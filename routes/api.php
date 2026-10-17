<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WishlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication & Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/users', [UserController::class, 'show']);

// Password Reset Routes (Email or Phone number OTP flow)
Route::post('/password/forgot', [PasswordResetController::class, 'sendResetOtp']);
Route::post('/password/verify-otp', [PasswordResetController::class, 'verifyOtp']);
Route::post('/password/reset', [PasswordResetController::class, 'resetPassword']);
// Convenience aliases
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetOtp']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

// Category endpoints (supports both /categories and /admin/categories)
Route::get('/categories', [CategoriesController::class, 'index']);
Route::get('/categories/{category}', [CategoriesController::class, 'show']);
Route::post('/categories', [CategoriesController::class, 'store']);
Route::match(['put', 'patch'], '/categories/{category}', [CategoriesController::class, 'update']);
Route::delete('/categories/{category}', [CategoriesController::class, 'destroy']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

Route::get('/banners', [BannerController::class, 'index']);
Route::get('/banners/{id}', [BannerController::class, 'show']);

// Allow unauthenticated token registration if needed, but handled safely
Route::post('/update-fcm-token', [NotificationController::class, 'updateFcmToken']);

/*
|--------------------------------------------------------------------------
| Authenticated User Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'data'   => $request->user(),
        ]);
    });

    // Profile management
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::post('/profile/picture', [UserController::class, 'updateProfilePicture']);
    Route::delete('/profile/picture', [UserController::class, 'deleteProfilePicture']);

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);

    // Cart routes (with legacy aliases)
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::post('/cart/add', [CartController::class, 'store']);
    Route::put('/cart/{id}', [CartController::class, 'updateQuantity']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Checkout & Order routes
    Route::post('/checkout', [CheckoutController::class, 'checkout']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::match(['put', 'patch'], '/orders/{order}', [OrderController::class, 'update']);
    Route::match(['put', 'patch'], '/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::put('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::delete('/orders/{order}', [OrderController::class, 'destroy']);

    // User address management
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::get('/addresses/{id}', [AddressController::class, 'show']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Admin & Legacy Management Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    // Categories
    Route::get('categories', [CategoriesController::class, 'index']);
    Route::get('categories/{id}', [CategoriesController::class, 'show']);
    Route::post('categories', [CategoriesController::class, 'store']);
    Route::match(['put', 'patch'], 'categories/{category}', [CategoriesController::class, 'update']);
    Route::delete('categories/{category}', [CategoriesController::class, 'destroy']);

    // Products
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);

    // Banners
    Route::get('banners', [BannerController::class, 'index']);
    Route::get('banners/{id}', [BannerController::class, 'show']);
    Route::post('banners', [BannerController::class, 'store']);
    Route::post('banners/{id}', [BannerController::class, 'update']);
    Route::delete('banners/{id}', [BannerController::class, 'destroy']);

    // Address (Admin / Legacy aliases)
    Route::get('address', [AddressController::class, 'index']);
    Route::post('address', [AddressController::class, 'store']);
    Route::get('address/{id}', [AddressController::class, 'show']);
    Route::put('address/{id}', [AddressController::class, 'update']);
    Route::delete('address/{id}', [AddressController::class, 'destroy']);
});