<?php

namespace WPWand\Admin;

/**
 * Injects the React floating assistant on every admin screen (the legacy behavior) and
 * removes the legacy footer panel — the assistant cutover. The panel renders closed; its
 * edge trigger opens it. Insert-to-editor lights up automatically on editor screens
 * (Results detects the active editor).
 */
final class AssistantInjector
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'inject']);
    }

    public function inject(string $hook): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        // The new panel replaces the legacy footer assistant everywhere.
        remove_action('admin_footer', 'wpwand_frontend_callback');

        AssistantAssets::enqueue();
        add_action('admin_footer', [$this, 'mount']);
    }

    public function mount(): void
    {
        echo '<div id="wpwand-assistant-root"></div>';
    }
}
