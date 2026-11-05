<?php

namespace Modules\Contract\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Entities\User;

class ContractServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'Contract';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'contract';

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
        $this->registerFactories();
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
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RepositoryServiceProvider::class);
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
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
        }
    }

    /**
     * Register an additional directory of factories.
     *
     * @return void
     */
    public function registerFactories()
    {
        if (! app()->environment('production') && $this->app->runningInConsole()) {
            $this->loadFactoriesFrom(module_path($this->moduleName, 'Database/factories'));
        }
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
    
    /**
     * Register gates for the Contract module.
     *
     * @return void
     */
    protected function registerGates()
    {
        // Gates para gestionar contratos
        Gate::define('manage-contracts', function (User $user) {
            return $user->hasPermission('manage', 'contracts');
        });

        Gate::define('view-contracts', function (User $user) {
            return $user->canViewModule('contracts');
        });

        Gate::define('create-contracts', function (User $user) {
            return $user->canCreateInModule('contracts');
        });

        Gate::define('edit-contracts', function (User $user) {
            return $user->canEditInModule('contracts');
        });

        Gate::define('delete-contracts', function (User $user) {
            return $user->canDeleteInModule('contracts');
        });

        // Gates para gestionar instalaciones
        Gate::define('manage-installations', function (User $user) {
            return $user->hasPermission('manage', 'installations');
        });

        Gate::define('view-installations', function (User $user) {
            return $user->canViewModule('installations');
        });

        Gate::define('create-installations', function (User $user) {
            return $user->canCreateInModule('installations');
        });

        Gate::define('edit-installations', function (User $user) {
            return $user->canEditInModule('installations');
        });

        Gate::define('delete-installations', function (User $user) {
            return $user->canDeleteInModule('installations');
        });

        // Gates para gestionar rutas de instalación
        Gate::define('manage-routes', function (User $user) {
            return $user->hasPermission('manage', 'routes');
        });

        Gate::define('view-routes', function (User $user) {
            return $user->canViewModule('routes');
        });

        Gate::define('create-routes', function (User $user) {
            return $user->canCreateInModule('routes');
        });

        Gate::define('edit-routes', function (User $user) {
            return $user->canEditInModule('routes');
        });

        Gate::define('delete-routes', function (User $user) {
            return $user->canDeleteInModule('routes');
        });

        // Gates para gestionar SLAs
        Gate::define('manage-slas', function (User $user) {
            return $user->hasPermission('manage', 'slas');
        });

        Gate::define('view-slas', function (User $user) {
            return $user->canViewModule('slas');
        });

        Gate::define('create-slas', function (User $user) {
            return $user->canCreateInModule('slas');
        });

        Gate::define('edit-slas', function (User $user) {
            return $user->canEditInModule('slas');
        });

        Gate::define('delete-slas', function (User $user) {
            return $user->canDeleteInModule('slas');
        });
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