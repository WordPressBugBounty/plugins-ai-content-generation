<?php

namespace WPWand\Modules;

/**
 * Small base for modules: helpers to register REST controllers and admin-only callbacks.
 */
abstract class AbstractModule implements Module
{
    public function requires_pro(): bool
    {
        return false;
    }

    /**
     * Register a module's REST controllers on rest_api_init.
     *
     * @param array<class-string> $controllers
     */
    protected function rest(array $controllers): void
    {
        add_action('rest_api_init', static function () use ($controllers) {
            foreach ($controllers as $controller) {
                (new $controller())->register_routes();
            }
        });
    }

    /** Run a callback only in the admin context (where the React screens live). */
    protected function admin(callable $fn): void
    {
        if (is_admin()) {
            $fn();
        }
    }
}
