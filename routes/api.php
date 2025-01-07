<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MarkupController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TopUpController;
use App\Http\Controllers\Api\TrxController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(Authenticate::using('sanctum'));

//posts
Route::apiResource('/posts', App\Http\Controllers\Api\PostController::class);


Route::prefix('v1')->group(function () {
    // auth route limited 6x response 
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register/member', [AuthController::class, 'registerMember']);
        Route::post('/login/member', [AuthController::class, 'loginMember']);
    });

    // doesnt have limited but need token 
    // protected route
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [UserController::class, 'show']);
        Route::post('/profile/update', [UserController::class, 'update']);
        Route::post('/profile/update/member', [UserController::class, 'updateMember']);
        Route::get('/balance', [UserController::class, 'checkBalance']);
        Route::post('/sync/product/Prabayar', [ProductController::class, 'productPrabayar']);
        Route::post('/sync/product/pascabayar', [ProductController::class, 'productPascabayar']);
        Route::post('/sync/product/search', [ProductController::class, 'showData']);
        Route::post('/transaction', [TrxController::class, 'transaction']);
        Route::post('/khusus/member', [TrxController::class, 'transactionMember']);
        Route::post('/products/markup/prabayar', [MarkupController::class, 'updatePrabayar']);
        Route::post('/products/markup/pascabayar', [MarkupController::class, 'updatePascabayar']);
        Route::get('/check', [TrxController::class, 'checkTransactionUser']);
        Route::post('/topup/create', [TopUpController::class, 'createTransaction']);
        Route::get('/transaction-summary', [PostController::class, 'getTransactionSummary']);
        Route::delete('/delete-account', [UserController::class, 'deleteAccount']);
    });
    Route::post('callback/midtrans', [TopUpController::class, 'handle']);
    Route::post('callback', [TrxController::class, 'handle']);
});
