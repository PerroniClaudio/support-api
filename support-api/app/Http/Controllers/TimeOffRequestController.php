<?php

namespace App\Http\Controllers;

use App\Models\TimeOffRequest;
use App\Models\TimeOffType;
use Illuminate\Http\Request;

class TimeOffRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        $requests = TimeOffRequest::with(['type', 'user'])->where('status', '<>', '4')->orderBy('id', 'asc')->get();
        
        $formattedRequests = [];
        $currentRequest = null;

        foreach( $requests as $key => $req ) {

            if($req->time_off_type_id == 11) {

                if($currentRequest) {
                    $currentRequest['date_to'] = $req->date_to;

                    if(isset($requests[$key + 1])) {
                        $nextBatchId = $requests[$key + 1]['batch_id'];
                        $currentBatchId = $req->batch_id;

                        if($nextBatchId != $currentBatchId) {
                            
                            $formattedRequests[] = $currentRequest;
                            $currentRequest = null;
                        }
                    } else {
                      
                        $formattedRequests[] = $currentRequest;
                        $currentRequest = null;
                    }
                    

                } else {
                    $currentRequest = $req;
                }

            } else {
                $formattedRequests[] = $req;
                $currentRequest = null;
            }

        }

        return response([
            'requests' => $formattedRequests,
           
        ]);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //

        $user = $request->user();

        $fields = $request->validate([
            'date_from' => 'required|string',
            'date_to' => 'required|string',
            'company_id' => 'required|int',
            'time_off_type_id' => 'required|int',
            'description' => 'required|string',
        ]);

        // L'orario di fine non può essere maggiore di quello di inizio

        if(strtotime($fields['date_to']) < strtotime($fields['date_from'])) {

            return response([
                'message' => 'La data di fine non può essere maggiore di quella di inizio',
            ], 400);

        }

        $fields['user_id'] = $user->id;

        $request = TimeOffRequest::create($fields);

        return response([
            'request' => $request
        ]);
    }

    public function storeBatch(Request $request) {

        $user = $request->user();

        // Controlla una per una che siano valide 

        $requests = json_decode($request->requests);

        $batch_id = uniqid();

        foreach( $requests as $time_off_request ) {

            $fields = [
                'date_from' => $time_off_request->date_from,
                'date_to' => $time_off_request->date_to,
                'time_off_type_id' => $time_off_request->time_off_type_id,
            ];

            $fields['user_id'] = $user->id;
            $fields['company_id'] = $user->company_id;
            $fields['batch_id'] = $batch_id;

            $request = TimeOffRequest::create($fields);

        }

        return response([
            'message' => 'Richieste di permesso create con successo'
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(TimeOffRequest $timeOffRequest)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TimeOffRequest $timeOffRequest)
    {
        //

         
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TimeOffRequest $timeOffRequest)
    {
        //

        $fields = $request->validate([
            'date_from' => 'required|string',
            'date_to' => 'required|string',
            'company_id' => 'required|int',
            'time_off_type_id' => 'required|int',
            'description' => 'required|string',
        ]);

        $timeOffRequest->update($fields);

        return response([
            'message' => 'Richiesta di permesso aggiornata con successo'
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TimeOffRequest $timeOffRequest)
    {
        //

        $timeOffRequest->update([
            'status' => '4'
        ]);

        return response([
            'message' => 'Richiesta di permesso cancellata con successo'
        ], 200);

    }

    public function types() {

        $types = TimeOffType::all();

        return response([
            'types' => $types
        ]);

    }
}
