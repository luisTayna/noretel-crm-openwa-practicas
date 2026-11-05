<?php

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Entities\User;
use Modules\Core\Entities\SystemConfiguration;
use Modules\Core\Entities\Workflow;
use Modules\Core\Entities\WorkflowState;
use Modules\Core\Entities\WorkflowTransition;
use Modules\Core\Entities\Notification;
use Modules\Core\Entities\NotificationTemplate;
use Modules\Core\Entities\SecurityPolicy;
use Modules\Core\Entities\AuditLog;
use Modules\Core\Repositories\UserRepository;
use Modules\Core\Repositories\RoleRepository;
use Modules\Core\Repositories\SystemConfigurationRepository;
use Modules\Core\Repositories\WorkflowRepository;
use Modules\Core\Repositories\NotificationRepository;
use Modules\Core\Repositories\SecurityPolicyRepository;
use Modules\Core\Repositories\AuditRepository;
use Spatie\Permission\Models\Role;

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
        $this->app->bind(UserRepository::class, function ($app) {
            return new UserRepository(new User());
        });

        $this->app->bind(RoleRepository::class, function ($app) {
            return new RoleRepository(new Role());
        });

        $this->app->bind(SystemConfigurationRepository::class, function ($app) {
            return new SystemConfigurationRepository(new SystemConfiguration());
        });

        $this->app->bind(WorkflowRepository::class, function ($app) {
            return new WorkflowRepository(
                new Workflow(),
                new WorkflowState(),
                new WorkflowTransition()
            );
        });

        $this->app->bind(NotificationRepository::class, function ($app) {
            return new NotificationRepository(
                new Notification(),
                new NotificationTemplate()
            );
        });

        $this->app->bind(SecurityPolicyRepository::class, function ($app) {
            return new SecurityPolicyRepository(new SecurityPolicy());
        });

        $this->app->bind(AuditRepository::class, function ($app) {
            return new AuditRepository(new AuditLog());
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
            UserRepository::class,
            RoleRepository::class,
            SystemConfigurationRepository::class,
            WorkflowRepository::class,
            NotificationRepository::class,
            SecurityPolicyRepository::class,
            AuditRepository::class,
        ];
    }
}
