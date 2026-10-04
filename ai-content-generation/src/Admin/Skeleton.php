<?php

namespace WPWand\Admin;

/**
 * Shared, JS-free loading skeleton for the React admin screens.
 *
 * Each Page prints Skeleton::panel() inside its mount container so the browser paints an instant
 * layout — brand header + tab rail (crisp) and a blurred placeholder card with a spinner — the
 * moment WordPress renders the page, before the app's JS has downloaded. React's createRoot()
 * clears these children on first mount. The matching React loading state (shared LoadingSkeleton
 * component) reuses the same class names, so mount → data-ready is one seamless loader.
 *
 * All CSS is scoped by the wpwand-skel- class prefix (unique) so nothing leaks into wp-admin.
 * The Settings screen keeps its own tab-accurate variant; everything else uses this generic one.
 *
 * THE CSS IN styles() IS REPEATED VERBATIM IN assets/src/shared/LoadingSkeleton.js. Edit one and
 * the screen jumps the moment React mounts, because the server paint and the React paint stop
 * agreeing. Change both, in the same commit — adopt-design-foundation task 9.5 strips the
 * whitespace from each and compares them.
 *
 * Every var() carries its literal as a fallback on purpose: the two screens that print this,
 * Bulk and Automation, scope their tokens under .wpwand-bulk and .wpwa, not the .wpwand-app root
 * this markup uses. Without the fallbacks the placeholder loses its colours where it is used.
 *
 * A FALLBACK IS NOT THE SAME AS THE TWO PAINTS AGREEING. This copy prints into the bare mount
 * container, outside every token root, so it always takes the literal; the React copy renders
 * inside an app root and resolves the token. --wpw-field and --wpw-field-line survive that because
 * tokens.root emits them as the same literals. --wpw-brand does not: it is
 * var(--wpwand-brand-color, #3767fb), so a White Label install that ever set that property would
 * have the spinner change colour at the instant React mounts. That is why the spinner ring is the
 * literal #3767FB on both sides rather than the token. If the spinner is one day meant to follow an
 * agency's colour, styles() has to be handed the resolved value from Brand::resolve() so both sides
 * get it — not the token on one side only.
 *
 * The placeholder rests on the field fill; the shimmer is a light sweep across it, not a grey wash.
 */
final class Skeleton
{
    /**
     * @param string        $title Heading shown crisp at the top (e.g. the brand/page name).
     * @param string[]      $tabs  Optional crisp tab labels; the first renders active.
     * @param int           $rows  Number of blurred placeholder rows in the card body.
     */
    public static function panel(string $title, array $tabs = [], int $rows = 4): string
    {
        $rowHtml = str_repeat(
            '<div class="wpwand-skel-row"><span class="wpwand-skel-line"></span><span class="wpwand-skel-input"></span></div>',
            max(1, $rows)
        );

        $tabHtml = '';
        foreach (array_values($tabs) as $i => $label) {
            $tabHtml .= '<button type="button" class="wpws-tab' . ($i === 0 ? ' is-active' : '') . '">'
                . esc_html($label) . '</button>';
        }

        return self::styles()
            . '<div class="wpwand-app" role="status" aria-live="polite">'
            . '<div class="wpwand-app__header"><h1 class="wpwand-app__title">' . esc_html($title) . '</h1></div>'
            . '<div class="wpws-card">'
            . ($tabHtml !== '' ? '<div class="wpws-tabs">' . $tabHtml . '</div>' : '')
            . '<div class="wpwand-skel-panel">'
            . '<div class="wpws-panel wpwand-skel-blur">' . $rowHtml . '</div>'
            . '<div class="wpwand-skel-spin"><i></i><span>' . esc_html__('Loading…', 'ai-content-generation') . '</span></div>'
            . '</div>'
            . '</div></div>';
    }

    private static function styles(): string
    {
        return '<style>'
            . '.wpwand-skel-panel{position:relative;min-height:320px}'
            . '.wpwand-skel-blur{filter:blur(3px);opacity:.6;pointer-events:none;user-select:none}'
            . '.wpwand-skel-row{display:flex;flex-direction:column;gap:8px;padding:14px 0;border-bottom:1px solid #f1f2f4}'
            . '.wpwand-skel-line{display:block;height:12px;width:180px;border-radius:5px;background:#eef0f2}'
            . '.wpwand-skel-input{display:block;height:45px;max-width:420px;border:1px solid var(--wpw-field-line,#dcdce1);border-radius:6px;'
            . 'background-color:var(--wpw-field,#fcfcfd);'
            . 'background-image:linear-gradient(90deg,rgba(220,220,225,.14) 25%,rgba(255,255,255,.9) 37%,rgba(220,220,225,.14) 63%);'
            . 'background-size:400% 100%;'
            . 'animation:wpwand-skel-shine 1.4s ease infinite}'
            . '.wpwand-skel-spin{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;'
            . "justify-content:center;gap:14px;color:#6b7280;font-size:13px;font-family:var(--wpwand-font,'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif);z-index:2}"
            . '.wpwand-skel-spin i{width:26px;height:26px;border:3px solid #e5e7eb;border-top-color:#3767fb;border-radius:50%;'
            . 'animation:wpwand-skel-spin .8s linear infinite;display:block}'
            . '@keyframes wpwand-skel-shine{0%{background-position:100% 0}100%{background-position:-100% 0}}'
            . '@keyframes wpwand-skel-spin{to{transform:rotate(360deg)}}'
            . '</style>';
    }
}
