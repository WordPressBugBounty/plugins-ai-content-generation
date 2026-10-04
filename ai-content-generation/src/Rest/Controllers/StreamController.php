<?php

namespace WPWand\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WPWand\Data\History;
use WPWand\Generation\Completeness;
use WPWand\Generation\ErrorFormatter;
use WPWand\Generation\JobRunner;
use WPWand\Generation\Prompt;
use WPWand\Generation\Providers\ProviderFactory;

/**
 * POST /wpwand/v1/generate/stream — live (token-by-token) generation for the assistant.
 *
 * Experimental, opt-in (Settings → "Stream generation live"). Builds the SAME command as
 * /generate (via Prompt) and proxies OpenAI's Server-Sent Events straight to the browser using
 * a raw cURL write callback — WordPress's HTTP API can't stream a response body, so we use cURL
 * directly and flush each chunk.
 *
 * Safety / compatibility: if the host can't stream (no OpenAI key, cURL missing) it returns a
 * normal JSON {stream:false} signal and the client silently falls back to /generate. The client
 * also falls back if the SSE yields an error or no tokens — so streaming can never break output.
 */
final class StreamController extends AbstractController
{
    protected string $rest_base = 'generate/stream';

    public function register_routes(): void
    {
        register_rest_route(
            $this->rest_namespace,
            '/' . $this->rest_base,
            [
                ['methods' => 'POST', 'callback' => [$this, 'stream'], 'permission_callback' => [$this, 'can_use']],
            ]
        );
    }

    public function stream(WP_REST_Request $request)
    {
        $provider = ProviderFactory::active();

        // Can't stream here (Claude / no key / no cURL) → tell the client to use /generate.
        if (! $provider->supportsStreaming() || ! $provider->isConfigured() || ! function_exists('curl_init')) {
            return new WP_REST_Response(['stream' => false, 'reason' => 'unsupported'], 200);
        }
        if ((string) $request->get_param('prompt') === '') {
            return new WP_REST_Response(['stream' => false, 'reason' => 'empty'], 200);
        }

        // A long-form template is written one section at a time by /generate. There is no single
        // request to stream, and streaming the whole article from one call is the thing that got it
        // cut off — so hand these straight back and let the client fall back.
        if (JobRunner::handles_template(sanitize_text_field((string) $request->get_param('template_name')))) {
            return new WP_REST_Response(['stream' => false, 'reason' => 'sectioned'], 200);
        }

        $built = Prompt::build($request);
        $temp  = (float) get_option('wpwand_temperature', 1.0);

        $this->open_stream();

        $payload = $provider->streamBody($built['command'], $temp);

        // Buffer the raw SSE so we can record the finished text in History (the streaming path
        // otherwise never sees the assembled content) — same as a normal /generate would.
        $raw = '';

        // Raw cURL is required here: WordPress's HTTP API cannot stream a response body, and this
        // endpoint proxies Server-Sent Events to the browser chunk by chunk.
        // phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt_array, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_close, Squiz.PHP.DiscouragedFunctions.Discouraged
        $ch = curl_init($provider->endpoint());
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => wp_json_encode($payload),
            CURLOPT_HTTPHEADER     => $provider->headers(),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_WRITEFUNCTION  => static function ($curl, $chunk) use (&$raw) {
                // Forward OpenAI's raw SSE bytes; the client parses delta.content.
                $raw .= $chunk;
                echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();
                return strlen($chunk);
            },
        ]);

        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        // phpcs:enable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt_array, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_close, Squiz.PHP.DiscouragedFunctions.Discouraged

        if ($err === '') {
            $shortfall = $this->record_history($request, $raw);
            if (null !== $shortfall) {
                // The text has already reached the browser, so this is the only place left to say it
                // came up short. The client reads delta.content and ignores anything else, so an
                // older build is unaffected.
                echo 'data: ' . wp_json_encode(['wpwand_partial' => $shortfall]) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput
            }
        } else {
            echo 'data: ' . wp_json_encode(['error' => ErrorFormatter::humanize($err)]) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput
        }
        echo "data: [DONE]\n\n";
        @flush();
        exit;
    }

    /**
     * Reassemble the streamed delta.content tokens and store the result in History.
     *
     * @return array<string, mixed>|null What to tell the client when the article came up short, or
     *                                   null when it is whole (or was never long-form to begin with).
     */
    private function record_history(WP_REST_Request $request, string $raw): ?array
    {
        $text = '';
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if (strpos($line, 'data:') !== 0) {
                continue;
            }
            $json = trim(substr($line, 5));
            if ($json === '' || $json === '[DONE]') {
                continue;
            }
            $obj = json_decode($json);
            $delta = $obj->choices[0]->delta->content ?? null;
            if (is_string($delta)) {
                $text .= $delta;
            }
        }

        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $template = sanitize_text_field((string) $request->get_param('template_name'));
        if ($template === '') {
            $template = __('Custom', 'ai-content-generation');
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_params();
        }
        unset($params['prompt'], $params['markdown']);

        // Only long-form output has an article shape to measure. A meta title or a tagline ends
        // without a full stop because that is how it is written, and judging it here would throw
        // away good work. The template's `markdown` flag is the line.
        $verdict = null;
        if ((bool) $request->get_param('markdown')) {
            $verdict = Completeness::check($text);
        }

        if (null !== $verdict && !Completeness::isComplete($verdict)) {
            $params['wpwand_completeness'] = [
                'status'    => $verdict['status'],
                'reason'    => $verdict['reason'],
                'promised'  => $verdict['promised'],
                'arrived'   => $verdict['arrived'],
                'missing'   => $verdict['missing'],
                'sensitive' => Completeness::sensitiveMissing($verdict['missing']),
            ];
        }

        History::record(
            $template,
            $params,
            History::response_from_text($text),
            History::title_from($text),
            History::SOURCE_EDITOR
        );

        if (null === $verdict || Completeness::isComplete($verdict)) {
            return null;
        }

        return [
            'status' => $verdict['status'],
            'reason' => $verdict['reason'],
        ];
    }

    /** Switch the response into an unbuffered SSE stream. */
    private function open_stream(): void
    {
        if (! headers_sent()) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache, no-transform');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // disable nginx proxy buffering
        }

        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', 'off');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        ignore_user_abort(true);
        @set_time_limit(0);
    }
}
