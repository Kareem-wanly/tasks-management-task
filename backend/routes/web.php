<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BitrixAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// In web.php because the OAuth state is kept in the session.
Route::get('/auth/bitrix/redirect', [BitrixAuthController::class, 'redirect'])->name('auth.bitrix.redirect');
Route::match(['get', 'post'], '/auth/bitrix/callback', [BitrixAuthController::class, 'callback'])->name('auth.bitrix.callback');
