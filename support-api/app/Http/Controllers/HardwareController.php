<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Hardware;
use App\Models\HardwareType;
use App\Models\HardwareUserAuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

use function PHPUnit\Framework\isEmpty;

class HardwareController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authUser = $request->user();
        if ($authUser->is_admin) {
            $hardwareList = Hardware::with(['hardwareType', 'company'])->get();
            return response([
                'hardwareList' => $hardwareList,
            ], 200);
        }
        
        if($authUser->is_company_admin) {
            $hardwareList = Hardware::where('company_id', $authUser->company_id)->with(['hardwareType', 'company'])->get();
            return response([
                'hardwareList' => $hardwareList,
            ], 200);
        }

        $hardwareList = Hardware::where('company_id', $authUser->company_id)->where('user_id', $authUser->id)->with(['hardwareType', 'company'])->get();
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
        $authUser = $request->user();
     
        if (!$authUser->is_admin) {
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
            'ownership_type' => 'nullable|string',
            'ownership_type_note' => 'nullable|string',
            'notes' => 'nullable|string',
            'users' => 'nullable|array',
        ]);        

        if (isset($data['company_id']) && !Company::find($data['company_id'])) {
            return response([
                'message' => 'Company not found',
            ], 404);
        }

        // Aggiungere le associazioni utenti
        if (isset($data['company_id']) && !empty($data['users']) ) {
            $isFail = User::whereIn('id', $data['users'])->where('company_id', '!=', $data['company_id'])->exists();
            if ($isFail) {
                return response([
                    'message' => 'One or more users do not belong to the specified company',
                ], 400);
            }

        }
        
        $hardware = Hardware::create($data);

        if (!empty($data['users'])) {
            // Non so perchè ma non crea i log in automatico, quindi devo aggiungerli manualmente
            // $hardware->users()->attach($data['users']);
            
            foreach ($data['users'] as $userId) {
                $hardware->users()->syncWithoutDetaching($userId, [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
                HardwareUserAuditLog::create([
                    'type' => 'created',
                    'modified_by' => $authUser->id,
                    'hardware_id' => $hardware->id,
                    'user_id' => $userId,
                ]);
            }
        }
        
        return response([
            'hardware' => $hardware,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Hardware $hardware)
    {
        $authUser = $request->user();
        if (!$authUser->is_admin 
            && !($authUser->is_company_admin && $hardware->company_id == $authUser->company_id) 
            && !(in_array($authUser->id, $hardware->users->pluck('id')->toArray()))) {
            return response([
                'message' => 'You are not allowed to view this hardware',
            ], 403);
        }
        if($authUser->is_admin || $authUser->is_company_admin) {
            // $hardware->load(['company', 'hardwareType', 'users']);
            $hardware->load([
                'company' => function ($query) {$query->select('id', 'name');}, 
                'hardwareType', 
                'users' => function ($query) {
                    $query->select('user_id as id', 'name', 'surname', 'email', 'is_company_admin', 'is_deleted'); // Limit user data sent to frontend
            }]);
        } else {
            $hardware->load([
                'company' => function ($query) {$query->select('id', 'name');}, 
                'hardwareType', 'users' => function ($query) {
                    $query->select('user_id as id', 'name', 'surname', 'email', 'is_company_admin', 'is_deleted'); // Limit user data sent to frontend
            }]);
        }
        return response([
            'hardware' => $hardware,
        ], 200);
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
        $authUser = $request->user();
     
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to edit hardware',
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
            'ownership_type' => 'nullable|string',
            'ownership_type_note' => 'nullable|string',
            'notes' => 'nullable|string',
            'users' => 'nullable|array',
        ]);        

        if (isset($data['company_id']) && !Company::find($data['company_id'])) {
            return response([
                'message' => 'Company not found',
            ], 404);
        }

        // controllare le associazioni utenti
        if (isset($data['company_id']) && !empty($data['users']) ) {
            $isFail = User::whereIn('id', $data['users'])->where('company_id', '!=', $data['company_id'])->exists();
            if ($isFail) {
                return response([
                    'message' => 'One or more selected users do not belong to the specified company',
                ], 400);
            }

        }

        // Aggiorna l'hardware
        $hardware->update($data);

        // Aggiorna gli utenti associati
        // Non so perchè ma non crea i log in automatico, quindi devo aggiungerli manualmente
        // $hardware->users()->attach($data['users']);
        
        $usersToRemove = $hardware->users->pluck('id')->diff($data['users']);
        $usersToAdd = collect($data['users'])->diff($hardware->users->pluck('id'));

        foreach ($usersToAdd as $userId) {
            $hardware->users()->syncWithoutDetaching($userId, [
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            HardwareUserAuditLog::create([
                'type' => 'created',
                'modified_by' => $authUser->id,
                'hardware_id' => $hardware->id,
                'user_id' => $userId,
            ]);
        }

        foreach ($usersToRemove as $userId) {
            $hardware->users()->detach($userId);
            HardwareUserAuditLog::create([
                'type' => 'deleted',
                'modified_by' => $authUser->id,
                'hardware_id' => $hardware->id,
                'user_id' => $userId,
            ]);
        }
        
        return response([
            'hardware' => $hardware,
        ], 201);

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

    public function updateHardwareUsers(Request $request, Hardware $hardware)
    {
        $hardware = Hardware::find($hardware->id);
        if (!$hardware) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }

        $authUser = $request->user();
        if (!($authUser->is_company_admin && ($hardware->company_id == $authUser->company_id)) && !$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to update hardware users',
            ], 403);
        }

        $data = $request->validate([
            'users' => 'nullable|array',
        ]);


        $company = $hardware->company;

        if($company && !isEmpty($data['users'])){
            $isFail = User::whereIn('id', $data['users'])->where('company_id', '!=', $company->id)->exists();
            if ($isFail) {
                return response([
                    'message' => 'One or more selected users do not belong to the specified company',
                ], 400);
            }
        }

        $users = User::whereIn('id', $data['users'])->get();
        if ($users->count() != count($data['users'])) {
            return response([
                'message' => 'One or more users not found',
            ], 404);
        }

        $usersToRemove = $hardware->users->pluck('id')->diff($data['users']);
        $usersToAdd = collect($data['users'])->diff($hardware->users->pluck('id'));

        foreach ($usersToAdd as $userId) {
            $hardware->users()->syncWithoutDetaching($userId, [
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            HardwareUserAuditLog::create([
                'type' => 'created',
                'modified_by' => $authUser->id,
                'hardware_id' => $hardware->id,
                'user_id' => $userId,
            ]);
        }

        foreach ($usersToRemove as $userId) {
            $hardware->users()->detach($userId);
            HardwareUserAuditLog::create([
                'type' => 'deleted',
                'modified_by' => $authUser->id,
                'hardware_id' => $hardware->id,
                'user_id' => $userId,
            ]);
        }

        return response([
            'message' => 'Hardware users updated successfully',
        ], 200);
    }

    public function deleteHardwareUser(Request $request)
    {
        $data = $request->validate([
            'hardware_id' => 'required|int',
            'user_id' => 'required|int',
        ]);

        $hardware = Hardware::find($request->hardware_id);
        if (!$hardware) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }

        $authUser = $request->user();
        if (!$authUser->is_admin && !($authUser->is_company_admin && ($hardware->company_id == $authUser->company_id))) {
            return response([
                'message' => 'You are not allowed to update hardware users',
            ], 403);
        }

        $user = User::find($data['user_id']);
        if (!$user) {
            return response([
                'message' => 'User not found',
            ], 404);
        }

        if (!$hardware->users->contains($user)) {
            return response([
                'message' => 'User not associated with hardware',
            ], 400);
        }

        $hardware->users()->detach($user->id);
        HardwareUserAuditLog::create([
            'type' => 'deleted',
            'modified_by' => $authUser->id,
            'hardware_id' => $hardware->id,
            'user_id' => $user->id,
        ]);
        
        return response([
            'message' => 'User removed from hardware successfully',
        ], 200);
    }

    public function userHardwareList(Request $request, User $user)
    {
        $authUser = $request->user();
        if (!$authUser->is_admin && !($authUser->company_id != $user->company_id) && !($authUser->id == $user->id)) {
            return response([
                'message' => 'You are not allowed to view this user hardware',
            ], 403);
        }

        $hardwareList = $user->hardware()->with(['hardwareType', 'company'])->get();
        return response([
            'hardwareList' => $hardwareList,
        ], 200);
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
