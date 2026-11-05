<?php

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Entities\User;
use Modules\Core\Http\Middleware\CheckPermission;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'Core';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'core';

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
        $this->registerMiddlewares();
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
     * Register gates for the Core module.
     *
     * @return void
     */
    protected function registerGates()
    {
        // Gates para gestionar usuarios
        Gate::define('manage-users', function (User $user) {
            return $user->hasPermission('manage', 'users');
        });

        Gate::define('view-users', function (User $user) {
            return $user->canViewModule('users');
        });

        Gate::define('create-users', function (User $user) {
            return $user->canCreateInModule('users');
        });

        Gate::define('edit-users', function (User $user) {
            return $user->canEditInModule('users');
        });

        Gate::define('delete-users', function (User $user) {
            return $user->canDeleteInModule('users');
        });

        // Gates para gestionar roles
        Gate::define('manage-roles', function (User $user) {
            return $user->hasPermission('manage', 'roles');
        });

        Gate::define('view-roles', function (User $user) {
            return $user->canViewModule('roles');
        });

        Gate::define('create-roles', function (User $user) {
            return $user->canCreateInModule('roles');
        });

        Gate::define('edit-roles', function (User $user) {
            return $user->canEditInModule('roles');
        });

        Gate::define('delete-roles', function (User $user) {
            return $user->canDeleteInModule('roles');
        });

        // Gates para gestionar configuración
        Gate::define('manage-config', function (User $user) {
            return $user->hasPermission('manage', 'configuration');
        });

        Gate::define('view-config', function (User $user) {
            return $user->canViewModule('configuration');
        });

        Gate::define('edit-config', function (User $user) {
            return $user->canEditInModule('configuration');
        });

        // Gates para gestionar notificaciones
        Gate::define('manage-notifications', function (User $user) {
            return $user->hasPermission('manage', 'notifications');
        });

        Gate::define('create-notifications', function (User $user) {
            return $user->canCreateInModule('notifications');
        });

        Gate::define('view-notifications', function (User $user) {
            return $user->canViewModule('notifications');
        });

        // Gates para gestionar workflows
        Gate::define('manage-workflows', function (User $user) {
            return $user->hasPermission('manage', 'workflows');
        });

        Gate::define('view-workflows', function (User $user) {
            return $user->canViewModule('workflows');
        });

        Gate::define('create-workflows', function (User $user) {
            return $user->canCreateInModule('workflows');
        });

        Gate::define('edit-workflows', function (User $user) {
            return $user->canEditInModule('workflows');
        });

        Gate::define('delete-workflows', function (User $user) {
            return $user->canDeleteInModule('workflows');
        });

        // Gates para gestionar seguridad
        Gate::define('manage-security', function (User $user) {
            return $user->hasPermission('manage', 'security');
        });

        Gate::define('create-security', function (User $user) {
            return $user->canCreateInModule('security');
        });

        Gate::define('view-security', function (User $user) {
            return $user->canViewModule('security');
        });

        Gate::define('edit-security', function (User $user) {
            return $user->canEditInModule('security');
        });
        Gate::define('delete-security', function (User $user) {
            return $user->canDeleteInModule('security');
        });
    }

    /**
     * Register middlewares for the Core module.
     *
     * @return void
     */
    protected function registerMiddlewares()
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('permission', CheckPermission::class);
        $router->aliasMiddleware('role', \Spatie\Permission\Middleware\RoleMiddleware::class);
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
