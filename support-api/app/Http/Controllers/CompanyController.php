<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Http\Request;

class CompanyController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        $isAdminRequest = $request->user()["is_admin"] == 1;

        if ($isAdminRequest) {
            $companies = Company::all();
            $companies->makeHidden(['sla', 'sla_take_low', 'sla_take_medium', 'sla_take_high', 'sla_take_critical', 'sla_solve_low', 'sla_solve_medium', 'sla_solve_high', 'sla_solve_critical', 'sla_prob_take_low', 'sla_prob_take_medium', 'sla_prob_take_high', 'sla_prob_take_critical', 'sla_prob_solve_low', 'sla_prob_solve_medium', 'sla_prob_solve_high', 'sla_prob_solve_critical']);

            if (!$companies) {
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
    public function create() {
        //
        return response([
            'message' => 'Please use /api/store to create a new company',
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        //
        $fields = $request->validate([
            'name' => 'required|string',
        ]);

        $user = $request->user();

        if ($user["is_admin"] != 1) {
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
    public function show($id, Request $request) {
        $user = $request->user();

        if ($user["is_admin"] != 1 && $user["company_id"] != $id) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $company = Company::where('id', $id)->first();

        if (!$company) {
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
    public function edit(Company $company) {
        //
        return response([
            'message' => 'Please use /api/update to update an existing company',
        ], 404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request) {
        $request->validate([
            'id' => 'required|int|exists:companies,id',
        ]);

        $user = $request->user();

        if (!$user['is_admin']) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $company = Company::findOrFail($request->id);

        $updatedFields = $request->only($company->getFillable());
        $company->update($updatedFields);

        return response(['company' => $company], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id) {
        $user = $request->user();

        if (!$user["is_admin"]) {
            return response(['message' => 'Unauthorized',], 401);
        }

        // If it has users throw an error 

        if (Company::findOrFail($id)->tickets()->count() > 0) {
            return response([
                'message' => 'tickets',
            ], 400);
        }

        if (Company::findOrFail($id)->ticketTypes()->count() > 0) {
            return response([
                'message' => 'ticket-types',
            ], 400);
        }

        if (Company::findOrFail($id)->users()->count() > 0) {
            return response([
                'message' => 'users',
            ], 400);
        }

        if (Company::findOrFail($id)->offices()->count() > 0) {
            return response([
                'message' => 'offices',
            ], 400);
        }


        $deleted_company = Company::destroy($id);

        if (!$deleted_company) {
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
        if (!($user["is_admin"] == 1 || $user["company_id"] == $company["id"])) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }
        // Esclude gli utenti disabilitati
        $users = $company->users()->where('is_deleted', false)->get();
        $users->makeHidden('microsoft_token');

        return response([
            'users' => $users,
        ], 200);
    }

    public function ticketTypes(Company $company, Request $request) {
        $isMassive = $request->query('is_massive');
        if ($isMassive) {
            $ticketTypes = $company->ticketTypes()->where('is_massive_enabled', 1)->with('category')->get();
        } else {
            $ticketTypes = $company->ticketTypes()->where('is_massive_enabled', 0)->with('category')->get();
        }

        return response([
            'companyTicketTypes' => $ticketTypes,
        ], 200);
    }

    public function brands(Company $company) {
        $brands = $company->brands()->each(function (Brand $brand) {
            $brand->withGUrl();
        });

        $brandsArray = array();
        foreach ($brands as $brand) {
            $brandsArray[] = $brand;
        }

        return response([
            'brands' => $brandsArray,
        ], 200);
    }

    public function getFrontendLogoUrl(Company $company) {
        $suppliers = Supplier::all()->toArray();

        // Prendi tutti i brand dei tipi di ticket associati all'azienda dell'utente
        $brands = $company->brands()->toArray();

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

    public function tickets(Company $company, Request $request) {
        $user = $request->user();
        if ($user["is_admin"] != 1 && $user["company_id"] != $company["id"]) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $tickets = $company->tickets()->with(['ticketType'])->orderBy('created_at', 'desc')->get();

        if ($user["is_admin"] != 1) {
            foreach ($tickets as $ticket) {
                $ticket->makeHidden(["admin_user_id", "group_id", "priority", "is_user_error", "actual_processing_time"]);
            }
        }

        return response([
            'tickets' => $tickets,
        ], 200);
    }

    // Orari azienda

    public function getWeeklyTimes(Company $company) {
        $weeklyTimes = $company->weeklyTimes()->get();

        if ($weeklyTimes->count() == 0) {

            $weeklyTimes = [];

            // Se non sono stati impostati generali di default con orario 09:00 - 18:00

            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

            foreach ($days as $day) {
                $weeklyTimes[] = [
                    'day' => $day,
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                ];
            }

            // Sabato e domenica di default vengono inseriti a 00:00 - 00:00

            $weeklyTimes[] = [
                'day' => 'saturday',
                'start_time' => '00:00',
                'end_time' => '00:00',
            ];

            $weeklyTimes[] = [
                'day' => 'sunday',
                'start_time' => '00:00',
                'end_time' => '00:00',
            ];

            foreach ($weeklyTimes as $weeklyTime) {
                $company->weeklyTimes()->create($weeklyTime);
            }
        }

        $weeklyTimes = $company->weeklyTimes()->get();

        return response([
            'weeklyTimes' => $weeklyTimes,
        ], 200);
    }

    public function editWeeklyTime(Request $request) {
        $request->validate([
            'company_id' => 'required|int|exists:companies,id',
            'day' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user['is_admin']) {
            return response(['message' => 'Unauthorized'], 401);
        }

        $weeklyTime = Company::findOrFail($request->company_id)->weeklyTimes()->where('day', $request->day)->first();

        $weeklyTime->update([
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        return response(['weeklyTime' => $weeklyTime], 200);
    }
}
