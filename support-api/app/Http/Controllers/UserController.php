<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    //

    public function me() {

        $user = auth()->user();

        return response([
            'user' => $user,
        ], 200);

    }

    public function ticketTypes(Request $request) {

        $user = $request->user();

        if($user["is_admin"] == 1){
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
    // Controlla se l'utente che fa la richiesta è admin, se lo è restituisce gli id degli admin, altrimenti restituisce null.
    public function adminsIds  (Request $request) {
        $isAdminRequest = $request->user()["is_admin"] == 1;

        if($isAdminRequest){
            $users = User::where('is_admin', 1)->get();
            $ids = $users->map(function($user) {
                return $user->id;
            });
        } else {
            $ids = null;
        }

        return response([
            'ids' => $ids,
        ], 200);

    }


}
