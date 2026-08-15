<?php

namespace App\Http\Controllers;

use App\Models\Adress;
use Faker\Provider\ar_EG\Address;
use Illuminate\Http\Request;

class AdressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $address = Adress::all();
        return response()->json([
            'status' => 200,
            'address' => $address
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'city' => 'required|string|max:100',
            'street'=> 'required|string|max:255',
            'state' => 'required|string|max:100',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);
        $address = Adress::create($data);
        return response()->json([
            'message' => 'Address created successfully',
            'address' => $address
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Adress $adress)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Adress $adress)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Adress $adress)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'city' => 'required|string|max:100',
            'street'=> 'required|string|max:255',
            'state' => 'required|string|max:100',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);
        $adress->update($data);
        return response()->json([
            'message' => 'Address updated successfully',
            'address' => $adress
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Adress $adress)
    {
        //
    }
}
