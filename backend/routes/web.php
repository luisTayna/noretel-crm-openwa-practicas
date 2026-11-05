<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    // Si el usuario está autenticado, iniciar en Gestión de Clientes
    if (auth()->check()) {
        return redirect()->route('customer.customers.index');
    }

    // Si no está autenticado, mostrar login
    return redirect()->route('core.auth.login');
});
