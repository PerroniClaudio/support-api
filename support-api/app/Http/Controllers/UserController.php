<?php

namespace App\Http\Controllers;

use App\Exports\UserTemplateExport;
use App\Imports\UsersImport;
use App\Models\ActivationToken;
use App\Jobs\SendWelcomeEmail;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Supplier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use PragmaRX\Google2FA\Google2FA;

class UserController extends Controller {
    //

    public function me() {

        $user = auth()->user();

        return response([
            'user' => $user,
        ], 200);
    }

    public function store(Request $request) {
        $fields = $request->validate([
            'company_id' => 'required|int',
            'name' => 'required|string',
            'email' => 'required|string',
            'surname' => 'required|string',
        ]);

        $requestUser = $request->user();

        if (!($requestUser["is_admin"] == 1 || ($requestUser["company_id"] == $fields["company_id"] && $requestUser["is_company_admin"] == 1))) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Se si modifica qualcosa da questo punto in poi bisogna modificare anche in UsersImport.php
        $newUser = User::create([
            'company_id' => $fields['company_id'],
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => Hash::make(Str::password()),
            'surname' => $fields['surname'],
            'phone' => $request['phone'] ?? null,
            'city' => $request['city'] ?? null,
            'zip_code' => $request['zip_code'] ?? null,
            'address' => $request['address'] ?? null,
            'is_company_admin' => $request['is_company_admin'] ?? 0,
        ]);

        $activation_token = ActivationToken::create([
            // 'token' => Hash::make(Str::random(32)),
            'token' => Str::random(20) . time(),
            'uid' => $newUser['id'],
            'status' => 0,
        ]);

        // Inviare mail con url: frontendBaseUrl + /support/set-password/ + activation_token['token]
        $url = env('FRONTEND_URL') . '/support/set-password/' . $activation_token['token'];
        dispatch(new SendWelcomeEmail($newUser, $url));

        return response([
            'user' => $newUser,
        ], 201);
    }


    /**
     * Attiva l'utenza assegnandogli la password scelta.
     */
    public function activateUser(Request $request) {
        $fields = $request->validate([
            'token' => 'required|string|exists:activation_tokens,token',
            'email' => 'required|string|exists:users,email',
            'password'  => 'required|string',
        ]);

        $user = User::where('email', $request['email'])->first();

        // Per non far sapere che l'utente esiste si può modificare in unauthorized
        if (!$user) {
            return response([
                // 'message' => 'User not found',
                'message' => 'Unauthorized',
            ], 404);
        }

        if ($user['is_deleted'] == 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        // l'activation token nel db deve avere token, uid, status = 0
        $token = ActivationToken::where('token', $request['token'])->first();
        if ($token['uid'] != $user['id'] || $token['used'] != 0) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Controllare se la password rispetta i requisiti e poi aggiornare la password dell'utente
        $password = $fields['password'];
        $pattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{10,}$/";

        if (!preg_match($pattern, $password)) {
            return response([
                'message' => 'Invalid password',
            ], 400);
        }

        $updated = $user->update([
            'password' => Hash::make($fields['password']),
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            return response([
                'message' => 'Error',
            ], 404);
        }

        $token->update([
            'used' => 1,
        ]);

        Auth::login($user);

        return response([
            'user' => $user,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request) {
        $fields = $request->validate([
            'id' => 'required|int|exists:users,id', // TODO: 'id' => 'required|int|exists:users,id
            'company_id' => 'required|int',
            'name' => 'required|string',
            'email' => 'required|string',
            'surname' => 'required|string',
        ]);

        $req_user = $request->user();

        // Se non è admin o non è della compagnia e company_admin allora non è autorizzato
        if (!($req_user["is_admin"] == 1 || ($req_user["company_id"] == $fields["company_id"] && $req_user["is_company_admin"] == 1))) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $user = User::where('id', $request['id'])->first();

        if (!$user) {
            return response([
                'message' => 'User not found',
            ], 404);
        }

        $updatedFields = [];

        $userFields = $user->getFillable();

        foreach ($request->all() as $fieldName => $fieldValue) {
            if (in_array($fieldName, $userFields)) {
                $updatedFields[$fieldName] = $fieldValue;
            }
        }

        $user->update([
            'is_company_admin' => $updatedFields['is_company_admin'],
            'company_id' => $updatedFields['company_id'],
            'name' => $updatedFields['name'],
            'surname' => $updatedFields['surname'],
            'email' => $updatedFields['email'],
            'phone' => $updatedFields['phone'],
            'address' => $updatedFields['address'],
            'city' => $updatedFields['city'],
            'zip_code' => $updatedFields['zip_code'],
            // 'password' => $updatedFields['password'] ?? $user->password,
        ]);

        return response([
            'user' => $user,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, Request $request) {
        //Solo gli admin possono eliminare (disabilitare) le utenze
        $req_user = $request->user();

        // if ($req_user["is_admin"] == 1 && $id) {
        //     // Se l'utente ha ticket associati si disabilita l'utente, senza eliminarlo.
        //     $user = User::where('id', $id)->first();
        //     if($user->tickets()->count() > 0){
        //         $disabled = $user->update([
        //             'is_deleted' => true,
        //         ]);
        //         if($disabled){
        //             return response([
        //                 'deleted_user' => $id,
        //                 'message' => 'tickets',
        //             ], 200);
        //         }
        //         return response([
        //             'message' => 'Error',
        //         ], 400);
        //     }

        //     $deleted_user = User::destroy($id);
        // }

        // if ($deleted_user  == 0) {
        //     return response([
        //         'message' => 'Error',
        //     ], 404);
        // }
        // return response([
        //     'deleted_user' => $id,
        // ], 200);

        if ($req_user["is_admin"] == 1 && $id) {
            // In ogni caso si disabilita l'utente, senza eliminarlo.
            $user = User::where('id', $id)->first();
            $disabled = $user->update([
                'is_deleted' => true,
            ]);
            if ($disabled) {
                return response([
                    'deleted_user' => $id,
                ], 200);
            }
            return response([
                'message' => 'Error',
            ], 400);
        }
    }

    // Riabilitare utente disabilitato
    public function enable($id, Request $request) {
        $req_user = $request->user();

        if ($req_user["is_admin"] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }
        if (!$id) {
            return response([
                'message' => 'Error',
            ], 404);
        }

        $user = User::where('id', $id)->first();
        $enabled = $user->update([
            'is_deleted' => 0,
        ]);
        if (!$enabled) {
            return response([
                'message' => 'Error',
            ], 404);
        }
        return response([
            'enabled_user' => $id,
        ], 200);
    }

    public function ticketTypes(Request $request) {

        $user = $request->user();

        // Se l'utente è admin allora prende tutti i ticket types di tutti i gruppi associati all'utente, altrimenti solo quelli della sua compagnia
        if ($user["is_admin"] == 1) {
            $ticketTypes = collect();
            foreach ($user->groups as $group) {
                $ticketTypes = $ticketTypes->concat($group->ticketTypes()->with('category')->get());
            }
        } else {
            $ticketTypes = $user->company->ticketTypes()->where("is_custom_group_exclusive", false)->with('category')->get();

            $customGroups = $user->customUserGroups()->get();
            foreach ($customGroups as $customGroup) {
                $ticketTypes = $ticketTypes->concat($customGroup->ticketTypes()->with('category')->get());
            }
        }

        return response([
            'ticketTypes' => $ticketTypes
        ], 200);
    }

    // public function adminTicketTypes(Request $request) {

    //     $user = $request->user();

    //     if($user["is_admin"] == 1){
    //         $ticketTypes = collect();
    //         foreach ($user->groups as $group) {
    //             $ticketTypes = $ticketTypes->concat($group->ticketTypes()->with('category')->get());
    //         }
    //     }

    //     return response([
    //         'ticketTypes' => $ticketTypes || [],
    //     ], 200);

    // }

    public function test(Request $request) {

        return response([
            'test' => $request,
        ], 200);
    }

    // Restituisce gli id degli admin (serve per vedere se un messaggio va mostrato come admin o meno).
    // Controlla se l'utente che fa la richiesta è admin, se lo è restituisce gli id degli admin, altrimenti restituisce [].
    public function adminsIds(Request $request) {
        $isAdminRequest = $request->user()["is_admin"] == 1;

        if ($isAdminRequest) {
            $users = User::where('is_admin', 1)->get();
            $ids = $users->map(function ($user) {
                return $user->id;
            });
        } else {
            $ids = [];
        }

        return response([
            'ids' => $ids,
        ], 200);
    }

    public function allAdmins(Request $request) {
        $isAdminRequest = $request->user()["is_admin"] == 1;

        if ($isAdminRequest) {
            $users = User::where('is_admin', 1)->get();
        } else {
            $users = null;
        }

        return response([
            'admins' => $users,
        ], 200);
    }

    public function allUsers(Request $request) {
        $isAdminRequest = $request->user()["is_admin"] == 1;
        if ($isAdminRequest) {
            $users = User::all();
            $users->makeHidden(['microsoft_token']);
            if (!$users) {
                $users = [];
            }
        } else {
            $users = [];
        }

        return response([
            'users' => $users,
        ], 200);
    }

    public function getName($id, Request $request) {
        $user = User::where('id', $id)->first();

        if (!$request->user()["is_admin"] && ($user["company_id"] != $request->user()["company_id"]) && $user->company->data_owner_email != $request->user()->email) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        return response([
            'name' => $user["name"] . " " . $user["surname"],
        ], 200);
    }

    public function getFrontendLogoUrl(Request $request) {
        $suppliers = Supplier::all()->toArray();

        // Prendi tutti i brand dei tipi di ticket associati all'azienda dell'utente
        $brands = $request->user()->company->brands()->toArray();

        // Filtra i brand omonimo alle aziende interne ed utilizza quello dell'azienda interna con l'id piu basso
        $sameNameSuppliers = array_filter($suppliers, function ($supplier) use ($brands) {
            $brandNames = array_column($brands, 'name');
            return in_array($supplier['name'], $brandNames);
        });

        $selectedBrand = '';

        // Se ci sono aziende interne allora prende quella con l'id più basso e recupera il marchio omonimo, altrimenti usa il marchio con l'id più basso.
        if (!empty($sameNameSuppliers)) {
            usort($sameNameSuppliers, function ($a, $b) {
                return $a['id'] <=> $b['id'];
            });
            $selectedSupplier = reset($sameNameSuppliers);
            $selectedBrand = array_values(array_filter($brands, function ($brand) use ($selectedSupplier) {
                return $brand['name'] === $selectedSupplier['name'];
            }))[0];
        } else {
            usort($brands, function ($a, $b) {
                return $a['id'] <=> $b['id'];
            });

            $selectedBrand = reset($brands);
        }

        // Crea l'url
        $url = config('app.url') . '/api/brand/' . $selectedBrand['id'] . '/logo';

        // $url = $request->user()->company->frontendLogoUrl;

        return response([
            'urlLogo' => $url,
        ], 200);
    }

    public function exportTemplate() {
        $name = 'users_import_template_' . time() . '.xlsx';
        return Excel::download(new UserTemplateExport(), $name);
    }

    public function importUsers(Request $request) {
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

            Excel::import(new UsersImport, $file, 'xlsx');
        }

        return response([
            'message' => "Success",
        ], 200);
    }

    public function twoFactorChallenge(Request $request) {
        $user = Auth::user();

        $google2fa = new Google2FA();
        $secret = decrypt($user->two_factor_secret);

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response([
                'message' => 'Invalid code',
            ], 401);
        }

        return response([
            'success' => true,
        ], 200);
    }
}
