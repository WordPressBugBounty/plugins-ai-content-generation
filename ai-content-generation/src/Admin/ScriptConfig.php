<?php

namespace WPWand\Admin;

/**
 * One place to put data on the shared `wpwandApi` JS global.
 *
 * Seven screens across both plugins were each calling wp_localize_script() with the name
 * `wpwandApi`, and that function REPLACES the global rather than merging into it. Whichever
 * enqueue ran last won, so the keys the others depended on simply vanished:
 *
 *   - The assistant loads on every admin screen and ships `brand` and `togglerPosition`. On
 *     Bulk or Automated Posts the page's own call overwrote both, so the panel lost the brand
 *     colour and rendered its floating trigger even when the user had chosen the admin-bar
 *     position — two triggers at once.
 *   - On the Pro Licence screen the surviving object was just `{root, nonce}`.
 *
 * `wp_add_inline_script( …, 'before' )` prints ahead of the bundle exactly like localize does, so
 * ordering between enqueues stops mattering: each page contributes its keys and the rest survive.
 */
final class ScriptConfig
{
    /** The global every app reads its REST root, nonce and page config from. */
    private const GLOBAL_NAME = 'wpwandApi';

    /**
     * Merge $data into window.wpwandApi for $handle.
     *
     * @param string               $handle Registered script handle to attach to.
     * @param array<string, mixed> $data   Keys this screen contributes.
     */
    public static function merge(string $handle, array $data): void
    {
        $json = wp_json_encode($data);
        if ($json === false) {
            return;
        }

        wp_add_inline_script(
            $handle,
            'window.' . self::GLOBAL_NAME . ' = Object.assign( window.' . self::GLOBAL_NAME . ' || {}, ' . $json . ' );',
            'before'
        );
    }

    /**
     * The keys every screen needs: where the REST API is, and the nonce to call it with.
     *
     * @return array<string, mixed>
     */
    public static function base(): array
    {
        return [
            'root'  => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
        ];
    }
}
