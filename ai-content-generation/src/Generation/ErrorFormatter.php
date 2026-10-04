<?php

namespace WPWand\Generation;

/**
 * Turns raw provider / API errors into a short, human-readable message.
 *
 * Providers (OpenAI, Claude, OpenRouter, …) and cURL frequently surface failures as a JSON blob
 * — e.g. {"error":{"message":"You exceeded your current quota","type":"insufficient_quota"}}.
 * Showing that raw to the user (in the floating panel, bulk popup, or settings) is noise. This
 * pulls out the human sentence, strips tags, and trims it to a sane length. Shared by the free
 * generation controllers, the settings key test, and the Pro bulk/automation job runner so every
 * surface speaks the same language.
 *
 * OpenRouter wraps upstream failures in a generic envelope whose `message` is literally
 * "Provider returned error"; the real reason (e.g. a model being rate-limited/down) lives in
 * `error.metadata.raw` (often itself a JSON string) with the upstream in `error.metadata.provider_name`.
 * The extractor below digs through that, and through the double-encoding the legacy generator adds
 * (it json_encode()s the provider error into an exception message), so the user sees the actual cause.
 */
class ErrorFormatter
{
    /**
     * @param mixed  $raw      A string, an error object/array, or anything stringable.
     * @param string $fallback Shown when nothing human can be extracted.
     * @param string $provider Provider id when the caller knows it ('openai', 'claude', 'deepseek',
     *                         'gemini', 'openrouter'). Some payloads name nobody — OpenAI's quota
     *                         error is just "check your plan and billing details", and Google words
     *                         its own almost identically — so a caller that already knows who it
     *                         asked should say so.
     */
    public static function humanize($raw, string $fallback = '', string $provider = ''): string
    {
        if ($fallback === '') {
            $fallback = __('Something went wrong. Please try again.', 'ai-content-generation');
        }

        // Normalise objects to assoc arrays so extraction is uniform.
        if (is_object($raw)) {
            $raw = json_decode((string) wp_json_encode($raw), true);
        }

        $msg = trim(wp_strip_all_tags(self::extract($raw)));

        // Transport failures first: these never reach the provider, so there is no JSON envelope to
        // unwrap and the raw cURL string ("cURL error 6: Could not resolve host: api.openai.com")
        // would otherwise go straight to the user.
        $transport = self::transport_message($msg, self::provider_label($raw, $provider));
        if ($transport !== '') {
            return $transport;
        }

        if ($msg === '') {
            return $fallback;
        }

        if (mb_strlen($msg) > 220) {
            $msg = mb_substr($msg, 0, 217) . '…';
        }

        return self::attribute_billing($raw, self::attribute_key_failure($raw, $msg, $provider), $provider);
    }

    /**
     * Rewrite "your key is wrong" into plain words instead of the provider's own sentence.
     *
     * OpenAI's own text for this is "Incorrect API key provided: sk-proj-***…iXYz. You can find
     * your API key at https://platform.openai.com/account/api-keys." — a masked key fragment and a
     * bare URL, in the provider's voice rather than an instruction in ours. Other providers word the
     * same failure differently (Claude: authentication_error; Gemini: "API key not valid"), so this
     * matches on the shape of the problem, not one provider's exact sentence.
     *
     * Images run on the OpenAI key only ({@see \WPWand\Rest\Controllers\ImageController}), so when
     * that key is the one that failed, image generation went down with it and the message says so —
     * whichever screen is showing this failure, not only the image tab itself.
     *
     * @param mixed $raw The original error, before extraction.
     */
    private static function attribute_key_failure($raw, string $msg, string $provider = ''): string
    {
        $haystack = strtolower($msg . ' ' . self::type_of($raw));

        $keyish = [
            'incorrect api key', 'invalid api key', 'api key not valid', 'api key is invalid',
            'invalid_api_key', 'api key provided', 'authentication_error',
        ];

        $hit = false;
        foreach ($keyish as $needle) {
            if (strpos($haystack, $needle) !== false) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            return $msg;
        }

        $label = self::provider_label($raw, $provider);
        $who   = $label !== '' ? $label : __('The provider', 'ai-content-generation');

        /* translators: %s: provider name, e.g. OpenAI */
        $rewritten = sprintf(__('%s rejected this key. Get a fresh one from your account and paste it in.', 'ai-content-generation'), $who);

        return self::with_image_note($rewritten, $label);
    }

    /**
     * Append the "images are down too" sentence when the failing provider is OpenAI, the only
     * provider image generation can use. Left off for every other provider, since their keys have
     * no bearing on images at all.
     */
    private static function with_image_note(string $msg, string $label): string
    {
        if ('OpenAI' !== $label) {
            return $msg;
        }

        return $msg . ' ' . __('Images run on this key too, so image generation is down until it works.', 'ai-content-generation');
    }

    /**
     * Rewrite a cURL / network failure as something the user can act on.
     *
     * The request never got to the provider, so nothing in the string is theirs — it is the host's
     * DNS, firewall or a request that outlasted the timeout. Empty when this isn't a transport
     * failure, so the caller falls through to the normal provider-envelope handling.
     *
     * @param string $msg   The already-extracted, tag-stripped message.
     * @param string $label Provider name when known ('OpenAI', 'Claude', …), '' otherwise.
     */
    private static function transport_message(string $msg, string $label): string
    {
        if ($msg === '') {
            return '';
        }

        $code = preg_match('/^cURL error (\d+)/i', $msg, $m) ? (int) $m[1] : 0;

        // Only an anchored "cURL error N" is a transport failure. A provider can put the same words
        // inside a perfectly normal JSON envelope — OpenRouter relays "Connection refused by upstream"
        // and "Operation timed out at the upstream provider" from the model host — and rewriting those
        // as a hosting problem loses the upstream's name and sends the user to their host for nothing.
        if (0 === $code) {
            return '';
        }

        $lower   = strtolower($msg);
        $dns     = 6 === $code || strpos($lower, 'could not resolve host') !== false;
        $timeout = 28 === $code || strpos($lower, 'operation timed out') !== false;
        $refused = 7 === $code || strpos($lower, 'connection refused') !== false;

        $who = $label !== '' ? $label : __('the AI provider', 'ai-content-generation');

        if ($timeout) {
            /* translators: %s: provider name, e.g. OpenAI */
            return sprintf(__('%s took too long to answer, so the request was dropped. Try again in a moment. If long pieces keep timing out, ask for something shorter.', 'ai-content-generation'), $who);
        }

        if ($dns) {
            /* translators: %s: provider name, e.g. OpenAI */
            return sprintf(__("Your site couldn't look up %s. That is nearly always DNS or a firewall on your hosting, not your API key — ask your host to allow outgoing requests, then try again.", 'ai-content-generation'), $who);
        }

        if ($refused) {
            /* translators: %s: provider name, e.g. OpenAI */
            return sprintf(__("Your site reached %s but the connection was refused. A firewall or proxy on your hosting is usually the cause — ask your host to allow outgoing HTTPS, then try again.", 'ai-content-generation'), $who);
        }

        /* translators: 1: provider name, e.g. OpenAI, 2: cURL error number */
        return sprintf(__("Your site couldn't complete the request to %1\$s (network error %2\$d). Try again in a minute. If it keeps happening, ask your host to check outgoing connections.", 'ai-content-generation'), $who, $code);
    }

    /**
     * Name the provider when the failure is really their billing, not ours.
     *
     * A provider says "You exceeded your current quota" about the user's own account, and on its own
     * that sentence reads like a WP Wand limit — so people uninstall over a bill they could have paid.
     * The 1.x generator prefixed these with "Openai Error"; the rewrite dropped the label, so this
     * puts the attribution back in the one case where confusing whose limit it is costs us the user.
     *
     * @param mixed $raw The original error, before extraction.
     */
    private static function attribute_billing($raw, string $msg, string $provider = ''): string
    {
        $haystack = strtolower($msg . ' ' . self::type_of($raw));
        // Each provider words this differently: OpenAI "exceeded your current quota", Anthropic
        // "credit balance is too low", DeepSeek just "Insufficient Balance", OpenRouter
        // "Insufficient credits". They all mean the same thing — the user owes their provider money.
        $billing = ['quota', 'insufficient_quota', 'balance', 'credit', 'billing', 'payment',
                    'out of funds', 'top up', 'top-up', 'exceeded your current'];

        $hit = false;
        foreach ($billing as $needle) {
            if (strpos($haystack, $needle) !== false) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            return $msg;
        }

        $label = self::provider_label($raw, $provider);
        if ($label === '') {
            return $msg;
        }

        return self::with_image_note(sprintf(
            /* translators: 1: provider name, e.g. OpenAI, 2: the provider's own error sentence */
            __('%1$s says: %2$s (this is your %1$s account, not a WP Wand limit.)', 'ai-content-generation'),
            $label,
            $msg
        ), $label);
    }

    /** The provider's error `type` field, if the payload carries one. */
    private static function type_of($raw): string
    {
        if (is_object($raw)) {
            $raw = json_decode((string) wp_json_encode($raw), true);
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw     = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return '';
        }
        $err = (isset($raw['error']) && is_array($raw['error'])) ? $raw['error'] : $raw;

        return isset($err['type']) && is_string($err['type']) ? $err['type'] : '';
    }

    /**
     * Which provider produced this error.
     *
     * Generator stamps the error `type` as "<provider>_error", which is the reliable signal. The
     * sentence itself is the fallback, since provider names appear in their own copy ("the Anthropic
     * API", "platform.openai.com").
     *
     * Order in the map is load-bearing for that last resort: the text search returns the FIRST
     * needle found, so 'gemini' sits at the end. An OpenRouter failure relaying a Google model
     * mentions "google/gemini-…" in its text, and OpenRouter has to win that tie — it is the one we
     * actually sent the request to, and the one holding the key the user would go and check.
     */
    private static function provider_label($raw, string $hint = ''): string
    {
        $labels = [
            'openai'     => 'OpenAI',
            'claude'     => 'Claude',
            'anthropic'  => 'Claude',
            'deepseek'   => 'DeepSeek',
            'openrouter' => 'OpenRouter',
            'gemini'     => 'Gemini',
        ];

        $hint = strtolower(trim($hint));
        if ($hint !== '' && isset($labels[$hint])) {
            return $labels[$hint];
        }

        $type = strtolower(self::type_of($raw));
        foreach ($labels as $needle => $label) {
            if ($needle !== 'anthropic' && strpos($type, $needle) === 0) {
                return $label;
            }
        }

        $text = strtolower(trim(wp_strip_all_tags(self::extract($raw))));
        foreach ($labels as $needle => $label) {
            if (strpos($text, $needle) !== false) {
                return $label;
            }
        }

        return '';
    }

    /**
     * Recursively pull the most specific human message out of a decoded error structure.
     *
     * @param mixed $data
     */
    private static function extract($data, int $depth = 0): string
    {
        if ($depth > 6) {
            return ''; // guard against pathological nesting
        }

        if (is_string($data)) {
            $t = trim($data);
            // A string that is itself a JSON blob — decode and recurse.
            if ($t !== '' && ($t[0] === '{' || $t[0] === '[')) {
                $decoded = json_decode($t, true);
                if (is_array($decoded)) {
                    $inner = self::extract($decoded, $depth + 1);
                    return $inner !== '' ? $inner : $t;
                }
            }
            return $t;
        }

        if (!is_array($data)) {
            return '';
        }

        // Unwrap an { error: … } envelope when present.
        $err = (isset($data['error']) && (is_array($data['error']) || is_string($data['error'])))
            ? $data['error']
            : $data;

        if (is_string($err)) {
            return self::extract($err, $depth + 1);
        }
        if (!is_array($err)) {
            return '';
        }

        // OpenRouter: the real upstream reason is in metadata.raw; message is just a generic wrapper.
        if (isset($err['metadata']) && is_array($err['metadata'])) {
            $rawMsg = self::extract($err['metadata']['raw'] ?? '', $depth + 1);
            if ($rawMsg !== '') {
                $provider = isset($err['metadata']['provider_name']) && is_string($err['metadata']['provider_name'])
                    ? trim($err['metadata']['provider_name'])
                    : '';
                return $provider !== '' ? $provider . ': ' . $rawMsg : $rawMsg;
            }
        }

        foreach (['message', 'code'] as $field) {
            if (isset($err[$field]) && (is_string($err[$field]) || is_numeric($err[$field]))) {
                $value = trim((string) $err[$field]);
                if ($value !== '') {
                    return self::extract($value, $depth + 1);
                }
            }
        }

        return '';
    }
}
