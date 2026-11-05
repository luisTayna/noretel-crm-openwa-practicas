<?php

namespace Modules\Services\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Entities\User;

class ServicesServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'Services';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'services';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->registerGates();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);

        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
            $this->loadJsonTranslationsFrom(module_path($this->moduleName, 'Resources/lang'));
        }
    }

    /**
     * Register gates for the Services module.
     *
     * @return void
     */
    public function registerGates()
    {
        // Gates para gestionar servicios
        Gate::define('manage-services', function ($user) {
            return $user->hasPermissionTo('manage','services');
        });
        Gate::define('services-view', function ($user) {
            return $user->canViewModule('services');
        });
        Gate::define('services-create', function ($user) {
            return $user->canCreateInModule('services');
        });
        Gate::define('services-update', function ($user) {
            return $user->canEditInModule('services');
        });
        Gate::define('services-delete', function ($user) {
            return $user->canDeleteInModule('services');
        });
        // Gates para gestionar Planes
        Gate::define('manage-plans', function ($user) {
            return $user->hasPermissionTo('manage','plans');
        });
        Gate::define('plans-view', function ($user) {
            return $user->canViewModule('plans');
        });
        Gate::define('plans-create', function ($user) {
            return $user->canCreateInModule('plans');
        });
        Gate::define('plans-update', function ($user) {
            return $user->canEditInModule('plans');
        });
        Gate::define('plans-delete', function ($user) {
            return $user->canDeleteInModule('plans');
        });
        // Gates para gestionar Promociones
        Gate::define('manage-promotions', function ($user) {
            return $user->hasPermissionTo('manage','promotions');
        });
        Gate::define('promotions-view', function ($user) {
            return $user->canViewModule('promotions');
        });
        Gate::define('promotions-create', function ($user) {
            return $user->canCreateInModule('promotions');
        });
        Gate::define('promotions-update', function ($user) {
            return $user->canEditInModule('promotions');
        });
        Gate::define('promotions-delete', function ($user) {
            return $user->canDeleteInModule('promotions');
        });
        // Gates para gestionar Servicios Adicionales
        Gate::define('manage-additional-services', function ($user) {
            return $user->hasPermissionTo('manage','additional-services');
        });
        Gate::define('additional-services-view', function ($user) {
            return $user->canViewModule('additional-services');
        });
        Gate::define('additional-services-create', function ($user) {
            return $user->canCreateInModule('additional-services');
        });
        Gate::define('additional-services-update', function ($user) {
            return $user->canEditInModule('additional-services');
        });
        Gate::define('additional-services-delete', function ($user) {
            return $user->canDeleteInModule('additional-services');
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (\Config::get('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }
        return $paths;
    }
}
