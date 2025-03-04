<?php

namespace App\Http\Controllers;

use App\Exports\HardwareAssignationTemplateExport;
use App\Exports\HardwareDeletionTemplateExport;
use App\Exports\HardwareLogsExport;
use App\Exports\HardwareTemplateExport;
use App\Imports\HardwareAssignationsImport;
use App\Imports\HardwareDeletionsImport;
use App\Imports\HardwareImport;
use App\Models\Company;
use App\Models\Hardware;
use App\Models\HardwareAuditLog;
use App\Models\HardwareType;
use App\Models\TypeFormFields;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Faker\Factory as Faker;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;


use function PHPUnit\Framework\isEmpty;

class HardwareController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        $authUser = $request->user();
        if ($authUser->is_admin) {
            $hardwareList = Hardware::with(['hardwareType', 'company'])->get();
            return response([
                'hardwareList' => $hardwareList,
            ], 200);
        }

        if ($authUser->is_company_admin) {
            $hardwareList = Hardware::where('company_id', $authUser->company_id)->with(['hardwareType', 'company'])->get();
            return response([
                'hardwareList' => $hardwareList,
            ], 200);
        }

        $hardwareList = Hardware::where('company_id', $authUser->company_id)->whereHas('users', function ($query) use ($authUser) {
            $query->where('user_id', $authUser->id);
        })->with(['hardwareType', 'company'])->get();

        return response([
            'hardwareList' => $hardwareList,
        ], 200);
    }

    public function companyHardwareList(Request $request, Company $company) {
        $authUser = $request->user();
        if (!$authUser->is_admin && !($authUser->is_company_admin && ($company->id == $authUser->company_id))) {
            return response([
                'message' => 'You are not allowed to view this hardware',
            ], 403);
        }

        $hardwareList = Hardware::where('company_id', $company->id)->with(['hardwareType', 'company'])->get();
        return response([
            'hardwareList' => $hardwareList,
        ], 200);
    }

    public function formFieldHardwareList(Request $request, TypeFormFields $typeFormField) {
        $authUser = $request->user();

        if (!$typeFormField) {
            return response([
                'message' => 'Type form field not found',
            ], 404);
        }

        $company = $typeFormField->ticketType->company;
        if (!$authUser->is_admin && !(!!$company && ($company->id == $authUser->company_id))) {
            return response([
                'message' => 'You are not allowed to view this hardware',
            ], 403);
        }

        $hardwareList = [];

        // Con una query unica
        // Costruisci la query di base
        if ($authUser->is_admin || $authUser->is_company_admin) {
            $query = Hardware::where('company_id', $company->id);
        } else {
            $query = $authUser->hardware();
        }
        // Aggiungi le relazioni
        $query->with(['hardwareType', 'company']);
        // Se necessario rimuove gli hardware che non hanno il tipo associato
        if (!$typeFormField->include_no_type_hardware) {
            $query->whereNotNull('hardware_type_id');
        }
        // Se necessario limitare a determinati tipi di hardware (tenendo conto dell'hardware che non ha un tipo associato)
        if ($typeFormField->hardwareTypes->count() > 0) {
            $hardwareTypeIds = $typeFormField->hardwareTypes->pluck('id')->toArray();
            $query->where(function ($query) use ($hardwareTypeIds, $typeFormField) {
                $query->whereIn('hardware_type_id', $hardwareTypeIds);
                if ($typeFormField->include_no_type_hardware) {
                    $query->orWhereNull('hardware_type_id');
                }
            });
        }

        // Esegui la query
        $hardwareList = $query->get();

        return response([
            'hardwareList' => $hardwareList,
        ], 200);
    }

    public function hardwareListWithTrashed(Request $request) {

        $authUser = $request->user();

        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to view this hardware',
            ], 403);
        }

        $hardwareList = Hardware::withTrashed()->with(['hardwareType', 'company'])->get();
        return response([
            'hardwareList' => $hardwareList,
        ], 200);

        // Se servisse anche per gli utenti 
        // if ($authUser->is_admin) {
        //     $hardwareList = Hardware::withTrashed()->with(['hardwareType', 'company'])->get();
        //     return response([
        //         'hardwareList' => $hardwareList,
        //     ], 200);
        // }
        // if($authUser->is_company_admin) {
        //     $hardwareList = Hardware::withTrashed()->where('company_id', $authUser->company_id)->with(['hardwareType', 'company'])->get();
        //     return response([
        //         'hardwareList' => $hardwareList,
        //     ], 200);
        // }

        // $hardwareList = Hardware::withTrashed()->where('company_id', $authUser->company_id)->where('user_id', $authUser->id)->with(['hardwareType', 'company'])->get();

        return response([
            'hardwareList' => $hardwareList,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        return response([
            'message' => 'Please use /api/store to create a new hardware',
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
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
            'is_exclusive_use' => 'required|boolean',
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
        if (isset($data['company_id']) && !empty($data['users'])) {
            $isFail = User::whereIn('id', $data['users'])->where('company_id', '!=', $data['company_id'])->exists();
            if ($isFail) {
                return response([
                    'message' => 'One or more users do not belong to the specified company',
                ], 400);
            }
        }

        $hardware = Hardware::create($data);

        if ($hardware->company_id) {
            HardwareAuditLog::create([
                'modified_by' => $authUser->id,
                'hardware_id' => $hardware->id,
                'log_subject' => 'hardware_company',
                'log_type' => 'create',
                'new_data' => json_encode(['company_id' => $hardware->company_id]),
            ]);
        }

        if (!empty($data['users'])) {
            // Non so perchè ma non crea i log in automatico, quindi devo aggiungerli manualmente
            // $hardware->users()->attach($data['users']);

            foreach ($data['users'] as $userId) {
                $hardware->users()->attach($userId, [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
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
    public function show(Request $request,  $hardwareId) {
        $authUser = $request->user();
        $hardware = null;

        if ($authUser->is_admin) {
            $hardware = Hardware::withTrashed()->find($hardwareId);
        } else {
            $hardware = Hardware::find($hardwareId);
        }

        if (!$hardware) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }

        if (
            !$authUser->is_admin
            && !($authUser->is_company_admin && ($hardware->company_id == $authUser->company_id))
            && !(in_array($authUser->id, $hardware->users->pluck('id')->toArray()))
        ) {
            return response([
                'message' => 'You are not allowed to view this hardware',
            ], 403);
        }



        if ($authUser->is_admin || $authUser->is_company_admin) {
            // $hardware->load(['company', 'hardwareType', 'users']);
            // if (!$hardware) {
            //     $hardware = Hardware::withTrashed()->find($hardware->id);
            // }
            $hardware->load([
                'company' => function ($query) {
                    $query->select('id', 'name');
                },
                'hardwareType',
                'users' => function ($query) {
                    $query->select('user_id as id', 'name', 'surname', 'email', 'is_company_admin', 'is_deleted'); // Limit user data sent to frontend
                }
            ]);
        } else {
            $hardware->load([
                'company' => function ($query) {
                    $query->select('id', 'name');
                },
                'hardwareType',
                'users' => function ($query) {
                    $query->select('user_id as id', 'name', 'surname', 'email', 'is_company_admin', 'is_deleted'); // Limit user data sent to frontend
                }
            ]);
        }
        return response([
            'hardware' => $hardware,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Hardware $hardware) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Hardware $hardware) {
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
            'is_exclusive_use' => 'required|boolean',
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
        if (isset($data['company_id']) && !empty($data['users'])) {
            $isFail = User::whereIn('id', $data['users'])->where('company_id', '!=', $data['company_id'])->exists();
            if ($isFail) {
                return response([
                    'message' => 'One or more selected users do not belong to the specified company',
                ], 400);
            }
        }

        if(!$hardware->is_exclusive_use && $data['is_exclusive_use'] && count($data['users']) > 1) {
            return response([
                'message' => 'Exclusive use hardware can be associated to no more than one user. Hardware not updated.',
            ], 400);
        }

        $oldCompanyId = $hardware->company_id;

        // Aggiorna l'hardware
        $hardware->update($data);

        if ($hardware->company_id != $oldCompanyId) {
            $logType = $oldCompanyId ? ($hardware->company_id ? 'update' : 'delete') : 'create';
            $oldData = $oldCompanyId ? json_encode(['company_id' => $oldCompanyId]) : null;
            $newData = $hardware->company_id ? json_encode(['company_id' => $hardware->company_id]) : null;
            HardwareAuditLog::create([
                'modified_by' => $authUser->id,
                'hardware_id' => $hardware->id,
                'log_subject' => 'hardware_company',
                'log_type' => $logType,
                'old_data' => $oldData,
                'new_data' => $newData,
            ]);
        }

        // Aggiorna gli utenti associati
        // Non so perchè ma non crea i log in automatico, quindi devo aggiungerli manualmente
        // $hardware->users()->attach($data['users']);

        $usersToRemove = $hardware->users->pluck('id')->diff($data['users']);
        $usersToAdd = collect($data['users'])->diff($hardware->users->pluck('id'));

        foreach ($usersToAdd as $userId) {
            $hardware->users()->attach($userId, [
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                // Qui si potrebbe aggiungere il campo responsible_user_id ma bisogna creare il frontend per settarlo
            ]);
        }

        foreach ($usersToRemove as $userId) {
            $hardware->users()->detach($userId);
        }

        return response([
            'hardware' => $hardware,
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($hardwareId, Request $request) {
        // Soft delete: delete(); Hard delete: forceDelete();
        // Senza soft deleted ::find(1), o il metodo che si vuole; con soft deleted ::withTrashed()->find(1); 

        $user = $request->user();
        if (!$user->is_admin) {
            return response([
                'message' => 'You are not allowed to delete hardware',
            ], 403);
        }

        $hardware = Hardware::findOrFail($hardwareId);
        if (!$hardware) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }
        $hardware->delete();
        HardwareAuditLog::create([
            'modified_by' => $user->id,
            'hardware_id' => null,
            'log_subject' => 'hardware',
            'log_type' => 'delete',
            'old_data' => json_encode($hardware->toArray()),
            'new_data' => null,
        ]);
        return response([
            'message' => 'Hardware soft deleted successfully',
        ], 200);
    }

    public function destroyTrashed($hardwareId, Request $request) {
        // Soft delete: delete(); Hard delete: forceDelete();
        // Senza soft deleted ::find(1), o il metodo che si vuole; con soft deleted ::withTrashed()->find(1); 

        $user = $request->user();
        if (!$user->is_admin) {
            return response([
                'message' => 'You are not allowed to delete hardware',
            ], 403);
        }

        $hardware = Hardware::withTrashed()->findOrFail($hardwareId);
        if (!$hardware) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }
        $hardware->forceDelete();
        HardwareAuditLog::create([
            'modified_by' => $user->id,
            'hardware_id' => null,
            'log_subject' => 'hardware',
            'log_type' => 'permanent-delete',
            'old_data' => json_encode($hardware->toArray()),
            'new_data' => null,
        ]);
        return response([
            'message' => 'Hardware deleted successfully',
        ], 200);
    }

    public function restore($hardwareId, Request $request) {
        // Soft delete: delete(); Hard delete: forceDelete();
        // Senza soft deleted ::find(1), o il metodo che si vuole; con soft deleted ::withTrashed()->find(1); 

        $user = $request->user();
        if (!$user->is_admin) {
            return response([
                'message' => 'You are not allowed to delete hardware',
            ], 403);
        }

        $hardware = Hardware::withTrashed()->findOrFail($hardwareId);
        if (!$hardware) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }
        $hardware->restore();
        HardwareAuditLog::create([
            'modified_by' => $user->id,
            'hardware_id' => $hardwareId,
            'log_subject' => 'hardware',
            'log_type' => 'restore',
            'old_data' => null,
            'new_data' => json_encode($hardware->toArray()),
        ]);
        return response([
            'message' => 'Hardware restored successfully',
        ], 200);
    }

    public function getHardwareTypes() {
        return HardwareType::all();
    }

    public function updateHardwareUsers(Request $request, Hardware $hardware) {
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

        if (!isEmpty($data['users']) && !$company) {
            return response([
                'message' => 'Hardware must be associated with a company to add users',
            ], 404);
        }

        if ($company && !isEmpty($data['users'])) {
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

        // Solo l'admin può rimuovere associazioni hardware-user
        if(!$authUser->is_admin && count($usersToRemove) > 0){
            return response([
                'message' => 'You are not allowed to remove users from hardware',
            ], 403);
        }

        // L'hardware ad uso sclusivo può essere associato a un solo utente
        if($hardware->is_exclusive_use && 
            (count($usersToAdd) > 0 && 
                // Qui forse basterebbe $request->users->count() > 1
                (($hardware->users->count() - count($usersToRemove) + count($usersToAdd)) > 1)
            )
        ) {
            return response([
                'message' => 'This hardware can be associated to only one user.',
            ], 400);
        }

        foreach ($usersToAdd as $userId) {
            $hardware->users()->attach($userId, [
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        foreach ($usersToRemove as $userId) {
            $hardware->users()->detach($userId);
        }

        return response([
            'message' => 'Hardware users updated successfully',
        ], 200);
    }

    public function deleteHardwareUser($hardwareId, $userId, Request $request) {
        $hardware = Hardware::findOrFail($hardwareId);
        $user = User::findOrFail($userId);

        if (!$hardware) {
            return response([
                'message' => 'Hardware not found',
            ], 404);
        }
        if (!$user) {
            return response([
                'message' => 'User not found',
            ], 404);
        }

        $authUser = $request->user();
        // Adesso può farlo solo l'admin
        // if (!$authUser->is_admin && !($authUser->is_company_admin && ($hardware->company_id == $authUser->company_id))) {
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to delete hardware-user associations.',
            ], 403);
        }

        if (!$hardware->users->contains($user)) {
            return response([
                'message' => 'User not associated with hardware',
            ], 400);
        }

        $hardware->users()->detach($userId);

        return response()->json(['message' => 'User detached from hardware successfully'], 200);
    }

    public function userHardwareList(Request $request, User $user) {
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

    public function fakeHardwareField(Request $request) {

        $faker = Faker::create();

        $fakeCompany = (object) [
            'id' => 1,
            'name' => $faker->word,
        ];

        // Genera dati fittizi per HardwareType
        $fakeHardwareTypes = collect(range(1, 5))->map(function ($index) use ($faker) {
            return (object) [
                'id' => $index,
                'name' => $faker->word,
            ];
        });

        // Genera dati fittizi per Hardware
        $fakeHardwareList = collect(range(1, 5))->map(function ($index) use ($faker, $fakeCompany, $fakeHardwareTypes) {
            $type = $fakeHardwareTypes->random();
            return [
                'id' => $index,
                'make' => $faker->word,
                'model' => $faker->word,
                'serial_number' => $faker->uuid,
                'company_id' => 1,
                'hardware_type_id' => $type->id,
                'created_at' => now(),
                'updated_at' => now(),
                'hardwareType' => [
                    'id' => $type->id,
                    'name' => $type->name,
                ],
                'company' => [
                    'id' => $fakeCompany->id,
                    'name' => $fakeCompany->name,
                ],
            ];
        });

        return response([
            'company' => $fakeCompany,
            'hardwareTypes' => $fakeHardwareTypes,
            'hardware' => $fakeHardwareList,
        ], 200);
    }

    public function hardwareTickets(Request $request, Hardware $hardware) {
        $authUser = $request->user();
        if (
            !$authUser->is_admin
            && !($authUser->is_company_admin && ($hardware->company_id == $authUser->company_id))
            && !($hardware->users->contains($authUser))
        ) {
            return response([
                'message' => 'You are not allowed to view this hardware tickets',
            ], 403);
        }

        if ($authUser->is_admin) {
            $tickets = $hardware->tickets()->with([
                'ticketType',
                'company',
                'user' => function ($query) {
                    $query->select('id', 'name', 'surname', 'email', 'is_admin', 'is_company_admin', 'company_id', 'is_deleted');
                }
            ])->get();
            return response([
                'tickets' => $tickets,
            ], 200);
        }

        // Non sappiamo se l'hardware può passare da un'azienda all'altra.
        if ($authUser->is_company_admin) {
            $tickets = $hardware->tickets()->where('company_id', $hardware->company_id)->with([
                'ticketType',
                'company',
                'user' => function ($query) {
                    $query->select('id', 'name', 'surname', 'email', 'is_admin', 'is_company_admin', 'company_id', 'is_deleted');
                }
            ])->get();
            return response([
                'tickets' => $tickets,
            ], 200);
        }

        // Qui devono vedersi tutti i ticket collegati a questo hardware, aperti dall'utente o in cui è associato come utente interessato (referer)
        if ($hardware->users->contains($authUser)) {
            $tickets = $hardware->tickets()
                ->with([
                    'ticketType',
                    'company',
                    'user' => function ($query) {
                        $query->select('id', 'name', 'surname', 'email', 'is_admin', 'is_company_admin', 'company_id', 'is_deleted');
                    }
                ])->get();

            $tickets = $tickets->filter(function ($ticket) use ($authUser) {
                return $ticket->user_id == $authUser->id || (!!$ticket->referer() && $ticket->referer()->id == $authUser->id);
            });

            $tickets = $tickets->values()->toArray();

            return response([
                'tickets' => $tickets,
            ], 200);
        }

        return response([
            'message' => 'You are not allowed to view this hardware tickets',
        ], 403);
    }

    public function exportTemplate() {
        $name = 'hardware_import_template_' . time() . '.xlsx';
        return Excel::download(new HardwareTemplateExport(), $name);
    }
    
    public function exportAssignationTemplate() {
        $name = 'hardware_assignation_template_' . time() . '.xlsx';
        return Excel::download(new HardwareAssignationTemplateExport(), $name);
    }
    
    public function exportDeletionTemplate() {
        $name = 'hardware_assignation_template_' . time() . '.xlsx';
        return Excel::download(new HardwareDeletionTemplateExport(), $name);
    }

    public function importHardware(Request $request) {

        $authUser = $request->user();
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to import hardware',
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $extension = $file->getClientOriginalExtension();

            if (!($extension === 'xlsx')) {
                return response([
                    'message' => 'Invalid file type. Please upload an XLSX or XLS file.',
                ], 400);
            }

            try {
                Excel::import(new HardwareImport($authUser), $file, 'xlsx');
            } catch (\Exception $e) {
                return response([
                    'message' => 'An error occurred while importing the file. Please check the file and try again.' . ($e->getMessage() ?? ''),
                    'error' => $e->getMessage(),
                ], 400);
            }
        }

        return response([
            'message' => "Success",
        ], 200);
    }
    
    public function importHardwareAssignations(Request $request) {

        $authUser = $request->user();
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to import hardware assignations',
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $extension = $file->getClientOriginalExtension();

            if (!($extension === 'xlsx')) {
                return response([
                    'message' => 'Invalid file type. Please upload an XLSX or XLS file.',
                ], 400);
            }

            try {
                Excel::import(new HardwareAssignationsImport($authUser), $file, 'xlsx');
            } catch (\Exception $e) {
                return response([
                    'message' => 'An error occurred while importing the file. Please check the file and try again.' . ($e->getMessage() ?? ''),
                    'error' => $e->getMessage(),
                ], 400);
            }
        }

        return response([
            'message' => "Success",
        ], 200);
    }
    
    public function importHardwareDeletions(Request $request) {

        $authUser = $request->user();
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to import hardware deletions',
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $extension = $file->getClientOriginalExtension();

            if (!($extension === 'xlsx')) {
                return response([
                    'message' => 'Invalid file type. Please upload an XLSX or XLS file.',
                ], 400);
            }

            try {
                Excel::import(new HardwareDeletionsImport($authUser), $file, 'xlsx');
            } catch (\Exception $e) {
                return response([
                    'message' => 'An error occurred while importing the file. Please check the file and try again.' . ($e->getMessage() ?? ''),
                    'error' => $e->getMessage(),
                ], 400);
            }
        }

        return response([
            'message' => "Success",
        ], 200);
    }

    public function downloadUserAssignmentPdf(Hardware $hardware, User $user, Request $request) {
        $authUser = $request->user();
        if (!$authUser->is_admin && !($authUser->is_company_admin && ($hardware->company_id == $authUser->company_id))) {
            return response([
                'message' => 'You are not allowed to download this document',
            ], 403);
        }

        if (!$hardware->users->contains($user)) {
            return response([
                'message' => 'User not associated with hardware',
            ], 400);
        }

        // $fileName = 'hardware_assignment_' . $hardware->id . '_to_' . $user->id . '.pdf';

        $name = 'hardware_user_assignment_' . $hardware->id . '_to_' . $user->id . '_' . time() . '.pdf';
        $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);

        $hardware->load(['hardwareType', 'company']);

        $relation = $hardware->users()->wherePivot('user_id', $user->id)->first();

        $data = [
            'title' => $name,
            'hardware' => $hardware,
            'user' => $user,
            'relation' => $relation,
        ];

        Pdf::setOptions([
            'dpi' => 150,
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true // ✅ Abilita il caricamento di immagini da URL esterni
        ]);

        $pdf = Pdf::loadView('pdf.hardwareuserassignment', $data);

        // return $pdf->stream();
        return $pdf->download($name);
    }

    public function getHardwareLog (Hardware $hardware, Request $request){
        $authUser = $request->user();
        // if (!$authUser->is_admin && !($authUser->is_company_admin && ($hardware->company_id == $authUser->company_id))) {
        if (!$authUser->is_admin) {
            return response([
                'message' => 'You are not allowed to view this hardware log',
            ], 403);
        }

        $logs = HardwareAuditLog::where('hardware_id', $hardware->id)->orWhere(function ($query) use ($hardware) {
            $query->whereJsonContains('old_data->id', $hardware->id)
                  ->orWhereJsonContains('new_data->id', $hardware->id);
        })
        ->with('author')
        ->get();

        return response([
            'logs' => $logs,
        ], 200);
    }

    public function hardwareLogsExport(Hardware $hardware) {
        $name = 'hardware_' . $hardware->id .'_logs_' . time() . '.xlsx';
        return Excel::download(new HardwareLogsExport($hardware->id), $name);
    }
}
