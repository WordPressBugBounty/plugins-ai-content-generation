<?php

namespace WPWand\Modules;

use WPWand\Admin\WelcomePage;

/** Post-activation welcome screen (free core). */
final class WelcomeModule extends AbstractModule
{
    public function id(): string
    {
        return 'welcome';
    }

    public function label(): string
    {
        return 'Welcome';
    }

    public function boot(): void
    {
        $this->admin(static fn () => (new WelcomePage())->register());
    }
}
