<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        
        $user = $request->user();
    
        $attendances = Attendance::where('user_id', $user->id)->with(['company', 'attendanceType'])->orderBy('id', 'desc')->get();
 
        return response()->json($attendances);

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
            'date' => 'required|string',
            'time_in' => 'required|string',
            'time_out' => 'required|string',
            'company_id' => 'required|int',
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'company_id' => $fields['company_id'],
            'date' => $fields['date'],
            'time_in' => $fields['time_in'],
            'time_out' => $fields['time_out'],
        ]);

        return response([
            'attendance' => $attendance,
        ], 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(Attendance $attendance)
    {
        
        return response()->json($attendance);

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Attendance $attendance)
    {
        //

       

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Attendance $attendance)
    {
        //

        $user = $request->user();

        $fields = $request->validate([
            'date' => 'required|string',
            'time_in' => 'required|string',
            'time_out' => 'required|string',
            'company_id' => 'required|int',
        ]);

        $attendanceUpdated = Attendance::update([
            'user_id' => $user->id,
            'company_id' => $fields['company_id'],
            'date' => $fields['date'],
            'time_in' => $fields['time_in'],
            'time_out' => $fields['time_out'],
        ]);

        return response([
            'attendance' => $attendanceUpdated,
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attendance $attendance)
    {
        //

        $attendance->delete();

        return response([
            'message' => 'Attendance deleted successfully',
        ], 200);
    }
}
