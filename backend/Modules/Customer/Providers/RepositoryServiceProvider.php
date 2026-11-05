<?php

namespace Modules\Customer\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Register repositories
        $this->app->bind(
            \Modules\Customer\Interfaces\CustomerRepositoryInterface::class,
            \Modules\Customer\Repositories\CustomerRepository::class
        );

        $this->app->bind(
            \Modules\Customer\Interfaces\AddressRepositoryInterface::class,
            \Modules\Customer\Repositories\AddressRepository::class
        );

        $this->app->bind(
            \Modules\Customer\Interfaces\DocumentRepositoryInterface::class,
            \Modules\Customer\Repositories\DocumentRepository::class
        );

        // $this->app->bind(
        //     \Modules\Customer\Interfaces\DocumentTypeRepositoryInterface::class,
        //     \Modules\Customer\Repositories\DocumentTypeRepository::class
        // );

        $this->app->bind(
            \Modules\Customer\Interfaces\InteractionRepositoryInterface::class,
            \Modules\Customer\Repositories\InteractionRepository::class
        );

        $this->app->bind(
            \Modules\Customer\Interfaces\LeadRepositoryInterface::class,
            \Modules\Customer\Repositories\LeadRepository::class
        );
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
}