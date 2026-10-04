<?php

namespace WPWand\Automation;

use WPWand\Generation\UsageLimits;

/**
 * Option-backed store + normaliser for automation schedules (Phase 3).
 *
 * A schedule describes recurring post generation: how often to run, where the titles come from
 * (a fixed list, or AI-generated from a subject), how many posts per run, and the status the
 * finished posts get. Schedules live in a single option as a JSON array — there are only ever a
 * handful, so a table would be overkill and a migration is avoided.
 */
final class Schedules
{
    public const OPTION = 'wpwand_schedules';

    /** Frequency → interval in seconds. */
    private const INTERVALS = [
        'hourly'  => HOUR_IN_SECONDS,
        'daily'   => DAY_IN_SECONDS,
        'weekly'  => WEEK_IN_SECONDS,
        // Added 2026-08-30. A blog that publishes once a month had to pick weekly and remember to
        // pause it, or accept four times the posts it wanted. `interval()` falls back to daily for
        // anything it does not know, so before this a saved 'monthly' ran every day — silently.
        'monthly' => MONTH_IN_SECONDS,
    ];

    /** What max_count() returns when a tier has no ceiling on a run. */
    public const NO_CAP = UsageLimits::NO_CAP;

    /** Posts one scheduled run may ask for, by tier. Owner's ladder, 2026-09-13: 10 / 20 / none. */
    private const MAX_COUNT = [
        'solo'   => 10,
        'growth' => 20,
        'agency' => self::NO_CAP,
    ];

    /**
     * Posts one run may ask for: 10 on Solo, 20 on Growth, no ceiling on Agency (NO_CAP), the free
     * per-run cap (5) otherwise. The form, the sanitiser and the runner all read this one number, so
     * a schedule can neither ask for more than its tier allows nor be handed more — and where there
     * is no ceiling, all three let the count through as typed.
     *
     * Every tier bills automation by the run, not the post, so this is the only thing that says how
     * big a run may be. Same split bulk makes with FREE_BULK_PER_RUN on free.
     */
    public static function max_count(): int
    {
        return self::MAX_COUNT[UsageLimits::tier()] ?? UsageLimits::FREE_AUTOMATION_PER_RUN;
    }

    /** Clamp a requested per-run count to the tier's ceiling, or leave it when there is none. */
    public static function clamp_count(int $count): int
    {
        $max = self::max_count();
        return $max > 0 ? max(1, min($max, $count)) : max(1, $count);
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        $raw = get_option(self::OPTION, []);
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        return is_array($raw) ? array_values(array_filter($raw, 'is_array')) : [];
    }

    /** @return array<string, mixed>|null */
    public static function get(string $id): ?array
    {
        foreach (self::all() as $s) {
            if (($s['id'] ?? '') === $id) {
                return $s;
            }
        }
        return null;
    }

    /**
     * Insert or update a schedule (matched by id). Returns the stored, normalised record.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function save(array $input): array
    {
        $all = self::all();
        $id  = isset($input['id']) && $input['id'] !== '' ? (string) $input['id'] : wp_generate_uuid4();

        $existing = self::get($id);
        $record   = self::normalize($input, $existing, $id);

        $found = false;
        foreach ($all as $i => $s) {
            if (($s['id'] ?? '') === $id) {
                $all[$i] = $record;
                $found   = true;
                break;
            }
        }
        if (!$found) {
            $all[] = $record;
        }

        update_option(self::OPTION, $all, false);
        return $record;
    }

    /**
     * Clone a schedule: copy its settings but start paused with no run history, so a duplicate
     * can't silently burn the monthly cap before the user reviews it. Returns the new record.
     *
     * @return array<string, mixed>|null null if the source schedule is missing
     */
    public static function duplicate(string $id): ?array
    {
        $src = self::get($id);
        if (!$src) {
            return null;
        }
        $copy = $src;
        unset($copy['id']); // save() mints a fresh id
        $copy['name']    = trim((string) ($src['name'] ?? '')) . ' (copy)';
        $copy['enabled'] = false; // a click on Duplicate shouldn't start generating
        return self::save($copy);
    }

    public static function delete(string $id): bool
    {
        $all  = self::all();
        $next = array_values(array_filter($all, static fn ($s) => ($s['id'] ?? '') !== $id));
        if (count($next) === count($all)) {
            return false;
        }
        update_option(self::OPTION, $next, false);
        return true;
    }

    /** Persist mutated fields (cursor, next_run, last_run, runs, enabled) of one schedule. */
    public static function update(string $id, array $patch): void
    {
        $all = self::all();
        foreach ($all as $i => $s) {
            if (($s['id'] ?? '') === $id) {
                $all[$i] = array_merge($s, $patch);
                update_option(self::OPTION, $all, false);
                return;
            }
        }
    }

    public static function interval(string $frequency): int
    {
        return self::INTERVALS[$frequency] ?? self::INTERVALS['daily'];
    }

    /** Compute the next run timestamp from now + the schedule's interval. */
    public static function next_run_from_now(string $frequency): int
    {
        return time() + self::interval($frequency);
    }

    /**
     * Clean + default every field so the rest of the code can trust the shape. Preserves runtime
     * fields (cursor/runs/last_run) from the existing record unless the caller overrides them.
     *
     * @param array<string, mixed>      $in
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private static function normalize(array $in, ?array $existing, string $id): array
    {
        $freq = in_array(($in['frequency'] ?? ''), array_keys(self::INTERVALS), true)
            ? (string) $in['frequency']
            : (string) ($existing['frequency'] ?? 'daily');

        $mode = in_array(($in['mode'] ?? ''), ['list', 'prompt'], true)
            ? (string) $in['mode']
            : (string) ($existing['mode'] ?? 'list');

        $topics = [];
        foreach ((array) ($in['topics'] ?? $existing['topics'] ?? []) as $t) {
            $t = trim(sanitize_text_field((string) $t));
            if ($t !== '') {
                $topics[] = $t;
            }
        }

        $status  = in_array(($in['post_status'] ?? ''), ['draft', 'pending', 'publish'], true)
            ? (string) $in['post_status']
            : (string) ($existing['post_status'] ?? 'draft');

        $enabled = array_key_exists('enabled', $in)
            ? (bool) $in['enabled']
            : (bool) ($existing['enabled'] ?? true);

        // Recompute next_run when the schedule is (re)enabled or the cadence changed.
        $next_run = (int) ($existing['next_run'] ?? 0);
        $freq_changed = $existing && (string) $existing['frequency'] !== $freq;
        if (!$existing || $freq_changed || (!($existing['enabled'] ?? false) && $enabled) || $next_run <= 0) {
            $next_run = $enabled ? self::next_run_from_now($freq) : 0;
        }

        return [
            'id'          => $id,
            'name'        => trim(sanitize_text_field((string) ($in['name'] ?? $existing['name'] ?? 'Untitled schedule'))) ?: 'Untitled schedule',
            'enabled'     => $enabled,
            'frequency'   => $freq,
            'mode'        => $mode,
            'topics'      => $topics,
            'subject'     => trim(sanitize_text_field((string) ($in['subject'] ?? $existing['subject'] ?? ''))),
            'loop'        => array_key_exists('loop', $in) ? (bool) $in['loop'] : (bool) ($existing['loop'] ?? false),
            'cursor'      => (int) ($existing['cursor'] ?? 0),
            'count'       => self::clamp_count((int) ($in['count'] ?? $existing['count'] ?? 1)),
            'post_status' => $status,
            'author'      => (int) ($in['author'] ?? $existing['author'] ?? get_current_user_id()),
            'tone'        => trim(sanitize_text_field((string) ($in['tone'] ?? $existing['tone'] ?? ''))),
            'keyword'     => trim(sanitize_text_field((string) ($in['keyword'] ?? $existing['keyword'] ?? ''))),
            'language'    => trim(sanitize_text_field((string) ($in['language'] ?? $existing['language'] ?? ''))),
            'word_count'  => max(0, (int) ($in['word_count'] ?? $existing['word_count'] ?? 0)),
            'toc_include' => array_key_exists('toc_include', $in) ? (bool) $in['toc_include'] : (bool) ($existing['toc_include'] ?? false),
            'faq_include' => array_key_exists('faq_include', $in) ? (bool) $in['faq_include'] : (bool) ($existing['faq_include'] ?? false),
            'next_run'    => $next_run,
            'last_run'    => (int) ($existing['last_run'] ?? 0),
            'runs'        => (int) ($existing['runs'] ?? 0),
            // What this schedule has actually produced. Read from the existing record only, never
            // from $in — a save must not be able to rewrite the schedule's own tally, and the form
            // that posts here has no business sending one.
            //
            // The screen used to count the rows it had been handed instead: `posts.length` and
            // `failures.length`, both of which are capped at twenty and both of which shrink when
            // someone deletes a post the schedule wrote. A schedule that had written forty posts
            // reported twenty, and deleting one of them made it nineteen.
            'posts_written' => max(0, (int) ($existing['posts_written'] ?? 0)),
            'posts_failed'  => max(0, (int) ($existing['posts_failed'] ?? 0)),
            'created_at'  => (int) ($existing['created_at'] ?? time()),
        ];
    }
}
