<?php

namespace WPWand\Generation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The provider refused a request because the key has sent too many, and said when to come back.
 *
 * This is not a failure of the request and it used to be treated as one. Measured on 2026-10-02 on a
 * free Gemini key (five requests a minute, twenty a day): the sixth request in a minute was answered
 * "retry in 4.29s", the queue tried twice more at once, and the post was marked failed about a second
 * later with the article half written. Waiting four seconds would have finished it.
 *
 * Two cases, told apart by how long the provider says to wait:
 *  - a short wait is waited out, and costs the job nothing;
 *  - a long one (a daily allowance, or anything past {@see self::LONG}) is final at once — trying
 *    again twice more only spends two more refusals — and the sentence says when it comes back.
 *
 * Running out of money is neither: OpenAI answers that with a 429 as well (`insufficient_quota`),
 * and no amount of waiting fixes a bill. {@see ErrorFormatter} already words that one.
 */
final class RateLimited extends \RuntimeException
{
    /** Seconds worth sleeping through inside one request (the assistant, which has no queue). */
    public const SHORT = 15;

    /** Past this many seconds the limit is not one to wait out. */
    public const LONG = 120;

    /** What to assume when a provider refuses and names no wait. */
    private const UNSTATED = 30;

    /** @var int Seconds the provider asked for. */
    public $wait = 0;

    /** @var bool True when the wait is too long to sit out. */
    public $long = false;

    /** @var string The provider's name, for the line shown while waiting. */
    public $label = '';

    /** @var string The reason to store when the provider asked for a pause over and over and never let the request through. */
    public $stuck = '';

    /**
     * Read Generator's `->error` and return the refusal it describes, or null when it is anything
     * else.
     *
     * @param mixed  $error What Generator returned as `->error`.
     * @param string $then  What the reader can do once the wait is over — a whole sentence, or ''.
     */
    public static function from($error, string $then = ''): ?self
    {
        $e = self::unwrap($error);
        if ($e === null) {
            return null;
        }

        $code = isset($e['code']) && is_scalar($e['code']) ? (string) $e['code'] : '';
        $type = isset($e['type']) && is_string($e['type']) ? strtolower($e['type']) : '';

        // A 429 that is a bill, not a rate.
        if ($type === 'insufficient_quota' || $code === 'insufficient_quota') {
            return null;
        }

        $limited = (int) ($e['wpwand_http'] ?? 0) === 429
            || $code === '429'
            || $code === 'rate_limit_exceeded'
            || $type === 'rate_limit_error'
            || (isset($e['status']) && $e['status'] === 'RESOURCE_EXHAUSTED');
        if (!$limited) {
            return null;
        }

        $text  = self::text_of($e);
        $wait  = self::wait_of($e, $text);
        $daily = self::is_daily($e, $text);
        if ($wait === null) {
            // A daily allowance with no time on it is still not worth three attempts.
            $wait = $daily ? self::LONG + 1 : self::UNSTATED;
        }

        $known = self::label_of($error);
        $label = $known !== '' ? $known : __('The AI provider', 'ai-content-generation');
        $human = self::human($wait);

        $what = $daily
            /* translators: 1: provider name, e.g. Gemini, 2: a length of time, e.g. "10 hours" */
            ? __('%1$s says this key has used up its requests for today. They come back in about %2$s.', 'ai-content-generation')
            /* translators: 1: provider name, e.g. Gemini, 2: a length of time, e.g. "3 minutes" */
            : __('%1$s won’t take more requests from this key for now. It says to try again in about %2$s.', 'ai-content-generation');

        $tail = [];
        if ($then !== '') {
            $tail[] = $then;
        }
        if ($known !== '') {
            /* translators: %s: provider name, e.g. Gemini */
            $tail[] = sprintf(__('That’s your %s account’s limit, not a WP Wand limit.', 'ai-content-generation'), $known);
        }

        $self        = new self(implode(' ', array_merge([sprintf($what, $label, $human)], $tail)));
        $self->wait  = $wait;
        $self->long  = $daily || $wait > self::LONG;
        $self->label = $label;
        // "Try again in about 1 second" is the wrong thing to say to someone whose post has just
        // been refused six times a second apart.
        $self->stuck = implode(' ', array_merge([sprintf(
            /* translators: %s: provider name, e.g. Gemini */
            __('%s keeps saying this key is sending too many requests. Give it a few minutes.', 'ai-content-generation'),
            $label
        )], $tail));

        return $self;
    }

    /** The line shown while the queue waits. Built from the time left, so it counts down. */
    public static function note(string $label, int $seconds): string
    {
        return sprintf(
            /* translators: 1: provider name, e.g. Gemini, 2: a length of time, e.g. "40 seconds" */
            __('%1$s asked for a short pause. Carrying on in about %2$s.', 'ai-content-generation'),
            $label,
            self::human(max(1, $seconds))
        );
    }

    /**
     * The provider's own error as an array. Generator hands it over as JSON inside `message`.
     *
     * @param mixed $error
     * @return array<string, mixed>|null
     */
    private static function unwrap($error): ?array
    {
        $raw = json_decode((string) wp_json_encode($error), true);
        if (!is_array($raw)) {
            return null;
        }

        if (isset($raw['message']) && is_string($raw['message'])) {
            $inner = json_decode($raw['message'], true);
            if (is_array($inner)) {
                return isset($inner['error']) && is_array($inner['error']) ? $inner['error'] : $inner;
            }
        }

        return $raw;
    }

    /**
     * Every sentence the provider wrote. OpenRouter keeps the upstream's in `metadata.raw`.
     *
     * @param array<string, mixed> $e
     */
    private static function text_of(array $e): string
    {
        $text = isset($e['message']) && is_string($e['message']) ? $e['message'] : '';
        if (isset($e['metadata']['raw']) && is_string($e['metadata']['raw'])) {
            $text .= ' ' . $e['metadata']['raw'];
        }

        return $text;
    }

    /**
     * How long the provider said to wait, in whole seconds, or null when it did not say.
     *
     * Three places, in the order they can be trusted: the Retry-After header, Google's RetryInfo,
     * and the sentence ("Please retry in 10h4m46.65s", "Please try again in 20s").
     *
     * @param array<string, mixed> $e
     */
    private static function wait_of(array $e, string $text): ?int
    {
        $header = isset($e['wpwand_retry_after']) ? trim((string) $e['wpwand_retry_after']) : '';
        if ($header !== '') {
            if (is_numeric($header)) {
                return max(1, (int) ceil((float) $header));
            }
            $at = strtotime($header);
            if ($at !== false) {
                return max(1, $at - time());
            }
        }

        foreach ((array) ($e['details'] ?? []) as $detail) {
            if (is_array($detail) && isset($detail['retryDelay']) && is_string($detail['retryDelay'])
                && preg_match('/^([\d.]+)s$/', $detail['retryDelay'], $m)) {
                return max(1, (int) ceil((float) $m[1]));
            }
        }

        if (preg_match('/(?:retry|try again) in\s+((?:[\d.]+\s*(?:ms|h|m|s)\s*)+)/i', $text, $m)
            && preg_match_all('/([\d.]+)\s*(ms|h|m|s)/i', $m[1], $units, PREG_SET_ORDER)) {
            $seconds = 0.0;
            $per     = ['h' => 3600, 'm' => 60, 's' => 1, 'ms' => 0.001];
            foreach ($units as $u) {
                $seconds += (float) $u[1] * $per[strtolower($u[2])];
            }

            return max(1, (int) ceil($seconds));
        }

        return null;
    }

    /**
     * True when the allowance that ran out is the day's.
     *
     * @param array<string, mixed> $e
     */
    private static function is_daily(array $e, string $text): bool
    {
        foreach ((array) ($e['details'] ?? []) as $detail) {
            foreach ((array) (is_array($detail) ? ($detail['violations'] ?? []) : []) as $violation) {
                if (is_array($violation) && isset($violation['quotaId']) && stripos((string) $violation['quotaId'], 'PerDay') !== false) {
                    return true;
                }
            }
        }

        return (bool) preg_match('/per day|requests per day|\bRPD\b/i', $text);
    }

    /**
     * The provider's name, or '' when the error does not say whose it is.
     *
     * @param mixed $error Generator stamps `type` as "<provider>_error".
     */
    private static function label_of($error): string
    {
        $type = is_object($error) && isset($error->type) ? (string) $error->type : '';
        $id   = (string) preg_replace('/_error$/', '', $type);

        if (in_array($id, ['openai', 'claude', 'gemini', 'deepseek', 'openrouter'], true)) {
            return Providers\ProviderFactory::forProvider($id)->label();
        }

        return '';
    }

    /** "40 seconds", "3 minutes", "10 hours". */
    private static function human(int $seconds): string
    {
        if ($seconds < 90) {
            /* translators: %d: a number of seconds */
            return sprintf(_n('%d second', '%d seconds', $seconds, 'ai-content-generation'), $seconds);
        }
        if ($seconds < 5400) {
            $n = (int) round($seconds / 60);
            /* translators: %d: a number of minutes */
            return sprintf(_n('%d minute', '%d minutes', $n, 'ai-content-generation'), $n);
        }
        $n = (int) round($seconds / 3600);

        /* translators: %d: a number of hours */
        return sprintf(_n('%d hour', '%d hours', $n, 'ai-content-generation'), $n);
    }
}
