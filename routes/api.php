<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureTokenIsValid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


//Authentication User
Route::post('register',[AuthController::class, 'register']);

Route::post('login', [AuthController::class, 'login']);

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/users',[UserController::class, 'show']);

//Post