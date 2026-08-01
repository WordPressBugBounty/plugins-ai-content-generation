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
     * @param mixed $raw A string, an error object/array, or anything stringable.
     */
    public static function humanize($raw, string $fallback = ''): string
    {
        if ($fallback === '') {
            $fallback = __('Something went wrong. Please try again.', 'wp-wand');
        }

        // Normalise objects to assoc arrays so extraction is uniform.
        if (is_object($raw)) {
            $raw = json_decode((string) wp_json_encode($raw), true);
        }

        $msg = trim(wp_strip_all_tags(self::extract($raw)));
        if ($msg === '') {
            return $fallback;
        }

        if (mb_strlen($msg) > 220) {
            $msg = mb_substr($msg, 0, 217) . '…';
        }

        return $msg;
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
