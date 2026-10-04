<?php

namespace WPWand\Generation;

use WPWand\Generation\Providers\ProviderFactory;

/**
 * Automatic provider failover — keep writing when the chosen provider stops answering for a reason
 * that will not fix itself.
 *
 * OFF by default (`wpwand_failover_enabled`). While it is off {@see Generator::generate()} hands the
 * request straight to {@see Generator::attempt()} and nothing here runs, so an install that has not
 * opted in behaves exactly as it did before this file existed.
 *
 * WHAT COUNTS AS A REASON TO SWITCH
 *
 *   out of quota / credit   → switch, quietly. The user's bill is their business; the post still gets
 *                             written and the notice explains it afterwards.
 *   key invalid or expired  → switch, and say so. A dead key needs a human, so the response and the
 *                             admin notice both carry a warning.
 *   timeout / DNS / 5xx     → do NOT switch. Those are transient, and the same provider a minute
 *                             later is a better answer than somebody else's balance right now.
 *
 * The first two buckets are ErrorFormatter's, not ours: {@see ErrorFormatter::transport_message()}
 * decides what is a network failure and {@see ErrorFormatter::attribute_billing()} decides what is a
 * billing failure, and this class asks THOSE methods rather than keeping a second copy of the needle
 * lists that would drift the first time a provider reworded something. They are private, so the calls
 * go through reflection; if either ever disappears the classifier answers "don't switch", which is
 * the safe direction — see {@see self::self_check()}, which the test harness asserts on.
 *
 * The third bucket (auth) has no counterpart in ErrorFormatter — it never needed to tell an invalid
 * key from any other 4xx — so {@see self::is_auth()} is genuinely new rather than a duplicate.
 *
 * MONEY
 *
 * Switching spends an account the user did not choose for this request, so the rules are strict:
 * one switch per request and never a chain; only a provider that is listed in their order, has a key
 * saved, and whose key {@see ModelCatalog::status()} currently reports as active; and the failed
 * provider is parked for an hour so a 20-post bulk run does not pay for 20 doomed requests.
 *
 * Parking never writes `wpwand_model`. The model the user picked is still the model Settings shows,
 * and once the park expires that is what runs again.
 */
final class Failover
{
    /** Master switch. bool-ish, default off. */
    public const OPTION_ENABLED = 'wpwand_failover_enabled';

    /** Comma-separated provider ids, highest priority first. Empty = use the natural order. */
    public const OPTION_ORDER = 'wpwand_failover_order';

    /**
     * Providers the user unticked. Kept apart from the order on purpose: the order says where a
     * provider sits and this says whether to use it, so unticking one no longer erases where it was.
     */
    public const OPTION_SKIP = 'wpwand_failover_skip';

    /** array<provider-id, unix timestamp> — when each parked provider may be tried again. */
    public const OPTION_PARKED = 'wpwand_failover_parked';

    /** The last switch, for the dismissible admin notice. See {@see self::notice()}. */
    public const OPTION_NOTICE = 'wpwand_failover_notice';

    /** How long the "we finished somewhere else" notice stays worth reading. */
    private const NOTICE_TTL = 7 * DAY_IN_SECONDS;

    /**
     * How long a failed provider sits out.
     *
     * An hour is long enough that a bulk run or an overnight schedule stops paying the failed
     * provider for every item, and short enough that someone who tops up their balance over coffee
     * is back on their own account by the next post. Filter `wpwand_failover_park_seconds` to change
     * it. Nothing expires the park early on purpose: a key that was invalid a minute ago is still
     * invalid, and re-probing on every request is how you turn one wasted call into fifty.
     */
    public const PARK_SECONDS = HOUR_IN_SECONDS;

    /**
     * Providers this plugin can route to. Same set, same order, as Generator::PROVIDER_ORDER.
     *
     * The order is not decoration: an install that has never arranged a backup order gets this one
     * verbatim (see order()), so it decides where a switch sends the work — and therefore whose
     * account pays for it. Cheapest to get working comes first for that reason as much as for the
     * settings screen's.
     */
    private const PROVIDERS = ['gemini', 'openrouter', 'openai', 'claude', 'deepseek'];

    /**
     * Wordings that only ever appear when an account cannot pay.
     *
     * Every one is more than a single word on purpose. A bare 'quota' or 'credit' shows up in
     * argument names, content-policy refusals and the user's own prompt text, and each of those
     * would park a working provider and bill the run somewhere else.
     */
    private const BILLING_PHRASES = [
        'credit balance',
        'insufficient quota',
        'exceeded your current quota',
        'billing details',
        'billing hard limit',
        'out of funds',
        'top up',
        'top-up',
        'payment required',
        'add a payment method',
        'no credits remaining',
        'credits remaining',
        'purchase more credits',
        'quota exceeded for your plan',
        'plan and billing',
    ];

    /** Provider labels that mean the account genuinely cannot pay. These switch. */
    private const BILLING_MARKERS = [
        'insufficient_quota',
        'credit_balance_too_low',
        'billing_hard_limit_reached',
        'billing_not_active',
        'account_deactivated',
    ];

    /** Provider labels that are NOT about money, whatever words the message happens to contain. */
    private const NOT_BILLING_MARKERS = [
        'content_policy_violation',
        'context_length_exceeded',
        'string_above_max_length',
        'model_not_found',
        'rate_limit_exceeded',
        'rate_limit_error',
        'resource_exhausted',
        'overloaded_error',
        'server_error',
        'api_error',
    ];

    // -- entry points -----------------------------------------------------------------------

    /**
     * Has the site opted in, and is it entitled to this?
     *
     * Pro-only from 2.0.0. Switching to a second provider is a reliability feature for somebody
     * running more than one paid account — a free site has one key, so there is nothing to fall
     * back to and nothing is taken away. The licence check is here rather than only on the settings
     * screen because a site that turned this on while it was free would otherwise keep it forever.
     *
     * The half that stays free is the part that is a bug fix rather than a feature: when a
     * generation fails, the reason still reaches the user. Silence was the complaint.
     */
    public static function is_enabled(): bool
    {
        return !empty(get_option(self::OPTION_ENABLED, 0)) && \WPWand\Core\Pro::unlocked();
    }

    /**
     * Should failover touch this particular call?
     *
     * `wpwand_failover => false` in $args always wins, so a caller that must reach one specific
     * provider can say so. Absent that, a call that names a model is treated as "ask THIS provider":
     * the Settings "Test key" button is the one that does it, and answering it from a different
     * provider would report a dead key as working. Everything that generates for a user — the
     * assistant panel, bulk, automation, the classic editor — passes no model at all.
     *
     * @param array<string, mixed> $args The args handed to {@see Generator::generate()}.
     */
    public static function engages(array $args): bool
    {
        if (!self::is_enabled()) {
            return false;
        }
        if (array_key_exists('wpwand_failover', $args)) {
            return (bool) $args['wpwand_failover'];
        }

        return '' === trim((string) ($args['model'] ?? ''));
    }

    /**
     * Run one generation with failover around it.
     *
     * Generator owns the request; this owns the decision to make a second one. At most two attempts
     * leave here, and the second only ever goes to a provider that passed every check in
     * {@see self::next_provider()}.
     *
     * @param array<string, mixed> $args    Generation args, passed through untouched apart from `model`.
     * @param callable             $attempt fn(array $args): object — one trip to one provider.
     */
    public static function run(array $args, callable $attempt): object
    {
        $requested = (string) ($args['model'] ?? '');

        // The provider they picked is already parked from an earlier failure. Start on the stand-in
        // rather than paying for a request we know will fail.
        $backup = self::preflight($requested);
        if (!empty($backup)) {
            $args['model'] = $backup['model'];
            $response      = $attempt($args);
            self::park_if_terminal($response, $backup['to']);

            self::remember($backup);

            return self::tag($response, $backup);
        }

        $response = $attempt($args);

        $plan = self::plan($response, $requested);
        if (empty($plan)) {
            return $response;
        }

        if ('' === $plan['to']) {
            // Nowhere to go — their own error is still the right answer, now with a note saying why
            // no stand-in took over.
            self::remember($plan);

            return self::tag($response, $plan);
        }

        $retryArgs          = $args;
        $retryArgs['model'] = $plan['model'];
        $retry              = $attempt($retryArgs);

        if (isset($retry->error)) {
            // The stand-in failed too. Show the user the error from THEIR provider — that is the one
            // they can do something about — and park the stand-in if it failed for a lasting reason
            // so the next request skips it.
            self::park_if_terminal($retry, $plan['to']);
            $plan['fallback_failed'] = true;
            $plan['message']         = self::line_no_backup($plan['reason'], $plan['from']);
            self::remember($plan);

            return self::tag($response, $plan);
        }

        self::remember($plan);

        return self::tag($retry, $plan);
    }

    // -- classification ---------------------------------------------------------------------

    /**
     * Why did this request fail, in the only terms that matter here.
     *
     * @param mixed  $raw      The provider error as Generator threw it — a raw cURL string, or the
     *                         json-encoded provider error envelope.
     * @param string $provider Provider id, so ErrorFormatter can name whose billing it is.
     * @return string 'billing' | 'auth' | 'transport' | 'other' | 'unknown'
     */
    public static function classify($raw, string $provider = ''): string
    {
        $msg = self::extracted($raw);
        if ('' === $msg) {
            return 'unknown';
        }

        // Same order humanize() uses: transport is decided on the full sentence, billing on the
        // trimmed one, because that is the sentence the user is shown.
        if (self::is_transport($msg)) {
            return 'transport';
        }

        $trimmed = mb_strlen($msg) > 220 ? mb_substr($msg, 0, 217) . '…' : $msg;

        if (self::is_billing($raw, $trimmed, $provider)) {
            return 'billing';
        }
        if (self::is_auth($raw, $msg)) {
            return 'auth';
        }

        return 'other';
    }

    /** Only these two are worth someone else's money. */
    public static function switches_on(string $reason): bool
    {
        return 'billing' === $reason || 'auth' === $reason;
    }

    /**
     * Are ErrorFormatter's classifiers still reachable?
     *
     * Reflection into another class's privates is a real coupling, so it is asserted rather than
     * assumed: if this returns false, failover has quietly stopped switching and the fix is to make
     * those two methods public and call them directly.
     */
    public static function self_check(): bool
    {
        return null !== self::formatter('extract')
            && null !== self::formatter('transport_message')
            && null !== self::formatter('attribute_billing');
    }

    // -- the plan ---------------------------------------------------------------------------

    /**
     * Decide what (if anything) to do about a finished generation.
     *
     * @return array<string, mixed> Empty when the response is fine or the failure is transient.
     */
    private static function plan(object $response, string $requestedModel): array
    {
        if (!isset($response->error)) {
            return [];
        }

        $error = $response->error;

        // Pass the WHOLE envelope, not just the sentence. classify() has to tell a rate limit from
        // an empty account, and the only honest signals for that are error.code and error.type —
        // both of which are gone the moment the message is pulled out on its own. Normalised to an
        // array the way humanize() does, because ErrorFormatter::extract() reads arrays and strings,
        // not objects.
        $raw = json_decode((string) wp_json_encode($error), true);
        if (!is_array($raw)) {
            $raw = isset($error->message) ? (string) $error->message : '';
        }
        $type  = isset($error->type) ? (string) $error->type : '';
        $from  = self::provider_of($type, $requestedModel);

        if (!in_array($from, self::PROVIDERS, true)) {
            return []; // we cannot say who failed, so we cannot say who should take over
        }

        $reason = self::classify($raw, $from);
        if (!self::switches_on($reason)) {
            return [];
        }

        self::park($from);

        $to = self::next_provider($from);
        if ('' === $to) {
            return self::plan_shape($from, '', $reason, '', self::line_no_backup($reason, $from));
        }

        return self::plan_shape($from, $to, $reason, Generator::default_model($to), self::line_switched($reason, $from, $to));
    }

    /** @return array<string, mixed> */
    private static function plan_shape(string $from, string $to, string $reason, string $model, string $message): array
    {
        return [
            'from'       => $from,
            'from_label' => self::label($from),
            'to'         => $to,
            'to_label'   => '' === $to ? '' : self::label($to),
            'reason'     => $reason,
            'severity'   => 'auth' === $reason ? 'warning' : 'info',
            'model'      => $model,
            'message'    => $message,
            'until'      => self::parked_until($from),
            'time'       => time(),
        ];
    }

    /**
     * Nothing has failed yet this request, but the chosen provider is parked. Return the stand-in.
     *
     * @return array<string, mixed> Empty when the provider is fine, or when nobody can cover for it.
     */
    private static function preflight(string $requestedModel): array
    {
        $model = '' !== $requestedModel ? $requestedModel : (string) get_option('wpwand_model', '');
        if ('' === $model) {
            return [];
        }

        $from = ProviderFactory::forModel($model)->id();
        if (!self::is_parked($from)) {
            return [];
        }

        $to = self::next_provider($from);
        if ('' === $to) {
            return []; // let it fail honestly on their own provider
        }

        return self::plan_shape($from, $to, 'parked', Generator::default_model($to), self::line_parked($from, $to));
    }

    /**
     * The first provider in the user's order that can actually take the request.
     *
     * Every skip here is a way to spend money badly: a provider they removed from the order, one with
     * no key, one we already know is out of credit, or one they parked minutes ago.
     */
    private static function next_provider(string $exclude): string
    {
        foreach (self::order() as $provider) {
            if ($provider === $exclude || self::is_parked($provider)) {
                continue;
            }
            if (!ProviderFactory::forProvider($provider)->isConfigured()) {
                continue;
            }
            // ModelCatalog already knows — active / invalid / exceeded / unreachable / unset, cached
            // for 30 minutes. Anything short of 'active' is a request we should not pay for.
            $status = ModelCatalog::status($provider);
            if ('active' !== ($status['status'] ?? '')) {
                continue;
            }

            return $provider;
        }

        return '';
    }

    /**
     * The provider order, highest priority first.
     *
     * A saved order is taken literally — if the user dragged DeepSeek out of the list, DeepSeek does
     * not get their traffic, and we do not quietly append the rest. Only an install that has never
     * arranged an order falls back to the natural one.
     *
     * @return array<int, string>
     */
    public static function order(): array
    {
        $order = self::order_all();
        $skip  = self::skipped();

        if ($skip === []) {
            return $order;
        }

        return array_values(array_filter($order, static fn($p) => !in_array($p, $skip, true)));
    }

    /**
     * The same order with the unticked ones still in it, in the places the user put them.
     *
     * WHY BOTH EXIST. `order()` answers "who runs next", so it has to drop anything unticked.
     * The settings screen is asking a different question — "what does my list look like" — and
     * reading the runtime answer cost people their arrangement: an unticked provider vanished
     * from the payload, the screen appended it to the bottom as an unknown, and the next Save
     * wrote that bottom position over the place they had chosen. Untick DeepSeek from third,
     * save twice, and it is last for good. Order and inclusion are two facts and this is the
     * read side of keeping them apart.
     *
     * @return array<int, string>
     */
    public static function order_all(): array
    {
        $saved = get_option(self::OPTION_ORDER, '');
        $saved = is_array($saved) ? implode(',', $saved) : (string) $saved;

        $order = [];
        foreach (explode(',', $saved) as $provider) {
            $provider = strtolower(trim($provider));
            if (in_array($provider, self::PROVIDERS, true) && !in_array($provider, $order, true)) {
                $order[] = $provider;
            }
        }

        if ($order === []) {
            // Nothing valid survived. Only an install that has never arranged an order gets the
            // natural one — if the user DID save something and none of it parses, falling back to
            // "use them all" would send traffic to providers they did not choose. Fail closed: no
            // order, no backup.
            $order = trim($saved) === '' ? self::PROVIDERS : [];
        }

        return $order;
    }

    /**
     * Providers the user unticked. Read exactly like the order and whitelisted the same way, so a
     * corrupted value can only ever skip a real provider — never silently include one.
     *
     * @return array<int, string>
     */
    private static function skipped(): array
    {
        $saved = get_option(self::OPTION_SKIP, '');
        $saved = is_array($saved) ? implode(',', $saved) : (string) $saved;

        $skip = [];
        foreach (explode(',', $saved) as $provider) {
            $provider = strtolower(trim($provider));
            if (in_array($provider, self::PROVIDERS, true) && !in_array($provider, $skip, true)) {
                $skip[] = $provider;
            }
        }

        return $skip;
    }

    // -- parking ----------------------------------------------------------------------------

    /** @return array<string, int> provider id => unix timestamp it may be tried again */
    public static function parked(): array
    {
        $stored = get_option(self::OPTION_PARKED, []);
        if (!is_array($stored)) {
            return [];
        }

        $now  = time();
        $live = [];
        foreach ($stored as $provider => $until) {
            if (in_array($provider, self::PROVIDERS, true) && (int) $until > $now) {
                $live[$provider] = (int) $until;
            }
        }

        return $live;
    }

    public static function is_parked(string $provider): bool
    {
        $parked = self::parked();

        return isset($parked[$provider]);
    }

    /** Unix timestamp this provider comes back, or 0 when it is not parked. */
    public static function parked_until(string $provider): int
    {
        $parked = self::parked();

        return isset($parked[$provider]) ? $parked[$provider] : 0;
    }

    /** Let everything back in now — the "I've fixed it, stop waiting" button. */
    public static function unpark_all(): void
    {
        update_option(self::OPTION_PARKED, [], false);
    }

    private static function park(string $provider): void
    {
        $parked            = self::parked();
        $parked[$provider] = time() + self::park_seconds();
        update_option(self::OPTION_PARKED, $parked, false);
    }

    /** Park a provider that just failed for a reason waiting will not fix. */
    private static function park_if_terminal(object $response, string $provider): void
    {
        if (!isset($response->error) || !in_array($provider, self::PROVIDERS, true)) {
            return;
        }
        $raw = isset($response->error->message) ? (string) $response->error->message : '';
        if (self::switches_on(self::classify($raw, $provider))) {
            self::park($provider);
        }
    }

    private static function park_seconds(): int
    {
        /**
         * Filter how long a failed provider sits out before it is tried again.
         *
         * @param int $seconds Defaults to {@see self::PARK_SECONDS}.
         */
        return max(60, (int) apply_filters('wpwand_failover_park_seconds', self::PARK_SECONDS));
    }

    // -- what the user is told --------------------------------------------------------------

    /**
     * The last switch, for the dismissible admin notice. Empty when there is nothing to say.
     *
     * @return array<string, mixed>
     */
    public static function notice(): array
    {
        $notice = get_option(self::OPTION_NOTICE, []);

        if (!is_array($notice) || $notice === []) {
            return [];
        }

        // "Claude is out of credit" was still on screen weeks after a top-up, because nothing ever
        // took it down. It describes one generation at one moment, so it expires: after a week it
        // is history, not news, and the parked list below it is the live fact either way.
        $age = time() - (int) ($notice['time'] ?? 0);
        if ($age > self::NOTICE_TTL) {
            delete_option(self::OPTION_NOTICE);
            return [];
        }

        return $notice;
    }

    /** The user closed the notice. */
    public static function dismiss_notice(): void
    {
        delete_option(self::OPTION_NOTICE);
    }

    /** @param array<string, mixed> $plan */
    private static function remember(array $plan): void
    {
        update_option(self::OPTION_NOTICE, $plan, false);
    }

    /**
     * Attach the short line the result area shows.
     *
     * @param array<string, mixed> $plan
     */
    private static function tag(object $response, array $plan): object
    {
        $response->wpwand_failover = [
            'from'     => $plan['from'],
            'to'       => $plan['to'],
            'reason'   => $plan['reason'],
            'severity' => $plan['severity'],
            'message'  => $plan['message'],
        ];

        return $response;
    }

    private static function line_switched(string $reason, string $from, string $to): string
    {
        if ('auth' === $reason) {
            return sprintf(
                /* translators: 1: provider that failed, e.g. OpenAI. 2: provider used instead, e.g. Claude. */
                __("Your %1\$s key isn't working, so this was written with %2\$s instead. Check the key in Settings.", 'ai-content-generation'),
                self::label($from),
                self::label($to)
            );
        }

        return sprintf(
            /* translators: 1: provider that ran out of credit, e.g. OpenAI. 2: provider used instead, e.g. Claude. */
            __('%1$s is out of credit, so this was written with %2$s instead.', 'ai-content-generation'),
            self::label($from),
            self::label($to)
        );
    }

    private static function line_no_backup(string $reason, string $from): string
    {
        if ('auth' === $reason) {
            return sprintf(
                /* translators: %s: provider name, e.g. OpenAI */
                __("Your %s key isn't working and no backup provider was ready to take over. Check the key in Settings.", 'ai-content-generation'),
                self::label($from)
            );
        }

        return sprintf(
            /* translators: %s: provider name, e.g. OpenAI */
            __('%s is out of credit and no backup provider was ready to take over. Add credit, or set up a backup in Settings.', 'ai-content-generation'),
            self::label($from)
        );
    }

    private static function line_parked(string $from, string $to): string
    {
        return sprintf(
            /* translators: 1: provider being rested, e.g. OpenAI. 2: provider used instead, e.g. Claude. */
            __('%1$s is paused for now, so this was written with %2$s.', 'ai-content-generation'),
            self::label($from),
            self::label($to)
        );
    }

    private static function label(string $provider): string
    {
        return '' === $provider ? '' : ProviderFactory::forProvider($provider)->label();
    }

    // -- plumbing ---------------------------------------------------------------------------

    /**
     * Who failed. Generator stamps the error type as "<provider>_error", which is the reliable
     * signal; the model is the fallback for anything that predates that.
     */
    private static function provider_of(string $type, string $model): string
    {
        if ('' !== $type && '_error' === substr($type, -6)) {
            $id = substr($type, 0, -6);
            if (in_array($id, self::PROVIDERS, true)) {
                return $id;
            }
        }

        $model = '' !== $model ? $model : (string) get_option('wpwand_model', '');

        return '' === $model ? '' : ProviderFactory::forModel($model)->id();
    }

    /** @param mixed $raw */
    private static function extracted($raw): string
    {
        $extract = self::formatter('extract');
        if (null === $extract) {
            return '';
        }

        return trim(wp_strip_all_tags((string) $extract->invoke(null, $raw)));
    }

    /** cURL said no — the request never reached anybody, so nobody's balance is the problem. */
    private static function is_transport(string $msg): bool
    {
        $transport = self::formatter('transport_message');
        if (null === $transport) {
            return true; // can't tell → treat as transient → don't switch
        }

        return '' !== (string) $transport->invoke(null, $msg, '');
    }

    /**
     * ErrorFormatter rewrites a billing failure to name the provider; if the sentence came back
     * changed, it decided this was billing. That is the whole test — the needle list stays in one
     * place, where the user-facing copy already depends on it.
     *
     * @param mixed $raw
     */
    private static function is_billing($raw, string $msg, string $provider): bool
    {
        // Veto first. ErrorFormatter's needle list was written to WORD a sentence, where a false
        // positive costs nothing. Here it authorises spending a second account, so it needs
        // corroboration before it is believed.
        $marker = self::error_marker($raw);

        // An explicit billing marker settles it either way, whatever the prose says.
        if (in_array($marker, self::BILLING_MARKERS, true)) {
            return true;
        }
        if ($marker !== '' && in_array($marker, self::NOT_BILLING_MARKERS, true)) {
            return false;
        }

        $code = self::code_of($raw);

        // 402 is the one status that means "pay us" and nothing else.
        if (402 === $code) {
            return true;
        }

        // A 429 is rate limiting: retryable on the SAME provider, which is the case the rules
        // reserve for staying put. Gemini's free tier answers 429 with "Quota exceeded for quota
        // metric …", and OpenRouter's says "Add 10 credits to unlock" — both trip the needle list
        // and would park a working provider for an hour, billing the rest of the run elsewhere.
        if (429 === $code) {
            return false;
        }

        // Nothing decisive in the envelope. Fall back to the words — but require a PHRASE, not a
        // bare one. ErrorFormatter matches 'quota' and 'credit' on their own, which is right for
        // wording a sentence and wrong for authorising spend: "Unrecognized request argument
        // supplied: quota" is not a bill. Anthropic words its real one as "credit balance is too
        // low" under the generic invalid_request_error type, so the phrases have to carry it.
        $haystack = strtolower($msg . ' ' . self::raw_text($raw));

        foreach (self::BILLING_PHRASES as $phrase) {
            if (strpos($haystack, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * The machine-readable label a provider put on its own error: error.code first, then error.type.
     *
     * Prose lies about what an error is; this does not. OpenAI sends
     * code 'insufficient_quota' or 'content_policy_violation', Anthropic sends type
     * 'invalid_request_error'. Numeric codes are ignored here — {@see self::code_of()} reads those.
     *
     * @param mixed $raw
     */
    private static function error_marker($raw): string
    {
        $error = self::innermost($raw);

        foreach (['code', 'type'] as $field) {
            if (isset($error[$field]) && is_string($error[$field]) && $error[$field] !== '') {
                return strtolower($error[$field]);
            }
        }

        return '';
    }

    /**
     * Is this a key problem?
     *
     * ErrorFormatter has no opinion here, so this is ours. Kept to specific phrases rather than bare
     * words: "invalid" on its own is how Anthropic labels the type of its out-of-credit error, and
     * charging a switch to the wrong bucket is how "your key is broken" gets shown to somebody whose
     * key is fine.
     *
     * Runs after the billing check for the same reason.
     *
     * @param mixed $raw
     */
    private static function is_auth($raw, string $msg): bool
    {
        $haystack = strtolower($msg . ' ' . self::raw_text($raw));

        $phrases = [
            'invalid_api_key',
            'invalid api key',
            'incorrect api key',
            'invalid x-api-key',
            'api key is invalid',
            'api key not valid',
            'authentication_error',
            'authentication fails',
            'authentication failed',
            'invalid authentication',
            'no auth credentials',
            'permission_error',
            'unauthorized',
            'invalid_token',
        ];
        foreach ($phrases as $phrase) {
            if (strpos($haystack, $phrase) !== false) {
                return true;
            }
        }

        // "expired" is only ours when it is the credential that expired — a provider saying a
        // *request* expired is a transient thing to retry.
        if (strpos($haystack, 'expired') !== false) {
            foreach (['key', 'token', 'credential'] as $near) {
                if (strpos($haystack, $near) !== false) {
                    return true;
                }
            }
        }

        $code = self::code_of($raw);

        return 401 === $code || 403 === $code;
    }

    /**
     * The numeric status a provider put inside its own error envelope, when it did.
     *
     * OpenRouter's 401 body is just {"error":{"message":"User not found.","code":401}} — no phrase in
     * it says "key", so the code is the only signal. OpenAI puts a string there instead
     * ("invalid_api_key"), which the phrase list above already covers.
     *
     * @param mixed $raw
     */
    private static function code_of($raw): int
    {
        $error = self::innermost($raw);
        // `wpwand_http` is the HTTP status Generator keeps on the error since 2026-10-04 — the only
        // place DeepSeek's 402 is recorded, since its body says "Insufficient Balance" and names no
        // number.
        foreach (['code', 'status', 'wpwand_http'] as $field) {
            if (isset($error[$field]) && is_numeric($error[$field])) {
                $code = (int) $error[$field];
                if ($code >= 100 && $code <= 599) {
                    return $code;
                }
            }
        }

        return 0;
    }

    /** @param mixed $raw */
    /**
     * The provider's own error, whichever layer it is in.
     *
     * Generator hands a failure on as `{message: "<the provider's error, as JSON>", type:
     * "<provider>_error", code: 500}`. Read at that layer every failure is a 500 with no marker, so
     * the 429 veto in is_billing() never fired and the words "exceeded your current quota … plan
     * and billing details" decided it — measured 2026-10-04 with the two refusals Gemini sent on
     * the 2nd: `other` as the provider wrote them, `billing` as Generator passed them on. So the
     * layers are peeled: an `error` envelope (Google's comes as a one-item array), then a `message`
     * that is itself JSON, until one is reached that carries no JSON inside it.
     *
     * @param mixed $raw
     * @return array<string, mixed> Empty when there is no error to read.
     */
    private static function innermost($raw): array
    {
        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (is_object($data)) {
            $data = json_decode((string) wp_json_encode($data), true);
        }
        if (!is_array($data)) {
            return [];
        }

        if (isset($data['error']) && is_array($data['error'])) {
            $data = $data['error'];
        } elseif (isset($data[0]['error']) && is_array($data[0]['error'])) {
            $data = $data[0]['error'];
        }

        if (isset($data['message']) && is_string($data['message'])) {
            $text = trim($data['message']);
            if ($text !== '' && ($text[0] === '{' || $text[0] === '[')) {
                $inner = self::innermost($text);
                if (!empty($inner)) {
                    return $inner;
                }
            }
        }

        return $data;
    }

    private static function raw_text($raw): string
    {
        return is_string($raw) ? $raw : (string) wp_json_encode($raw);
    }

    /**
     * One of ErrorFormatter's private classifiers, or null when it is no longer there.
     *
     * Cached per request — reflection is cheap but this runs on a path that is already slow, and
     * there is no reason to pay for it twice.
     */
    private static function formatter(string $method): ?\ReflectionMethod
    {
        static $cache = [];

        if (array_key_exists($method, $cache)) {
            return $cache[$method];
        }

        $cache[$method] = null;
        try {
            if (method_exists(ErrorFormatter::class, $method)) {
                $reflected = new \ReflectionMethod(ErrorFormatter::class, $method);
                // Reflection reaches private methods on its own from 8.1, and calling this on 8.5
                // raises a deprecation notice — which on a site with WP_DEBUG_DISPLAY on would print
                // into the middle of a generation response. Only the old floor still needs it.
                if (PHP_VERSION_ID < 80100) {
                    $reflected->setAccessible(true);
                }
                $cache[$method] = $reflected;
            }
        } catch (\ReflectionException $e) {
            $cache[$method] = null;
        }

        return $cache[$method];
    }
}
