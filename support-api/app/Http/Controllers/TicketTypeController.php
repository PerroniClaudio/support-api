<?php

namespace App\Http\Controllers;

use App\Models\TicketType;
use App\Models\TypeFormFields;
use Illuminate\Http\Request;

class TicketTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        
        $ticketTypes = TicketType::with('category')->get();
        
        return response([
            'ticketTypes' => $ticketTypes,
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TicketType $ticketType)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TicketType $ticketType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketType $ticketType)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketType $ticketType)
    {
        //
    }
    
    public function getWebForm($id)
    {   

        if($id == 0) {
            return response([
                'webform' => [],
            ], 200);
        }

        $ticketType = TicketType::where('id', $id)->first();

        return response([
            'webform' => $ticketType->typeFormField,
        ], 200);
        
    }
    
    public function getGroups(TicketType $ticketType)
    {   
        $groups = $ticketType->groups()->get();
    
        return response([
            'ticketTypeGroups' => $groups,
        ], 200);
    
    }

    public function createFormField(Request $request)
    {   
        
        $validated = $request->validate([
            'ticket_type_id' => 'required',
            'field_name' => 'required',
            'field_type' => 'required',
            'field_label' => 'required',
            'required' => 'required',
            'placeholder' => 'required',
        ]);

        $formField = TypeFormFields::create($validated);
    
        return response([
            'formField' => $formField,
        ], 200);
    
    }
}
