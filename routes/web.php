<?php

use Illuminate\Support\Facades\Route;
use Cloudinary\Cloudinary;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/test-cloudinary', function () {
    $cloudinary = new Cloudinary(
        env('CLOUDINARY_URL')
    );

    return response()->json([
        'message' => 'Cloudinary configuration loaded successfully'
    ]);
});