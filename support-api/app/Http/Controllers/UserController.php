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

        $ticketTypes = $user->company->ticketTypes;

        return response([
            'ticketTypes' => $ticketTypes,
        ], 200);

    }


}
