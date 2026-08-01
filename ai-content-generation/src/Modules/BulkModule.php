<?php

namespace WPWand\Modules;

use WPWand\Admin\BulkPage;
use WPWand\Rest\Controllers\BulkController;

/** Bulk Posts generator. Free tier is capped monthly; Pro raises/lifts the cap. */
final class BulkModule extends AbstractModule
{
    public function id(): string
    {
        return 'bulk';
    }

    public function label(): string
    {
        return 'Bulk Posts';
    }

    public function boot(): void
    {
        $this->admin(static fn () => (new BulkPage())->register());
        $this->rest([BulkController::class]);
    }
}
