<?php

namespace App\Http\Controllers;

use App\Models\Hardware;
use Illuminate\Http\Request;

class HardwareController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->is_admin) {
            $hardwareList = Hardware::with(['hardwareType', 'company'])->get();
            return response([
                'hardwareList' => $hardwareList,
            ], 200);
        }
        
        if($user->is_company_admin) {
            $hardwareList = Hardware::where('company_id', $user->company_id)->with(['hardwareType', 'company'])->get();
            return response([
                'hardwareList' => $hardwareList,
            ], 200);
        }

        $hardwareList = Hardware::where('company_id', $user->company_id)->where('user_id', $user->id)->with(['hardwareType', 'company'])->get();
        return response([
            'hardwareList' => $hardwareList,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response([
            'message' => 'Please use /api/store to create a new hardware',
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
                'message' => 'You are not allowed to create hardware',
            ], 403);
        }

        $data = $request->validate([
            'make' => 'required|string',
            'model' => 'required|string',
            'serial_number' => 'required|string',
            'company_asset_number' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'company_id' => 'nullable|int',
            'hardware_type_id' => 'nullable|int',
        ]);        

        $hardware = Hardware::create($data);

        return response([
            'hardware' => $hardware,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Hardware $hardware)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Hardware $hardware)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Hardware $hardware)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        // Soft delete: delete(); Hard delete: forceDelete();
        // Senza soft deleted ::find(1), o il metodo che si vuole; con soft deleted ::withTrashed()->find(1); 
    
        $user = $request->user();
        if (!$user->is_admin) {
            return response([
                'message' => 'You are not allowed to delete hardware',
            ], 403);
        }

        if ($request->with_force) {
            $hardware = Hardware::withTrashed()->find($request->id);
            $hardware->forceDelete();
            return response([
                'message' => 'Hardware deleted successfully',
            ], 200);
        } else {
            $hardware = Hardware::find($request->id);
            $hardware->delete();
            return response([
                'message' => 'Hardware soft deleted successfully',
            ], 200);
        }        

    }

    public function getHardwareTypes()
    {
        return HardwareType::all();
    }

    public function assignHardwareToUser(Request $request)
    {
        
    }

    public function removeHardwareFromUser(Request $request)
    {
        
    }

    public function assignHardwareToCompany(Request $request)
    {
        
    }

    public function removeHardwareFromCompany(Request $request)
    {
        
    }
    
}
