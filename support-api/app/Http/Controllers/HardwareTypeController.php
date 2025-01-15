<?php

namespace App\Http\Controllers;

use App\Models\HardwareType;
use Illuminate\Http\Request;

class HardwareTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $hardwareTypes = HardwareType::all();

        return response([
            'hardwareTypes' => $hardwareTypes,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response([
            'message' => 'Please use /api/store to create a new hardware type',
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
     
        if (!$user->is_admin) {
            return response([
                'message' => 'You are not allowed to create hardware types',
            ], 403);
        }

        $data = $request->validate([
            'name' => 'required|string',
        ]);        

        $hardwareType = HardwareType::create($data);

        return response([
            'hardwareType' => $hardwareType,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(HardwareType $hardwareType)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(HardwareType $hardwareType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, HardwareType $hardwareType)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HardwareType $hardwareType)
    {
        //
    }
}
