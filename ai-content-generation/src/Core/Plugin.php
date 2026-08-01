<?php

namespace WPWand\Core;

use WPWand\Data\Migrations\MigrationRunner;
use WPWand\Generation\EngineHooks;
use WPWand\Modules\AssistantModule;
use WPWand\Modules\AutomationModule;
use WPWand\Modules\BulkModule;
use WPWand\Modules\HistoryModule;
use WPWand\Modules\ModuleManager;
use WPWand\Modules\SettingsModule;
use WPWand\Modules\WelcomeModule;

/**
 * Central bootstrapper for the new (REST + React) architecture.
 *
 * Features are booted as modules ({@see ModuleManager}): each is independently toggleable and the
 * Pro-only ones are gated. The Pro plugin adds its own modules via the `wpwand_register_modules`
 * action — the seam that lets Pro feature code live in the Pro plugin (see the migration plan).
 *
 * Runs side by side with the legacy bootstrap in wp-wand.php until the strangler migration is done.
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    private bool $booted = false;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * Register the new subsystems. Safe to call once; repeat calls are no-ops.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        // Version-flagged, idempotent DB migrations (admin context only).
        add_action('admin_init', [$this, 'run_migrations']);

        // Generation-engine infrastructure (WP-Cron drainer + schedule) for the Bulk/Automation job
        // queue. Registered on every load so queued jobs drain unattended; harmless when idle.
        add_action('plugins_loaded', static function () {
            if (class_exists(EngineHooks::class)) {
                (new EngineHooks())->register();
            }
        }, 21);

        // Boot feature modules late (after plugins_loaded:20) so the Pro plugin has registered its
        // own modules via wpwand_register_modules and wpwand_pro_init() exists for the Pro gate.
        add_action('plugins_loaded', [$this, 'boot_modules'], 99);
    }

    /**
     * Build the module registry, let the Pro plugin contribute, and boot every enabled module.
     */
    public function boot_modules(): void
    {
        // Cutover: the React Settings screen now owns the top-level "wpwand" menu, so drop the
        // legacy free menu registration (parent + legacy Settings submenu). Runs before admin_menu
        // fires. The legacy render functions stay defined (harmless) — only the menu is removed.
        if (is_admin()) {
            remove_action('admin_menu', 'wpwand_register_menu');
            // Seed the template catalog on a fresh/empty install (no-op once wpwand_data exists,
            // and Pro overwrites it from TALA) — so templates work without the legacy seeder.
            \WPWand\Data\Templates::seed();
        }

        $manager = new ModuleManager();
        foreach ($this->core_modules() as $module) {
            $manager->add($module);
        }
        do_action('wpwand_register_modules', $manager);
        $manager->boot();
    }

    /**
     * The modules shipped with the FREE plugin. Bulk and Automation now live here too (capped on the
     * free tier; Pro raises/lifts the cap). The remaining Pro-only modules (Custom Prompts, WooCommerce,
     * SEO, License) are registered by the Pro plugin via the wpwand_register_modules action.
     *
     * @return \WPWand\Modules\Module[]
     */
    private function core_modules(): array
    {
        return [
            new SettingsModule(),
            new HistoryModule(),
            new AssistantModule(),
            new WelcomeModule(),
            new BulkModule(),
            new AutomationModule(),
        ];
    }

    public function run_migrations(): void
    {
        (new MigrationRunner())->run();
    }
}
