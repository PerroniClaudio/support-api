<?php

namespace App\Http\Controllers;

use App\Models\TicketTypeCategory;
use Illuminate\Http\Request;

class TicketTypeCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ticketTypeCategories = TicketTypeCategory::all();

        return response($ticketTypeCategories, 200);
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
    public function show(TicketTypeCategory $ticketTypeCategory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TicketTypeCategory $ticketTypeCategory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketTypeCategory $ticketTypeCategory)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketTypeCategory $ticketTypeCategory, Request $request)
    {
        $user = $request->user();
        if(!$user['is_admin']) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $ticketTypeCategory->delete();

        return response([
            'message' => 'Ticket type category deleted successfully',
        ], 200);
    }
}
