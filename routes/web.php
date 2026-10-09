<?php

use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;
/*
Route::get('/', function () {
    return view('welcome');
});
*/

Route::get('/', [ImportController::class, 'create'])
    ->name('imports.create');

Route::post('/imports', [ImportController::class, 'store'])
    ->name('imports.store');