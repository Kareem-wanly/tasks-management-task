<?php

use App\Http\Controllers\BitrixAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// In web.php because the OAuth state is kept in the session.
Route::get('/auth/bitrix/redirect', [BitrixAuthController::class, 'redirect']);
Route::match(['get', 'post'], '/auth/bitrix/callback', [BitrixAuthController::class, 'callback']);
