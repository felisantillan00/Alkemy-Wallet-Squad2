<?php

use Illuminate\Support\Facades\Route;

// El frontend vive en public/app: la raíz del sitio lleva directo al login
// en lugar de mostrar la página de bienvenida de Laravel.
Route::get('/', function () {
    return redirect('/app/index.html');
});
