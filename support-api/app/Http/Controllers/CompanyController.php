<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        $isAdminRequest = $request->user()["is_admin"] == 1;

        if($isAdminRequest){
            $companies = Company::all();
            if(!$companies){
                $companies = [];
            }
        } else {
            $companies = [];
        }

        return response([
            'companies' => $companies,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return response([
            'message' => 'Please use /api/store to create a new company',
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $fields = $request->validate([
            'name' => 'required|string',
        ]);

        $user = $request->user();

        if($user["is_admin"] != 1){
            return response([
                'message' => "Unauthorized",
            ], 401);
        }
            
        // Il campo sla non serve più. Quando si modificherà il database, togliere anche il campo da qui
        $newCompany = Company::create([
            'name' => $fields['name'],
            'sla' => 'vuoto',
        ]);
        
        return response([
            'company' => $newCompany,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        if($user["is_admin"] != 1){
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $company = Company::where('id', $id)->first();

        if(!$company){
            return response([
                'message' => 'Company not found',
            ], 404);
        }

        return response([
            'company' => $company,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company)
    {
        //
        return response([
            'message' => 'Please use /api/update to update an existing company',
        ], 404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $fields = $request->validate([
            'id' => 'required|int|exists:companies,id', // TODO: 'id' => 'required|int|exists:companies,id
            'name' => 'required|string',
            'note' => 'nullable|string',
        ]);

        $user = $request->user();

        if($user["is_admin"] != 1){
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }
        
        $company = Company::where('id', $request['id'])->first();

        if(!$company){
            return response([
                'message' => 'Company not found',
            ], 404);
        }

        $updatedFields = [];

        $companyFields = $company->getFillable();

        foreach ($request->all() as $fieldName => $fieldValue) {
            if (in_array($fieldName, $companyFields)) {
                $updatedFields[$fieldName] = $fieldValue;
            }
        }

        $company->update($updatedFields);

        return response([
            'company' => $company,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if($user["is_admin"] != 1 ){
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $deleted_company = Company::destroy($id);

        if(!$deleted_company){
            return response([
                'message' => 'Error',
            ], 404);
        }

        return response([
            'deleted_company' => $id,
        ], 200);
    }

    public function offices(Company $company) {
        $offices = $company->offices()->get();

        return response([
            'offices' => $offices,
        ], 200);
    }

    public function admins(Company $company) {
        $users = $company->users()->where('is_company_admin', 1)->get();

        return response([
            'users' => $users,
        ], 200);
    }
    
    public function allusers(Company $company, Request $request) {
        $user = $request->user();

        // Se non è admin o non è della compagnia e company_admin allora non è autorizzato
        if(!($user["is_admin"] == 1 || ($user["company_id"] == $company["id"] && $user["is_company_admin"] == 1))){
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $users = $company->users()->get();

        return response([
            'users' => $users,
        ], 200);
    }
    
    public function ticketTypes(Company $company) {
        $ticketTypes = $company->ticketTypes()->with('category')->get();

        return response([
            'companyTicketTypes' => $ticketTypes,
        ], 200);
    }
}
