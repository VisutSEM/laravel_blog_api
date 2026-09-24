<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController; // Fixed missing import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Authentication User
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/users', [UserController::class, 'show']);
Route::post('/update-fcm-token', [NotificationController::class, 'updateFcmToken']);

// Profile & Cart Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
    Route::post('/profile/picture', [UserController::class, 'updateProfilePicture']);
    Route::delete('/profile/picture', [UserController::class, 'deleteProfilePicture']);

    // Wishlist routes
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);

    // --- Cart Routes (RESTful API Endpoint Alignment) ---
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);            // Changed from /cart/add -> /cart
    Route::put('/cart/{id}', [CartController::class, 'updateQuantity']);    // Changed from /cart/update/{id} -> /cart/{id}
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);       // Changed from /cart/remove/{id} -> /cart/{id}

    // --- Checkout Route ---
    Route::post('/checkout', [CheckoutController::class, 'checkout']);
});

// Admin routes
Route::prefix('admin')->group(function () {
    // Categories
    Route::post('categories', [CategoriesController::class, 'store']);
    Route::get('categories', [CategoriesController::class, 'index']);
    Route::get('categories/{id}', [CategoriesController::class, 'show']);
    Route::put('categories/{category}', [CategoriesController::class, 'update']);
    Route::delete('categories/{category}', [CategoriesController::class, 'destroy']);

    // Products
    Route::post('products', [ProductController::class, 'store']);
    Route::get('products', [ProductController::class, 'index']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);

    // Banners
    Route::get('banners', [BannerController::class, 'index']);
    Route::get('banners/{id}', [BannerController::class, 'show']);
    Route::post('banners', [BannerController::class, 'store']);
    Route::post('banners/{id}', [BannerController::class, 'update']);
    Route::delete('banners/{id}', [BannerController::class, 'destroy']);

    // Address
    Route::get('address', [AddressController::class, 'index']);
    Route::post('address', [AddressController::class, 'store']);
    Route::put('address/{id}', [AddressController::class, 'update']);
});