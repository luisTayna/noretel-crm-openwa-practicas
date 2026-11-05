<?php

namespace Modules\Billing\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Billing\Interfaces\InvoiceRepositoryInterface;
use Modules\Billing\Interfaces\PaymentRepositoryInterface;
use Modules\Billing\Interfaces\CreditNoteRepositoryInterface;
use Modules\Billing\Repositories\InvoiceRepository;
use Modules\Billing\Repositories\PaymentRepository;
use Modules\Billing\Repositories\CreditNoteRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(CreditNoteRepositoryInterface::class, CreditNoteRepository::class);
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
