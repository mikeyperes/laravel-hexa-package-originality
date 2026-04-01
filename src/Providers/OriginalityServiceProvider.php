<?php

namespace hexa_package_originality\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * OriginalityServiceProvider — registers config, routes, views, settings card, sidebar.
 */
class OriginalityServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/originality.php', 'originality');
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/originality.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'originality');

        // Settings card
        view()->composer('*', function ($view) {
            $view->getFactory()->startPush('settings-cards', view('originality::partials.settings-card')->render());
        });

        // Sidebar link
        if (class_exists(\hexa_core\Services\PackageRegistryService::class)) {
            $registry = app(\hexa_core\Services\PackageRegistryService::class);
            $registry->registerSidebarLink('originality.settings', 'Originality.ai ', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'Content', 'originality', 80);
        }
    }
}
