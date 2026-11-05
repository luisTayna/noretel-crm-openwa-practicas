<?php

namespace Modules\Customer\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Entities\User;
use Modules\Customer\Console\ExportHistoricalCustomerTemplateCommand;
use Modules\Customer\Console\ImportBaseCustomersCommand;

class CustomerServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'Customer';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'customer';

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
        $this->registerCommands();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
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
            module_path($this->moduleName, 'Config/config.php'),
            $this->moduleNameLower
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
     * Register gates for the Customer module.
     *
     * @return void
     */
    protected function registerGates()
    {
        // Gates para gestionar clientes
        Gate::define('manage-customers', function (User $user) {
            return $user->hasPermission('manage', 'customers');
        });

        Gate::define('view-customers', function (User $user) {
            return $user->canViewModule('customers');
        });

        Gate::define('create-customers', function (User $user) {
            return $user->canCreateInModule('customers');
        });

        Gate::define('edit-customers', function (User $user) {
            return $user->canEditInModule('customers');
        });

        Gate::define('delete-customers', function (User $user) {
            return $user->canDeleteInModule('customers');
        });

        // Gates para gestionar documentos
        Gate::define('view-documents', function (User $user) {
            return $user->canViewModule('customers');
        });

        Gate::define('upload-documents', function (User $user) {
            return $user->canCreateInModule('customers');
        });

        Gate::define('edit-documents', function (User $user) {
            return $user->canEditInModule('customers');
        });

        Gate::define('delete-documents', function (User $user) {
            return $user->canDeleteInModule('customers');
        });

        // Gates para gestionar interacciones
        Gate::define('view-interactions', function (User $user) {
            return $user->canViewModule('customers');
        });

        Gate::define('create-interactions', function (User $user) {
            return $user->canCreateInModule('customers');
        });

        Gate::define('edit-interactions', function (User $user) {
            return $user->canEditInModule('customers');
        });

        Gate::define('delete-interactions', function (User $user) {
            return $user->canDeleteInModule('customers');
        });

        // Gates para gestionar leads
        Gate::define('view-leads', function (User $user) {
            return $user->canViewModule('customers');
        });

        Gate::define('create-leads', function (User $user) {
            return $user->canCreateInModule('customers');
        });

        Gate::define('edit-leads', function (User $user) {
            return $user->canEditInModule('customers');
        });

        Gate::define('delete-leads', function (User $user) {
            return $user->canDeleteInModule('customers');
        });

        Gate::define('convert-leads', function (User $user) {
            return $user->canEditInModule('customers');
        });
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportBaseCustomersCommand::class,
                ExportHistoricalCustomerTemplateCommand::class,
            ]);
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
