<?php

namespace WPWand\Data;

/**
 * The template catalog + seeding.
 *
 * ONE list. It used to be split into 'free' and 'pro' arrays, but nothing gated on the split:
 * every reader merged both, no template carried is_pro, and 'pro' was a byte-identical subset of
 * 'free'. Collapsing it removes a whole class of bug — a fix landing in one copy and not the other.
 *
 * MIGRATION-SAFE: the data is stored under the UNCHANGED `wpwand_data` option, so existing installs
 * (whose option is already populated, or refreshed from TALA on Pro) are untouched — this only seeds
 * a fresh/empty install or an explicit sync. On Pro the TALA fetch still overwrites wpwand_data with
 * whatever the licence server sends, which is why readers must keep accepting the old two-key shape.
 */
final class Templates
{
    /** The built-in catalog: ['Template Name' => [...], ...]. */
    public static function catalog(): array
    {
        return require __DIR__ . '/free-templates-data.php';
    }

    /**
     * Flatten whatever shape the wpwand_data option is carrying into one name => template list.
     *
     * Two shapes exist in the wild: the one this plugin ships, and the {free, pro} payload a Pro
     * licence sync writes straight from the licence server. Both must read.
     *
     * @param mixed $data
     * @return array<string, array<string, mixed>>
     */
    public static function flatten($data): array
    {
        if (!is_array($data)) {
            return [];
        }

        if (isset($data['free']) || isset($data['pro'])) {
            $free = is_array($data['free'] ?? null) ? $data['free'] : [];
            $pro  = is_array($data['pro'] ?? null) ? $data['pro'] : [];
            return array_merge($free, $pro);
        }

        return $data;
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
