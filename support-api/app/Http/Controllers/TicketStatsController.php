<?php

namespace App\Http\Controllers;

use App\Models\TicketStats;
use Illuminate\Http\Request;

class TicketStatsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TicketStats $ticketStats)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TicketStats $ticketStats)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketStats $ticketStats)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketStats $ticketStats)
    {
        //
    }

    public function latestStats() {
        $stats = TicketStats::latest()->first();
        return response([
            'stats' => $stats,
        ], 200);
    }
}
