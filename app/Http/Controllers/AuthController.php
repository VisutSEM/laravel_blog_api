<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
class AuthController extends Controller
{

    //register
    public function register(Request $request){
        $validated = $request->validate([
        'name'=>'required|string|max:255',
        'email'=>'required|email|unique:users,email',
        'password'=> 'required|string|min:6'
    ]);

    $user = User::create([
         'name' => $validated['name'],
         'email' => $validated['email'],
         'password'=> Hash::make($validated['password'])
    ]);

    return response()->json([
        'message' => 'Registered successfully',
        'user' => $user
    ], 201);

    }
    //login
    public function login(Request $request){
        $request->validate([
        'email' => 'required|email|exists:users,email',
        'password' => 'required'
    ]);

    if(!Auth::attempt($request->only('email', 'password'))){
        return response()->json([
            'message'=> 'email or password is invalid'
        ], 401);
    }

    $token = Auth::user()->createToken('laravel-blog-api')->plainTextToken;

    return response()->json([
        'message'=> 'login successful',
        'data'=> Auth::user(),
        'token' => $token
    ]);

    }
    //logout
    public function logout(){
         $user = Auth::user();
    $user->tokens()->delete();

    return response()->json([
        'message'=>'logout successful'
    ],200);

    }
}
