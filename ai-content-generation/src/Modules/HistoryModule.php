<?php

namespace WPWand\Modules;

use WPWand\Admin\HistoryPage;
use WPWand\Rest\Controllers\HistoryController;

/** Generation history screen + API (free). */
final class HistoryModule extends AbstractModule
{
    public function id(): string
    {
        return 'history';
    }

    public function label(): string
    {
        return 'History';
    }

    public function boot(): void
    {
        $this->admin(static fn () => (new HistoryPage())->register());
        $this->rest([HistoryController::class]);
    }
}
