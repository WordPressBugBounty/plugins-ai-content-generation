<?php

namespace WPWand\Data;

/**
 * Writes generation history rows — restoring the legacy contract that every successful Assistant
 * generation is recorded (the legacy admin-ajax handler called wpwand_pro_add_history(); the new
 * REST endpoints dropped it, so History showed nothing for React-era generations).
 *
 * Schema (wpwand_history, see Migration_1_0_0_BaselineSchema): template_name, prompt_info (JSON of
 * the request), response (JSON of the AI response object — the {choices:[{message:{content}}]}
 * shape HistoryController decodes back). Mirrors wp-wand-pro/inc/api.php::wpwand_pro_add_history().
 */
final class History
{
    /**
     * @param string               $templateName Display name of the template used.
     * @param array<string, mixed>  $promptInfo   The request params (stored as JSON).
     * @param object|array          $response     The AI response object (must carry ->choices).
     */
    public static function record(string $templateName, array $promptInfo, $response): void
    {
        if ($templateName === '' || empty($response)) {
            return;
        }

        global $wpdb;
        // Custom history table; $wpdb->insert is prepared. phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            $wpdb->prefix . 'wpwand_history',
            [
                'template_name' => $templateName,
                'prompt_info'   => wp_json_encode($promptInfo),
                'response'      => wp_json_encode($response),
            ],
            ['%s', '%s', '%s']
        );
    }

    /** Build a response object in the stored shape from already-assembled plain text. */
    public static function response_from_text(string $text): array
    {
        return ['choices' => [['message' => ['content' => $text]]]];
    }
}
