<?php

namespace Modules\Contract\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Contract\Events\ContractCreated;
use Modules\Contract\Events\ContractUpdated;
use Modules\Contract\Events\ContractCancelled;
use Modules\Contract\Events\ContractRenewed;
use Modules\Contract\Events\InstallationScheduled;
use Modules\Contract\Events\InstallationCompleted;
use Modules\Contract\Listeners\SendContractNotification;
use Modules\Contract\Listeners\UpdateCustomerStatus;
use Modules\Contract\Listeners\UpdateInventoryStock;
use Modules\Contract\Listeners\NotifyTechnician;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        ContractCreated::class => [
            SendContractNotification::class,
            UpdateCustomerStatus::class,
        ],
        ContractUpdated::class => [
            SendContractNotification::class,
        ],
        ContractCancelled::class => [
            SendContractNotification::class,
            UpdateCustomerStatus::class,
        ],
        ContractRenewed::class => [
            SendContractNotification::class,
        ],
        InstallationScheduled::class => [
            NotifyTechnician::class,
        ],
        InstallationCompleted::class => [
            UpdateInventoryStock::class,
            SendContractNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}