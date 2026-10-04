<?php

namespace WPWand\Data\Migrations;

/**
 * Give every schedule a running total of what it has written and what it has lost.
 *
 * WHY. The Automated Posts row showed `posts.length` and `failures.length` — the size of two arrays
 * the endpoint builds for the modal. Both are capped at twenty rows, so a schedule that had written
 * forty posts reported twenty and stopped moving. Worse, the arrays are built by looking up posts
 * that still exist: delete a post the schedule wrote and its total went **down**. A schedule's
 * output is a fact about its past, not a count of what survives in the database today.
 *
 * From here the two numbers are incremented where the thing happens — `posts_written` where the
 * post is actually created, `posts_failed` where a job is finally given up on — and nothing that
 * happens afterwards moves them.
 *
 * WHAT THIS BACKFILL CAN AND CANNOT SEE. It counts posts still carrying `_wpwand_schedule_id` and
 * jobs still in the queue table. On an install where posts have been deleted, or where a failed job
 * was cleared from the modal, **the starting number is a floor rather than the true history** —
 * there is no record left to count. That is the honest position: it is right from the migration
 * onward, and no worse than the screen was before it. Saying so here is the point, because a number
 * that is quietly a floor is worse than one that is known to be.
 *
 * No table and no column: schedules live in the `wpwand_schedules` option.
 *
 * IDEMPOTENT. A schedule that already carries `posts_written` is left alone. Without that check a
 * second activation would overwrite live counters with a fresh count of surviving posts, which is
 * exactly the bug this migration exists to end.
 */
final class Migration_1_7_0_AutomationPostCounters implements MigrationInterface
{
    private const OPTION = 'wpwand_schedules';

    public function version(): string
    {
        return '1.7.0';
    }

    public function describe(): string
    {
        return 'Backfill posts_written and posts_failed on every schedule (a running total, not the size of a capped array).';
    }

    public function up(): void
    {
        global $wpdb;

        $schedules = get_option(self::OPTION, []);

        if (!is_array($schedules) || $schedules === []) {
            return;
        }

        $jobs    = $wpdb->prefix . 'wpwand_gen_jobs';
        $changed = false;

        foreach ($schedules as $i => $schedule) {
            if (!is_array($schedule) || array_key_exists('posts_written', $schedule)) {
                continue; // Already counted, and the live counters are the truth now.
            }

            $id = (string) ($schedule['id'] ?? '');
            if ($id === '') {
                continue;
            }

            // Every post still tagged with this schedule. Not the capped query the modal uses —
            // that one stops at twenty because twenty is all a modal can show.
            $written = get_posts([
                'post_type'      => 'post',
                'post_status'    => 'any',
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'no_found_rows'  => false,
                'meta_key'       => '_wpwand_schedule_id', // phpcs:ignore WordPress.DB.SlowDBQuery
                'meta_value'     => $id,                   // phpcs:ignore WordPress.DB.SlowDBQuery
            ]);

            // The schedule id lives inside the job's settings JSON, the same way
            // AutomationController reads it.
            $needle = '%' . $wpdb->esc_like('"schedule_id":"' . $id . '"') . '%';
            $failed = (int) $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB
                "SELECT COUNT(*) FROM {$jobs} WHERE status = 'failed' AND settings LIKE %s",
                $needle
            ));

            $schedules[$i]['posts_written'] = count($written);
            $schedules[$i]['posts_failed']  = $failed;
            $changed = true;
        }

        if ($changed) {
            update_option(self::OPTION, $schedules, false);
        }
    }
}
