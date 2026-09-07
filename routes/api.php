<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\CategoriesContrller;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\ProductController;
use App\Http\Middleware\EnsureTokenIsValid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\AdressController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


//Authentication User
Route::post('register', [AuthController::class, 'register']);

Route::post('login', [AuthController::class, 'login']);

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/users', [UserController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
   // Profile
    Route::get('/profile', [UserController::class, 'profile']);

     // Upload / update profile image
    Route::post('/profile/picture', [UserController::class, 'updateProfilePicture']);

    // Delete profile image
    Route::delete('/profile/picture', [UserController::class, 'deleteProfilePicture']);
});

//admin routes
Route::prefix('admin')->group(function () {
    Route::post('categories', [CategoriesContrller::class, 'store']);
    Route::get('categories', [CategoriesContrller::class, 'index']);
    Route::get('categories/{id}', [CategoriesContrller::class, 'show']);
    Route::put('categories/{category}', [CategoriesContrller::class, 'update']);
    Route::delete('categories/{category}', [CategoriesContrller::class, 'destroy']);
    

    //============= Products ===============//
    Route::post('products', [ProductController::class, 'store']);
    Route::get('products', [ProductController::class, 'index']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);

    //============= Banners ===============//
    Route::get('banners', [BannerController::class, 'index']);
    Route::post('banners', [BannerController::class, 'store']);
    Route::put('/banners/{id}', [BannerController::class, 'update']); // with image upload
    Route::delete('/banners/{id}', [BannerController::class, 'destroy']);

    //============= Address ===============//
    Route::get('address', [AdressController::class, 'index']);
    Route::post('address', [AdressController::class, 'store']);
    Route::put('address/{id}', [AdressController::class, 'update']);
});
