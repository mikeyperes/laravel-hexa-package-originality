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

        // Settings card on /settings page
        $this->registerSettingsCard();

        // Sidebar link
        if (class_exists(\hexa_core\Services\PackageRegistryService::class)) {
            $registry = app(\hexa_core\Services\PackageRegistryService::class);
            // HWS-SIDEBAR-MENU-3L-BEGIN
            $registry->registerDomainGroup('Discovery', 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z', 20);
            $registry->registerSectionGroup('Content', 'Discovery', '', 20);
            // HWS-SIDEBAR-MENU-3L-END

            $registry->registerSidebarLink('originality.settings', 'Originality.ai', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'Content', 'originality', 80);
            $registry->registerPackage('originality', 'hexawebsystems/laravel-hexa-package-originality', [
                'title' => 'Originality.ai',
                'description' => 'Originality.ai settings for plagiarism and AI-detection verification flows.',
                'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                'color' => 'rose',
                'settingsRoute' => 'originality.settings',
            ]);
        }
    }

    /**
     * Register settings card on the core settings page.
     *
     * @return void
     */
    private function registerSettingsCard(): void
    {
        view()->composer('settings.index', function ($view) {
            $view->getFactory()->startPush('settings-cards', view('originality::partials.settings-card')->render());
        });
    }
}
