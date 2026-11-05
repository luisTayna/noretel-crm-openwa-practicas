<?php

namespace Modules\Billing\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Console\AuditContractPeriodsCommand;
use Modules\Billing\Console\CreateTestYapeIntentCommand;
use Modules\Billing\Console\GenerateMonthlyInvoicesCommand;
use Modules\Billing\Console\GeneratePaymentRemindersCommand;
use Modules\Billing\Console\SendPaymentRemindersCommand;
use Modules\Core\Entities\User;

class BillingServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'Billing';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'billing';

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
     * Register gates for the Billing module.
     *
     * @return void
     */
    protected function registerGates()
    {
        // Gates para gestionar facturas
        Gate::define('manage-invoices', function (User $user) {
            return $user->hasPermission('manage', 'invoices');
        });

        Gate::define('view-invoices', function (User $user) {
            return $user->canViewModule('invoices');
        });

        Gate::define('create-invoices', function (User $user) {
            return $user->canCreateInModule('invoices');
        });

        Gate::define('edit-invoices', function (User $user) {
            return $user->canEditInModule('invoices');
        });

        Gate::define('delete-invoices', function (User $user) {
            return $user->canDeleteInModule('invoices');
        });

        // Gates para gestionar pagos
        Gate::define('manage-payments', function (User $user) {
            return $user->hasPermission('manage', 'payments');
        });

        Gate::define('view-payments', function (User $user) {
            return $user->canViewModule('payments');
        });

        Gate::define('create-payments', function (User $user) {
            return $user->canCreateInModule('payments');
        });

        Gate::define('edit-payments', function (User $user) {
            return $user->canEditInModule('payments');
        });

        Gate::define('delete-payments', function (User $user) {
            return $user->canDeleteInModule('payments');
        });

        // Gates para gestionar notas de crédito
        Gate::define('manage-credit-notes', function (User $user) {
            return $user->hasPermission('manage', 'credit_notes');
        });

        Gate::define('view-credit-notes', function (User $user) {
            return $user->canViewModule('credit_notes');
        });

        Gate::define('create-credit-notes', function (User $user) {
            return $user->canCreateInModule('credit_notes');
        });

        Gate::define('edit-credit-notes', function (User $user) {
            return $user->canEditInModule('credit_notes');
        });

        Gate::define('delete-credit-notes', function (User $user) {
            return $user->canDeleteInModule('credit_notes');
        });

        // Gates para reportes financieros
        Gate::define('manage-financial-reports', function (User $user) {
            return $user->hasPermission('manage', 'financial_reports');
        });

        Gate::define('view-financial-reports', function (User $user) {
            return $user->canViewModule('financial_reports');
        });
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditContractPeriodsCommand::class,
                CreateTestYapeIntentCommand::class,
                GenerateMonthlyInvoicesCommand::class,
                GeneratePaymentRemindersCommand::class,
                SendPaymentRemindersCommand::class,
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
