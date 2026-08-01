<?php

namespace WPWand\Modules;

/**
 * Registry that boots feature {@see Module}s, honouring per-release on/off and the Pro gate.
 *
 * Free modules are added by the core bootstrap; the Pro plugin adds its own via the
 * `wpwand_register_modules` action. A module is skipped when it is listed in the
 * `wpwand_disabled_modules` option/filter (so a release can ship without, say, Automation), or when
 * it needs Pro and Pro isn't active.
 */
final class ModuleManager
{
    /** @var array<string, Module> */
    private array $modules = [];

    public function add(Module $module): self
    {
        $this->modules[$module->id()] = $module;
        return $this;
    }

    public function has(string $id): bool
    {
        return isset($this->modules[$id]);
    }

    /** @return array<string, Module> */
    public function all(): array
    {
        return $this->modules;
    }

    public function boot(): void
    {
        $disabled  = self::disabled();
        $unlocked  = \WPWand\Core\Pro::unlocked(); // plugin present AND license active

        foreach ($this->modules as $module) {
            if (in_array($module->id(), $disabled, true)) {
                continue; // turned off for this release / by the site owner
            }
            // Pro feature modules boot only when the license is active, so deactivating re-locks
            // them (matches legacy, which require'd the Pro feature code only while licensed). The
            // License module itself sets requires_pro()=false so it stays reachable to (re)activate.
            if ($module->requires_pro() && !$unlocked) {
                continue;
            }
            $module->boot();
        }
    }

    /**
     * Module ids that are switched off. Set via the `wpwand_disabled_modules` option, or hard-coded
     * for a release via the matching filter.
     *
     * @return string[]
     */
    public static function disabled(): array
    {
        $list = (array) get_option('wpwand_disabled_modules', []);
        return array_values(array_filter((array) apply_filters('wpwand_disabled_modules', $list)));
    }
}
