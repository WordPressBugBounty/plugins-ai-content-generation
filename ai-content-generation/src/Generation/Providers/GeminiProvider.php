<?php

namespace WPWand\Generation\Providers;

/**
 * Gemini — Google's own OpenAI-compatible surface, so it inherits the base behaviour wholesale.
 *
 * Google publishes an OpenAI-shaped endpoint next to its native one. It takes the same Bearer auth
 * and the same chat/completions body, including `stream: true` with OpenAI's SSE framing, which is
 * why this reads like DeepSeek rather than like Claude. The `/v1beta/` in the path is Google's
 * versioning of the compatibility layer, not a preview flag of ours — there is no v1 spelling of it.
 *
 * Model ids come out of Google's listing prefixed with "models/"; {@see \WPWand\Generation\ModelCatalog}
 * strips that before storing, so the wire id here is the bare "gemini-2.5-pro" form the docs use.
 */
final class GeminiProvider extends AbstractProvider
{
    public function id(): string
    {
        return 'gemini';
    }

    public function label(): string
    {
        return 'Gemini';
    }

    public function endpoint(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
    }

    protected function keyOption(): string
    {
        return 'wpwand_gemini_api_key';
    }

    /**
     * Google's compatibility layer rejects OpenAI's two penalty fields outright:
     *
     *   HTTP 400  Invalid JSON payload received. Unknown name "frequency_penalty": Cannot find field.
     *
     * Sending them failed EVERY non-streaming generation on a Gemini install — bulk, automation and
     * the non-streaming assistant path alike. Streaming was unaffected only because streamBody()
     * never sent them, which is why the feature looked like it worked.
     */
    public function supportsPenalties(): bool
    {
        return false;
    }

    /**
     * Keep Gemini's reasoning out of the caller's token budget.
     *
     * The 2.5 models think before they answer, and on this endpoint those thinking tokens are
     * charged against max_tokens. Asking for a 600-word section with a 2,000-token budget spent
     * ~1,900 of it reasoning and came back with 80 words and finish_reason "length" — a request
     * that looks like it succeeded and is a third of what was asked for. Measured on the same
     * prompt at the same budget:
     *
     *   no reasoning_effort   336 words
     *   reasoning_effort=none 526 words
     *   reasoning_effort=low  610 words   <- asked for 600
     *
     * "low" rather than "none" because the thinking is worth something; it just should not be
     * paid for out of the article. Streaming never hit this only because streamBody() sends no
     * max_tokens at all, which is why the provider looked healthy.
     */
    public function chatBodyExtras(): array
    {
        return ['reasoning_effort' => 'low'];
    }

    /**
     * Room for the thinking, on top of whatever the caller asked to have written.
     *
     * Even at low effort the reasoning costs a near-constant amount, and it is charged against
     * max_tokens. Measured on one section prompt across four budgets:
     *
     *   budget 960   thinking ~800   ->  94 words, finish_reason "length"
     *   budget 1600  thinking  855   -> 583 words, finish_reason "stop"
     *   budget 2400  thinking  795   -> 606 words, finish_reason "stop"
     *   budget 3600  thinking  785   -> 545 words, finish_reason "stop"
     *
     * The cost does not scale with the budget, so a flat reserve is the right shape. 900 rather
     * than 800 to leave the measurement some margin.
     */
    public function tokenOverhead(): int
    {
        return 900;
    }
}
