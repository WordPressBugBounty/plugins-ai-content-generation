<?php

namespace WPWand\Data;

/**
 * Resolved brand identity, shared across the UIs.
 *
 * Replicates the legacy gating: the custom name/color/logo apply ONLY under an active
 * Agency white-label (Pro + wpwand_pro_tala_agency); otherwise the WP Wand defaults are
 * returned (brand color #3767fb). This is the correct source for theming everywhere —
 * never the raw, ungated wpwand_brand_* options.
 */
final class Brand
{
    /**
     * @return array{name: string, color: string, logo: string, icon: string}
     */
    public static function resolve(): array
    {
        $agency = \WPWand\Core\Pro::agency(); // white-label needs an ACTIVE Agency license

        $logo_default = WPWAND_NEW_URL . 'assets/img/logo.svg';
        $icon_default = WPWAND_NEW_URL . 'assets/img/icon.svg';

        if (!$agency) {
            return [
                'name'  => 'WP Wand',
                'color' => '#3767fb',
                'logo'  => $logo_default,
                'icon'  => $icon_default,
            ];
        }

        $name  = (string) get_option('wpwand_brand_name', '');
        $color = (string) get_option('wpwand_brand_color', '');
        $logo  = (string) get_option('wpwand_logo', '');
        $icon  = (string) get_option('wpwand_logo_icon', '');

        return [
            'name'  => $name !== '' ? $name : 'WP Wand',
            'color' => $color !== '' ? $color : '#3767fb',
            'logo'  => $logo !== '' ? $logo : $logo_default,
            'icon'  => $icon !== '' ? $icon : $icon_default,
        ];
    }
}
