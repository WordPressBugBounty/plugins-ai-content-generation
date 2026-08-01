<?php

namespace WPWand\Modules;

/**
 * A feature module — a self-contained unit (admin pages + REST controllers + integrations) that
 * can be enabled/disabled independently and can live in either the free or the Pro plugin.
 *
 * This is the seam that lets Pro features be shipped modularly: each is a Module with
 * requires_pro() = true, registered into the shared {@see ModuleManager}. The free plugin owns the
 * framework; the Pro plugin adds its modules via the `wpwand_register_modules` action. A module can
 * be turned off per release through the `wpwand_disabled_modules` option/filter, and moving a
 * feature between free and Pro is just moving its module + where it registers.
 */
interface Module
{
    /** Stable id, e.g. 'bulk', 'automation'. Used for enable/disable + dedupe. */
    public function id(): string;

    /** Human label (for diagnostics / a future module-manager screen). */
    public function label(): string;

    /** True when the module only works with the Pro plugin active. */
    public function requires_pro(): bool;

    /** Register all of this module's WordPress hooks (admin pages, rest_api_init, etc.). */
    public function boot(): void;
}
