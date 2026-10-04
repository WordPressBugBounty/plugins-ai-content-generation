<?php

namespace WPWand\Modules;

use WPWand\Admin\SettingsPage;
use WPWand\Rest\Controllers\SettingsController;
use WPWand\Rest\Controllers\OAuthController;

/** Settings screen + API (free core). */
final class SettingsModule extends AbstractModule
{
    public function id(): string
    {
        return 'settings';
    }

    public function label(): string
    {
        return 'Settings';
    }

    public function boot(): void
    {
        $this->admin(static fn () => (new SettingsPage())->register());
        $this->rest([SettingsController::class, OAuthController::class]);
    }
}
