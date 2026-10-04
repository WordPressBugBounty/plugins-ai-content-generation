<?php

namespace WPWand\Generation;

/**
 * The house style — one set of writing rules every template inherits.
 *
 * Forty-five prompts each carried their own idea of how to write, and most carried none. This is the
 * single place that answers "what should anything this plugin writes sound like", so improving the
 * output is one edit rather than forty-five.
 *
 * EVERY RULE HERE WAS MEASURED, and measured on more than one provider — because the first time it
 * was measured on one, the conclusion was wrong. Same 2,400-word article about bakery email
 * marketing, one rule changed at a time:
 *
 *   rule added                          Gemini 2.5 Flash      DeepSeek
 *   ---------------------------------   -------------------   --------------------
 *   ceiling on exclamation marks        40 -> 3, then 0       0
 *   named list of words to avoid        8 hits -> 0           0
 *   "two actionable numbers"            3 -> 16               18
 *   explicit "refer back" instruction   0 callbacks -> 3      yes
 *   the owner's own situation           1 detail -> 18        32
 *   vary the paragraph rhythm           IGNORED, twice        spread 0.30 -> 0.43
 *
 * Three lessons are baked into how these are phrased:
 *
 * 1. A rule stated as a preference does nothing. "Avoid marketing language" changed no count; a
 *    named list of words removed every hit.
 * 2. A behaviour that is not written down competes with every behaviour that is. Cross-section
 *    callbacks were emerging on their own until tone rules were added, then vanished until they
 *    were asked for in as many words.
 * 3. A rule proved on one provider is proved on one provider. This plugin routes to five.
 *
 * The one thing no rule here can produce is the "I have seen this happen" layer that separates a
 * specialist from a competent generalist. That cannot be invented without inventing experience —
 * the same fault as "Studies consistently show", which this audit found in shipped output. It can
 * only be supplied, and a Pro user has already typed it into About your business. See rules().
 */
final class HouseStyle
{
    /**
     * Words that make writing sound like a brochure. Named, because "avoid marketing language"
     * measurably does nothing and this measurably removes all of them.
     */
    private const AVOID = [
        'magic', 'magical', 'delightful', 'delight', 'wonderful', 'amazing',
        'journey', 'game-changer', 'unlock', 'dive in', 'sprinkle', 'warm hug',
        'in today\'s fast-paced world', 'it\'s no secret',
    ];

    /**
     * Rules one model follows and another ignores. Sent regardless — they cost a line, and they
     * land on the providers that honour them — but their presence is not a promise about output.
     *
     * Gemini 2.5 Flash ignored "at least two paragraphs under twenty words" twice: paragraph spread
     * stayed at 0.30 of the mean and not one short paragraph appeared. DeepSeek followed the same
     * sentence — spread 0.43, three short paragraphs. Even paragraph length is the clearest
     * remaining tell that a machine wrote something, so this matters, and an instruction is not what
     * fixes it on the models that ignore it. Post-processing is the untried option there.
     *
     * "Take a side" moved opinion markers from 0 to 2 in 2,400 words on Gemini. Better than nothing,
     * not yet a voice, and not re-measured elsewhere.
     */
    public const MODEL_DEPENDENT = ['paragraph rhythm', 'taking a side'];

    /**
     * The rules, as one block appended to a template's own instructions.
     *
     * @param array<string, string> $context Optional. 'business' and 'audience' — what a Pro
     *                                       install already collects on the Advanced tab.
     */
    public static function rules(array $context = []): string
    {
        $avoid = implode(', ', self::AVOID);

        $rules = [
            'Write like a person who knows the subject talking to one who does not. Contractions, '
                . 'concrete nouns, plain words.',
            'AT MOST ONE exclamation mark in the whole piece. Prefer none. Enthusiasm comes from '
                . 'the detail, not the punctuation.',
            "Do not use any of these: {$avoid}. Do not open a paragraph with Okay or So.",
            'Include real numbers a reader could act on — a typical rate, a sensible frequency, a '
                . 'figure that pays for itself. Give ranges. Say plainly when a number is a rule of '
                . 'thumb rather than a measured figure.',
            'NEVER cite a study, survey, report or statistic you cannot name. "Studies show" with '
                . 'no study is worse than saying nothing.',
            'No filler opening. Start with the thing itself.',
            // Followed by DeepSeek, ignored by Gemini 2.5 Flash. Cheap enough to send anyway.
            'Vary the rhythm. Not every paragraph the same size - at least two well under twenty '
                . 'words. A single blunt line is often the best thing on the page. Do not use the '
                . 'same shape twice in a row.',
            'Take a side at least once. Say which option you would actually pick and which you would '
                . 'not bother with, and why. "Both work" is not advice.',
        ];

        $business = trim((string) ($context['business'] ?? ''));
        $audience = trim((string) ($context['audience'] ?? ''));

        if ($business !== '') {
            // The one honest route to the "I have seen this happen" layer that separates a
            // specialist from a competent generalist. It cannot be invented — but a Pro user has
            // already typed their own version of it, and it was going unused.
            // Reworded on 2026-10-02. "The person you are writing for" read as the AUDIENCE, so the
            // model addressed the reader as the author — "As a WordPress developer dealing with
            // client projects, you know…" in an article about cold brew — and "Refer to it
            // specifically" put the business into every article whatever it was about: sixteen
            // sentences on WordPress in a piece about morning walks. The rule now says who this
            // is (the author), who it is not (the reader), and that it belongs only where the
            // subject touches it. Where it does, it is still asked for by name: their days, their
            // numbers — the measured 1 → 18 details came from that wording and it stays.
            $rules[] = "This post goes out under this person's name. In their own words:\n"
                . "\"{$business}\"\n"
                . 'Write as them. Where the subject touches their work, use their actual situation — '
                . 'their days, their numbers, what they already tried and how it went — and be '
                . 'specific about it. Where it does not, leave their work out; never force it in. '
                . 'The reader is NOT this person, so never address the reader as one of them. Do NOT '
                . 'invent experience they did not describe, and never claim to have worked with '
                . 'other businesses.';
        }

        if ($audience !== '') {
            $rules[] = "The readers are: {$audience}";
        }

        return "\n\nHow to write it:\n- " . implode("\n- ", $rules);
    }

    /**
     * About your business and Who you are writing for, for the callers that have no per-request
     * switch of their own — Bulk Posts and Automated Posts.
     *
     * Both fields are Pro (Settings → Advanced locks them on a free install), and the assistant
     * only sends them when its own Pro-only toggles are on. Bulk and automation read the options
     * directly, so a free install with text left in those fields had it sent on every batch while
     * the assistant on the same site sent nothing. One gate for the three, the owner's call on
     * 2026-10-02: Pro only.
     *
     * @return array<string, string> Empty on a free install.
     */
    public static function owner_context(): array
    {
        if (!class_exists('WPWand\\Core\\Pro') || !\WPWand\Core\Pro::unlocked()) {
            return [];
        }

        return [
            'business' => (string) get_option('wpwand_busines_details', ''),
            'audience' => (string) get_option('wpwand_targated_customer', ''),
        ];
    }

    /**
     * The extra rule for one section of a longer piece, where continuity is the thing at risk.
     *
     * @param string $previousTail The closing words of the section before this one.
     */
    public static function continuity(string $previousTail): string
    {
        if (trim($previousTail) === '') {
            return "\n- This is the opening section.";
        }

        return "\n- The previous section ended like this:\n  \"...{$previousTail}\"\n"
            . '- Open by picking up where that left off. Somewhere in this section, refer back to '
            . 'something the reader has already been told — one sentence, in plain speech, the way '
            . 'you would say it out loud. Not "as mentioned above".';
    }
}
