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


}
