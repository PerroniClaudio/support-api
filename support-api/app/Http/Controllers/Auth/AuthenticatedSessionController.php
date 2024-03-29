<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class AuthenticatedSessionController extends Controller {
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): Response {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response([
                'message' => 'Utente inesistente',
            ], 401);
        }

        if ($user['email_verified_at'] == null) {
            return response([
                'message' => 'Utenza non attivata. seguire le indicazioni nella mail di attivazione.',
            ], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response([
                'message' => 'Le credenziali non corrispondono',
            ], 401);
        }
        
        if ($user['is_deleted'] == 1) {
            return response([
                'message' => 'Utente disabilitato',
            ], 401);
        }

        $request->authenticate();

        $request->session()->regenerate();

        return response()->noContent();
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function storeMicrosoft(Request $request): Response {

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->token),
                'microsoft_token' => $request->token,
                'is_admin' => true,
            ]);

            event(new Registered($user));
        }

        Auth::login($user);

        $request->session()->regenerate();

        return response()->noContent();
    }
}
