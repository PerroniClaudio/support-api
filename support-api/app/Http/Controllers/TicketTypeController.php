<?php

namespace App\Http\Controllers;

use App\Models\TicketType;
use App\Models\TypeFormFields;
use App\Models\TicketTypeCategory;
use Illuminate\Http\Request;

class TicketTypeController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {

        $ticketTypes = TicketType::with('category')->get();

        return response([
            'ticketTypes' => $ticketTypes,
        ], 200);
    }

    public function categories() {

        $ticketTypeCategories = TicketTypeCategory::all();

        return response([
            'categories' => $ticketTypeCategories,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        //

        $validated = $request->validate([
            'name' => 'required',
            'ticket_type_category_id' => 'required',
        ]);

        $ticketType = TicketType::create($validated);

        return response([
            'ticketType' => $ticketType,
        ], 200);
    }

    public function storeCategory(Request $request) {

        $validated = $request->validate([
            'name' => 'required',
            'is_problem' => 'required',
            'is_request' => 'required',
        ]);

        $ticketTypeCategory = TicketTypeCategory::create($validated);

        return response([
            'ticketTypeCategory' => $ticketTypeCategory,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(TicketType $ticketType) {

        $ticketType = TicketType::where('id', $ticketType->id)->with('category')->first();

        return response([
            'ticketType' => $ticketType,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TicketType $ticketType) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketType $ticketType) {

        $validated = [
            'name' => $request->name,
            'ticket_type_category_id' => $request->ticket_type_category_id,
        ];

        $ticketType->update($validated);

        $tt = TicketType::where('id', $ticketType->id)->with('category')->first();

        return response([
            'ticketType' =>  $tt,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketType $ticketType) {
        //
    }

    public function getWebForm($id) {

        if ($id == 0) {
            return response([
                'webform' => [],
            ], 200);
        }

        $ticketType = TicketType::where('id', $id)->first();

        return response([
            'webform' => $ticketType->typeFormField,
        ], 200);
    }

    public function getGroups(TicketType $ticketType) {
        $groups = $ticketType->groups()->get();

        return response([
            'groups' => $groups,
        ], 200);
    }

    public function getCompanies(TicketType $ticketType) {
        $companies = $ticketType->companies()->get();

        return response([
            'companies' => $companies,
        ], 200);
    }

    public function updateCompanies(Request $request) {

        $validated = $request->validate([
            'ticket_type_id' => 'required',
            'companies' => 'required',
        ]);

        $ticketType = TicketType::where('id', $validated['ticket_type_id'])->first();

        $ticketType->companies()->sync($validated['companies']);

        return response([
            'ticketType' => $ticketType,
        ], 200);
    }

    public function deleteCompany(Request $request) {

        $validated = $request->validate([
            'ticket_type_id' => 'required',
            'company_id' => 'required',
        ]);

        $ticketType = TicketType::where('id', $validated['ticket_type_id'])->first();

        $ticketType->companies()->detach($validated['company_id']);

        $companies = $ticketType->companies()->get();

        return response([
            'companies' => $companies,
        ], 200);
    }

    public function updateSla(Request $request) {

        $validated = $request->validate([
            'ticket_type_id' => 'required',
            'company_id' => 'required',
            'sla_taking_charge' => 'required',
            'sla_resolving' => 'required',
        ]);

        $ticketType = TicketType::where('id', $validated['ticket_type_id'])->first();

        $ticketType->companies()->updateExistingPivot(
            $validated['company_id'],
            [
                'sla_taking_charge' => $validated['sla_taking_charge'],
                'sla_resolving' => $validated['sla_resolving'],
            ]
        );

        $companies = $ticketType->companies()->get();

        return response([
            'companies' => $companies,
        ], 200);
    }

    public function updateGroups(Request $request) {

        $validated = $request->validate([
            'ticket_type_id' => 'required',
            'groups' => 'required',
        ]);

        $ticketType = TicketType::where('id', $validated['ticket_type_id'])->first();

        $ticketType->groups()->sync($validated['groups']);

        return response([
            'ticketType' => $ticketType,
        ], 200);
    }

    public function deleteGroups(Request $request) {

        $validated = $request->validate([
            'ticket_type_id' => 'required',
            'group_id' => 'required',
        ]);

        $ticketType = TicketType::where('id', $validated['ticket_type_id'])->first();

        $ticketType->groups()->detach($validated['group_id']);

        $groups = $ticketType->groups()->get();

        return response([
            'groups' => $groups,
        ], 200);
    }

    public function createFormField(Request $request) {

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
