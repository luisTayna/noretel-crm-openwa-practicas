<?php

namespace Modules\Core\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\Core\Events\UserRegistered;
use Modules\Core\Events\UserLoggedIn;
use Modules\Core\Events\UserLoggedOut;
use Modules\Core\Events\PasswordReset;
use Modules\Core\Events\SecurityPolicyChanged;
use Modules\Core\Events\SystemConfigurationChanged;
use Modules\Core\Events\RoleAssigned;
use Modules\Core\Events\PermissionGranted;
use Modules\Core\Events\WorkflowStateChanged;
use Modules\Core\Listeners\SendWelcomeNotification;
use Modules\Core\Listeners\LogUserActivity;
use Modules\Core\Listeners\NotifyAdminOnPasswordReset;
use Modules\Core\Listeners\ClearSecurityCache;
use Modules\Core\Listeners\ClearConfigCache;
use Modules\Core\Listeners\UpdateUserPermissionsCache;
use Modules\Core\Listeners\NotifyOnWorkflowChange;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        UserRegistered::class => [
            SendWelcomeNotification::class,
            LogUserActivity::class,
        ],
        UserLoggedIn::class => [
            LogUserActivity::class,
        ],
        UserLoggedOut::class => [
            LogUserActivity::class,
        ],
        PasswordReset::class => [
            LogUserActivity::class,
            NotifyAdminOnPasswordReset::class,
        ],
        SecurityPolicyChanged::class => [
            ClearSecurityCache::class,
            LogUserActivity::class,
        ],
        SystemConfigurationChanged::class => [
            ClearConfigCache::class,
            LogUserActivity::class,
        ],
        RoleAssigned::class => [
            UpdateUserPermissionsCache::class,
            LogUserActivity::class,
        ],
        PermissionGranted::class => [
            UpdateUserPermissionsCache::class,
            LogUserActivity::class,
        ],
        WorkflowStateChanged::class => [
            LogUserActivity::class,
            NotifyOnWorkflowChange::class,
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