<?php

namespace WPWand\Core;

/**
 * Single source of truth for the Pro gate.
 *
 * Two DIFFERENT questions that must not be conflated:
 *  - active():   is the Pro plugin installed & loaded? (used only by the License screen, so you can
 *                still activate/deactivate when the license is off)
 *  - unlocked(): is Pro actually USABLE right now — plugin present AND license active? This is the
 *                gate for every Pro feature. Matches legacy, where the Pro feature code was require'd
 *                only when wpwand_pro_tala_check() (license active) was true, so deactivating the
 *                license re-locked the features.
 *
 * The earlier code gated features on active() alone, so deactivating left Pro features unlocked.
 */
final class Pro
{
    public static function active(): bool
    {
        return function_exists('wpwand_pro_init');
    }

    public static function unlocked(): bool
    {
        return self::active() && get_option('wpwand_pro_tala_status') === 'activated';
    }

    public static function agency(): bool
    {
        return self::unlocked() && (int) get_option('wpwand_pro_tala_agency') === 1;
    }
}
