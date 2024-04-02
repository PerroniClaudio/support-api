<?php

namespace App\Http\Controllers;

use App\Models\TicketReportExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TicketsExport;
use App\Jobs\GenerateReport;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketStatusUpdate;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class TicketReportExportController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        //
    }

    /**
     * Lista per company singola
     */

    public function company(Company $company) {
        $reports = TicketReportExport::where('company_id', $company->id)->where(
            'is_generated',
            true
        )->get();

        return response([
            'reports' => $reports,
        ], 200);
    }

    public function download(TicketReportExport $ticketReportExport) {

        $url = $this->generatedSignedUrlForFile($ticketReportExport->file_path);


        return response([
            'url' => $url,
            'filename' => $ticketReportExport->file_name
        ], 200);
    }

    private function generatedSignedUrlForFile($path) {

        /**
         * @disregard P1009 Undefined type
         */

        $url = Storage::disk('gcs')->temporaryUrl(
            $path,
            now()->addMinutes(65)
        );

        return $url;
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

        $name = time() . '_' . $request->company_id . '_tickets.xlsx';

        $company = Company::find($request->company_id);
        // $file =  Excel::store(new TicketsExport($company, $request->start_date, $request->end_date), 'exports/' . $request->company_id . '/' . $name, 'gcs');


        $report = TicketReportExport::create([
            'company_id' => $company->id,
            'file_name' => $name,
            'file_path' => 'exports/' . $request->company_id . '/' . $name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'optional_parameters' => json_encode($request->optional_parameters)
        ]);

        dispatch(new GenerateReport($report));


        return response()->json(['file' => $name]);
    }

    public function exportpdf(Ticket $ticket) {

        $name = time() . '_' . $ticket->id . '_tickets.xlsx';
        //? Webform

        $webform_data = json_decode($ticket->messages()->first()->message);

        $office = $ticket->company->offices()->where('id', $webform_data->office)->first();
        $webform_data->office = $office ? $office->name : null;

        if (isset($webform_data->referer)) {
            $referer = User::find($webform_data->referer);
            $webform_data->referer = $referer ? $referer->name . " " . $referer->surname : null;
        }

        if (isset($webform_data->referer_it)) {
            $referer_it = User::find($webform_data->referer_it);
            $webform_data->referer_it = $referer_it ? $referer_it->name . " " . $referer_it->surname : null;
        }

        //? Avanzamento

        $avanzamento = [
            "attesa" => 0,
            "assegnato" => 0,
            "in_corso" => 0,
        ];

        foreach ($ticket->statusUpdates as $update) {
            if ($update->type == 'status') {

                if (strpos($update->content, 'In attesa') !== false) {
                    $avanzamento["attesa"]++;
                }
                if (strpos($update->content, 'Assegnato') !== false) {
                    $avanzamento["assegnato"]++;
                }
                if (strpos($update->content, 'In corso') !== false) {
                    $avanzamento["in_corso"]++;
                }
            }
        }

        //? Chiusura

        $closingMessage = "";

        $closingUpdates = TicketStatusUpdate::where('ticket_id', $ticket->id)->where('type', 'closing')->get();
        $closingUpdate = $closingUpdates->last();

        if ($closingUpdate) {
            $closingMessage = $closingUpdate->content;
        }

        $data = [
            'title' => $name,
            'ticket' => $ticket,
            'webform_data' => $webform_data,
            'status_updates' => $avanzamento,
            'closing_messages' => $closingMessage,

        ];

        Pdf::setOptions(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        $pdf = Pdf::loadView('pdf.export', $data);

        return $pdf->stream();
    }

    public function exportBatch(Request $request) {
        $cacheKey = 'batch_report_' . $request->company_id . '_' . $request->from . '_' . $request->to;
        $company = Company::find($request->company_id);
        $tickets_data = Cache::get($cacheKey);


        $tickets_by_day = [];
        $ticket_graph_data = [];

        foreach ($tickets_data as $ticket) {
            $date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->format('Y-m-d');
            if (!isset($tickets_by_day[$date])) {
                $tickets_by_day[$date] = [];
            }
            $tickets_by_day[$date][] = $ticket;
        }

        for($date = \Carbon\Carbon::createFromFormat('Y-m-d', $request->from); $date <= \Carbon\Carbon::createFromFormat('Y-m-d', $request->to); $date->addDay()) {
            if (isset($tickets_by_day[$date->format('Y-m-d')])) {

                $incidents = 0;
                $requests = 0;
                
                foreach($tickets_by_day[$date->format('Y-m-d')] as $ticket) {
                    if($ticket['data']['ticketType']['category']['is_problem'] == 1) {
                        $incidents++;
                    } else {
                        $requests++;
                    }
                }

                $ticket_graph_data[$date->format('Y-m-d')] = [
                    'incidents' => $incidents,
                    'requests' => $requests
                ];
            }
        }


        $data = [
            'tickets' => $tickets_data,
            'title' => "Esportazione tickets",
            'date_from' => \Carbon\Carbon::createFromFormat('Y-m-d', $request->from),
            'date_to' =>\Carbon\Carbon::createFromFormat('Y-m-d', $request->to),
            'company' => $company,
            'ticket_graph_data' => $ticket_graph_data
        ];





        Pdf::setOptions(['dpi' => 150, 'defaultFont' => 'sans-serif']);
        $pdf = Pdf::loadView('pdf.exportbatch', $data);

        return $pdf->stream();

    }
}
