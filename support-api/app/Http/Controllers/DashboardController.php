<?php

namespace App\Http\Controllers;

use App\Models\Dashboard;
use Illuminate\Http\Request;

class DashboardController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        //

        $user = auth()->user();
        $dashboard = Dashboard::where('user_id', $user->id)->first();

        if (!$dashboard) {
            $dashboard = Dashboard::create([
                'user_id' => $user->id,
                'configuration' => json_encode([]),
                'enabled_widgets' => json_encode([]),
                'settings' => json_encode([]),
            ]);
        }

        return response()->json($dashboard);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Dashboard $dashboard) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Dashboard $dashboard) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Dashboard $dashboard) {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Dashboard $dashboard) {
        //
    }
}
