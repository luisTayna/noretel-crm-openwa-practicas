<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\DashboardController;
use Modules\Core\Http\Controllers\RoleController;
use Modules\Core\Http\Controllers\UserController;
use Modules\Core\Http\Controllers\AuditController;
use Modules\Core\Http\Controllers\AuthController;
use Modules\Core\Http\Controllers\ConfigurationController;
use Modules\Core\Http\Controllers\SecurityPolicyController;
use Modules\Core\Http\Controllers\NotificationController;
use Modules\Core\Http\Controllers\WorkflowController;

/*
|--------------------------------------------------------------------------
| Web Routes - Core Module
|--------------------------------------------------------------------------
*/

// ==================== AUTENTICACIÓN ====================
Route::prefix('core/auth')->name('core.auth.')->group(function () {
    // Login
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'loginWeb'])->name('login.post');

    // Logout
    Route::post('/logout', [AuthController::class, 'logoutWeb'])
        ->middleware('auth')
        ->name('logout');

    // Forgot Password
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('forgot-password');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('forgot-password.post');

    // Reset Password
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('reset-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password.post');
});

// ==================== RUTAS PROTEGIDAS ====================
Route::prefix('core')->middleware(['auth'])->name('core.')->group(function () {

    // Dashboard exclusivo para Admin
    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    });

    Route::get('/', function () {
        $user = Auth::user();
        if ($user && $user->hasRole('admin')) {
            return redirect()->route('core.dashboard');
        } elseif ($user && $user->hasRole('technician')) {
            return redirect()->route('operations.dashboard');
        } else {
            return redirect()->route('customer.customers.index');
        }
    });

    // ==================== PERFIL Y CONTRASEÑA ====================
    Route::get('/change-password', [AuthController::class, 'showChangePasswordForm'])->name('change-password');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('change-password.post');
    Route::get('/profile', [AuthController::class, 'showProfile'])->name('profile');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');

    // ==================== USUARIOS ====================
    Route::prefix('users')->name('users.')->group(function () {
        Route::middleware('permission:users,view')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
        });

        Route::middleware('permission:users,create')->group(function () {
            Route::get('/create', [UserController::class, 'create'])->name('create');
            Route::post('/', [UserController::class, 'store'])->name('store');
        });

        Route::middleware('permission:users,view')->group(function () {
            Route::get('/{id}', [UserController::class, 'show'])->name('show');
        });

        Route::middleware('permission:users,edit')->group(function () {
            Route::get('/{id}/edit', [UserController::class, 'edit'])->name('edit');
            Route::put('/{id}', [UserController::class, 'update'])->name('update');
            Route::post('/{id}/activate', [UserController::class, 'activate'])->name('activate');
            Route::post('/{id}/deactivate', [UserController::class, 'deactivate'])->name('deactivate');
        });

        Route::middleware('permission:users,delete')->group(function () {
            Route::delete('/{id}', [UserController::class, 'destroy'])->name('destroy');
        });
    });

    // ==================== ROLES Y PERMISOS ====================
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::middleware('permission:roles,view')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->name('index');
        });

        Route::middleware('permission:roles,create')->group(function () {
            Route::get('/create', [RoleController::class, 'create'])->name('create');
            Route::post('/', [RoleController::class, 'store'])->name('store');
        });

        Route::middleware('permission:roles,view')->group(function () {
            Route::get('/{id}', [RoleController::class, 'show'])->whereNumber('id')->name('show');
        });

        Route::middleware('permission:roles,edit')->group(function () {
            Route::get('/{id}/edit', [RoleController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('/{id}', [RoleController::class, 'update'])->whereNumber('id')->name('update');
            Route::post('/{id}/sync-permissions', [RoleController::class, 'syncPermissions'])->whereNumber('id')->name('sync-permissions');
            Route::post('/assign', [RoleController::class, 'assignToUser'])->name('assign');
            Route::post('/remove', [RoleController::class, 'removeFromUser'])->name('remove');
        });

        Route::middleware('permission:roles,delete')->group(function () {
            Route::delete('/{id}', [RoleController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });
    });

    // ==================== CONFIGURACIONES ====================
    Route::prefix('config')->name('config.')->group(function () {
        Route::middleware('permission:configuration,view')->group(function () {
            Route::get('/', [ConfigurationController::class, 'index'])->name('index');
            Route::get('/{id}', [ConfigurationController::class, 'show'])->name('show');
        });

        Route::middleware('permission:configuration,edit')->group(function () {
            Route::get('/create', [ConfigurationController::class, 'create'])->name('create');
            Route::post('/', [ConfigurationController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [ConfigurationController::class, 'edit'])->name('edit');
            Route::put('/{id}', [ConfigurationController::class, 'update'])->name('update');
            Route::post('/{id}/reset', [ConfigurationController::class, 'reset'])->name('reset');
            Route::post('/import', [ConfigurationController::class, 'import'])->name('import');
            Route::get('/export', [ConfigurationController::class, 'export'])->name('export');
        });

        Route::middleware('permission:configuration,delete')->group(function () {
            Route::delete('/{id}', [ConfigurationController::class, 'destroy'])->name('destroy');
        });
    });

    // ==================== SEGURIDAD ====================
    Route::prefix('security')->name('security.')->group(function () {
        Route::middleware('permission:security,view')->group(function () {
            Route::get('/', [SecurityPolicyController::class, 'index'])->name('index');
            Route::get('/{id}', [SecurityPolicyController::class, 'show'])->name('show');
        });

        Route::middleware('permission:security,create')->group(function () {
            Route::get('/create', [SecurityPolicyController::class, 'create'])->name('create');
            Route::post('/', [SecurityPolicyController::class, 'store'])->name('store');
        });

        Route::middleware('permission:security,edit')->group(function () {
            Route::get('/{id}/edit', [SecurityPolicyController::class, 'edit'])->name('edit');
            Route::put('/{id}', [SecurityPolicyController::class, 'update'])->name('update');
            Route::post('/{id}/activate', [SecurityPolicyController::class, 'activate'])->name('activate');
            Route::post('/{id}/deactivate', [SecurityPolicyController::class, 'deactivate'])->name('deactivate');
            Route::post('/test-password', [SecurityPolicyController::class, 'testPassword'])->name('test-password');
        });
    });

    // ==================== NOTIFICACIONES ====================
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::middleware('permission:notifications,view')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('/unread', [NotificationController::class, 'unread'])->name('unread');
            Route::post('/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('mark-as-read');
        });

        Route::middleware('permission:notifications,create')->group(function () {
            Route::get('/templates', [NotificationController::class, 'templates'])->name('templates');
            Route::get('/templates/create', [NotificationController::class, 'createTemplate'])->name('templates.create');
            Route::post('/templates', [NotificationController::class, 'storeTemplate'])->name('templates.store');
            Route::get('/templates/{id}/edit', [NotificationController::class, 'editTemplate'])->name('templates.edit');
            Route::put('/templates/{id}', [NotificationController::class, 'updateTemplate'])->name('templates.update');
            Route::delete('/templates/{id}', [NotificationController::class, 'destroyTemplate'])->name('templates.destroy');

            Route::get('/send', [NotificationController::class, 'sendForm'])->name('send');
            Route::post('/send', [NotificationController::class, 'send'])->name('send.post');
            Route::post('/process-pending', [NotificationController::class, 'processPending'])->name('process-pending');

            Route::get('/test', [NotificationController::class, 'testForm'])->name('test');
            Route::post('/preview', [NotificationController::class, 'preview'])->name('preview');
        });
    });

    // ==================== WORKFLOWS ====================
    Route::prefix('workflows')->name('workflows.')->group(function () {
        Route::middleware('permission:workflows,view')->group(function () {
            Route::get('/', [WorkflowController::class, 'index'])->name('index');
            Route::get('/{id}', [WorkflowController::class, 'show'])->name('show');
        });

        Route::middleware('permission:workflows,create')->group(function () {
            Route::get('/create', [WorkflowController::class, 'create'])->name('create');
            Route::post('/', [WorkflowController::class, 'store'])->name('store');
        });

        Route::middleware('permission:workflows,edit')->group(function () {
            Route::get('/{id}/edit', [WorkflowController::class, 'edit'])->name('edit');
            Route::put('/{id}', [WorkflowController::class, 'update'])->name('update');
            Route::post('/{id}/activate', [WorkflowController::class, 'activate'])->name('activate');
            Route::post('/{id}/deactivate', [WorkflowController::class, 'deactivate'])->name('deactivate');
        });

        Route::middleware('permission:workflows,execute')->group(function () {
            Route::post('/{id}/execute-transition', [WorkflowController::class, 'executeTransition'])->name('execute-transition');
        });

        Route::middleware('permission:workflows,delete')->group(function () {
            Route::delete('/{id}', [WorkflowController::class, 'destroy'])->name('destroy');
        });
    });

    // ==================== AUDITORÍA ====================
    Route::prefix('audit')->name('audit.')->group(function () {
        Route::middleware('permission:audit,view')->group(function () {
            Route::get('/', [AuditController::class, 'index'])->name('index');
            Route::get('/dashboard', [AuditController::class, 'dashboard'])->name('dashboard');
            Route::get('/{id}', [AuditController::class, 'show'])->name('show');
        });

        Route::middleware('permission:audit,export')->group(function () {
            Route::get('/export', [AuditController::class, 'export'])->name('export');
        });
    });
});
