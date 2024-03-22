<?php

namespace App\Http\Controllers;

use App\Models\TicketReportExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TicketsExport;
use App\Models\Company;

class TicketReportExportController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TicketReportExport $ticketReportExport) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketReportExport $ticketReportExport) {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketReportExport $ticketReportExport) {
        //
    }

    /**
     * Export the specified resource from storage.
     */

    public function export(Request $request) {

        $company = Company::find($request->company_id);
        $file =  Excel::store(new TicketsExport($company), 'exports/tickets.xlsx', 'gcs');

        return response()->json(['file' => $file]);
    }
}
