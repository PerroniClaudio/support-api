<?php

namespace App\Http\Controllers;

use App\Models\ActivationToken;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;


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

        $req_user = $request->user();

        // Se non è admin o non è della compagnia e company_admin allora non è autorizzato
        if(!($req_user["is_admin"] == 1 || ($req_user["company_id"] == $fields["company_id"] && $req_user["is_company_admin"] == 1))){
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

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
            'token' => Str::random(32),
            'uid' => $newUser['id'],
            'status' => 0,
        ]);

        // Inviare mail con url: frontendBaseUrl + /support/set-password/ + activation_token['token]

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
        ]);

        if(!$updated){
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
        if(!($req_user["is_admin"] == 1 || ($req_user["company_id"] == $fields["company_id"] && $req_user["is_company_admin"] == 1))){
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
    public function destroy($id, Request $request)
    {
        //
        $user = $request->user();

        if($user["is_admin"] == 1 && $id){
            $deleted_user = User::destroy($id);
        }

        if($deleted_user  == 0){
            return response([
                'message' => 'Error',
            ], 404);
        }
        return response([
            'deleted_user' => $id,
        ], 200);
    }

    public function ticketTypes(Request $request) {

        $user = $request->user();

        if ($user["is_admin"] == 1) {
            $ticketTypes = collect();
            foreach ($user->groups as $group) {
                $ticketTypes = $ticketTypes->concat($group->ticketTypes()->with('category')->get());
            }
        } else {
            $ticketTypes = $user->company->ticketTypes()->with('category')->get();
        }

        return response([
            'ticketTypes' => $ticketTypes,
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
            if(!$users) {
                $users = [];
            }
        } else {
            $users = [];
        }

        return response([
            'users' => $users,
        ], 200);
    }
}
