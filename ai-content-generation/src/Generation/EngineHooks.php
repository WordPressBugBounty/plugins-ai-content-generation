<?php

namespace WPWand\Generation;

/**
 * Wires the non-browser generation drivers into WordPress.
 *
 * Only the TRIGGER differs between drivers — they all advance the same step queue via
 * JobRunner::tick(). This registers the WP-Cron driver: a ~1-minute event that drains the
 * queue in a time-bounded loop (each tick is a single short step, so even the loop stays well
 * under any host request cap), and unschedules itself once the queue is empty.
 *
 * @see generation-engine-v2 (memory)
 */
final class EngineHooks
{
    private const HOOK = 'wpwand_gen_cron_tick';
    private const BUDGET = 45; // seconds a single cron run may spend draining

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'schedules']);
        add_action(self::HOOK, [$this, 'cron_tick']);
    }

    /**
     * @param array<string, array{interval:int, display:string}> $schedules
     * @return array<string, array{interval:int, display:string}>
     */
    public function schedules(array $schedules): array
    {
        if (!isset($schedules['wpwand_minutely'])) {
            $schedules['wpwand_minutely'] = [
                'interval' => 60,
                'display'  => __('Every minute (WP Wand)', 'wp-wand-pro'),
            ];
        }
        return $schedules;
    }

    public function cron_tick(): void
    {
        $runner = new JobRunner();
        $start  = time();

        while ($runner->is_running() && (time() - $start) < self::BUDGET) {
            $runner->tick();
        }

        if (!$runner->is_running()) {
            $ts = wp_next_scheduled(self::HOOK);
            if ($ts) {
                wp_unschedule_event($ts, self::HOOK);
            }
        }
    }
}
