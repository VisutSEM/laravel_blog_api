<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(){
        $user = User::all();
        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }
}
