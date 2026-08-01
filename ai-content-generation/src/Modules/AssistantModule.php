<?php

namespace WPWand\Modules;

use WPWand\Admin\AdminBarTrigger;
use WPWand\Admin\AssistantInjector;
use WPWand\Admin\ClassicEditor;
use WPWand\Admin\ElementorIntegration;
use WPWand\Admin\GutenbergIntegration;
use WPWand\Rest\Controllers\EditorController;
use WPWand\Rest\Controllers\GenerateController;
use WPWand\Rest\Controllers\ImageController;
use WPWand\Rest\Controllers\PingController;
use WPWand\Rest\Controllers\StreamController;
use WPWand\Rest\Controllers\TemplatesController;

/**
 * The AI Assistant: floating panel + editor integrations (Gutenberg/Classic/Elementor) and the
 * generation/template/image REST endpoints. The free core content generator.
 */
final class AssistantModule extends AbstractModule
{
    public function id(): string
    {
        return 'assistant';
    }

    public function label(): string
    {
        return 'AI Assistant';
    }

    public function boot(): void
    {
        $this->admin(static function () {
            (new AssistantInjector())->register();
            (new AdminBarTrigger())->register();
            (new GutenbergIntegration())->register();
            (new ClassicEditor())->register();
            (new ElementorIntegration())->register();
        });

        $this->rest([
            PingController::class,
            TemplatesController::class,
            GenerateController::class,
            StreamController::class,
            ImageController::class,
            EditorController::class,
        ]);
    }
}
