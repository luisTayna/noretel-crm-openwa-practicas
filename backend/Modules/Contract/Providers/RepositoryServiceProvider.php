<?php

namespace Modules\Contract\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Contract\Repositories\ContractRepository;
use Modules\Contract\Repositories\InstallationRepository;
use Modules\Contract\Repositories\RouteRepository;
use Modules\Contract\Repositories\SLARepository;
use Modules\Contract\Entities\Contract;
use Modules\Contract\Entities\Installation;
use Modules\Contract\Entities\Route;
use Modules\Contract\Entities\SLA;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Registrar repositorios
        $this->app->bind(ContractRepository::class, function ($app) {
            return new ContractRepository(new Contract());
        });

        $this->app->bind(InstallationRepository::class, function ($app) {
            return new InstallationRepository(new Installation());
        });

        $this->app->bind(RouteRepository::class, function ($app) {
            return new RouteRepository(new Route());
        });

        $this->app->bind(SLARepository::class, function ($app) {
            return new SLARepository(new SLA());
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            ContractRepository::class,
            InstallationRepository::class,
            RouteRepository::class,
            SLARepository::class,
        ];
    }
}