<?php

namespace WPWand\Modules;

use WPWand\Automation\AutomationRunner;
use WPWand\Rest\Controllers\AutomationController;
use WPWand\Admin\AutomationPage;

/** Scheduled recurring post automation. Free tier is capped monthly; Pro raises/lifts the cap. */
final class AutomationModule extends AbstractModule
{
    public function id(): string
    {
        return 'automation';
    }

    public function label(): string
    {
        return 'Automation';
    }

    public function boot(): void
    {
        // Front + admin so the WP-Cron tick runs unattended.
        (new AutomationRunner())->register();

        $this->admin(static fn () => (new AutomationPage())->register());
        $this->rest([AutomationController::class]);
    }
}
