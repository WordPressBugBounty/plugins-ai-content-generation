<?php

namespace WPWand\Data;

/**
 * Free template catalog + seeding, in the new architecture.
 *
 * Port of the legacy wpwand_dummy_datas() (the built-in free template list) + wpwand_get_data()
 * (which seeds the wpwand_data option from it). The catalog content lives byte-identically in
 * free-templates-data.php (exported from the legacy function — never hand-edited).
 *
 * MIGRATION-SAFE: the data is stored under the UNCHANGED `wpwand_data` option, so existing installs
 * (whose option is already populated, or refreshed from TALA on Pro) are untouched — this only seeds
 * a fresh/empty install or an explicit sync. On Pro, the TALA fetch still overwrites wpwand_data.
 */
final class Templates
{
    /** The built-in free catalog: ['free' => [...], 'pro' => [...]]. */
    public static function catalog(): array
    {
        return require __DIR__ . '/free-templates-data.php';
    }

    /**
     * Seed the wpwand_data option from the built-in catalog. Mirrors wpwand_get_data($sync):
     * writes only when the option is empty, or when $force (the "Sync" action) is set.
     */
    public static function seed(bool $force = false): bool
    {
        if (!get_option('wpwand_data') || $force) {
            return (bool) update_option('wpwand_data', self::catalog());
        }
        return false;
    }
}
