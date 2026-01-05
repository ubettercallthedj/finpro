<?php

use Illuminate\Support\Facades\Route;

// Servir el frontend React para todas las rutas que no sean /api/*
Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '^(?!api).*$');
