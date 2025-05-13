<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\GeneratePdfReport;
use App\Models\Company;
use App\Models\TicketReportPdfExport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TicketReportPdfExportController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        //
    }

    /**
     * Lista per company singola
     */
    
    public function pdfCompany(Company $company, Request $request) {
        $user = $request->user();
        if ($user["is_admin"] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $reports = TicketReportPdfExport::where('company_id', $company->id)
            // ->where('is_generated', true)
            ->orderBy('created_at', 'DESC')
            ->get();

        return response([
            'reports' => $reports,
        ], 200);
    }

    public function generic() {
        
    }

    /**
     * Lista per utente singolo
     */

    public function pdfUser(Request $request, User $user) {
        if ($user["is_admin"] != 1 && $user["is_company_admin"] != 1) {
            return response([
                'message' => 'The user must be at least company admin.',
            ], 401);
        }
        if($user["is_company_admin"] == 1 && $user["company_id"] != $user->company_id) {
            return response([
                'message' => 'You can\'t see company reports.',
            ], 401);
        }

        $user = $request->user();

        // Per ora non vengono ancora generati dall'utente, quindi qui si deve ancora decidere come prenderli.
        // penso tutti quelli dell'azienda dell'utente, generati da loro.
        $reports = TicketReportPdfExport::where('company_id', $user->company_id)
            ->where('is_user_generated', true)
            ->orderBy('created_at', 'DESC')
            ->get();

        return response([
            'reports' => $reports,
        ], 200);
    }

    /**
     * Lista report approvati come billing
     */

    public function pdfBilling(Request $request) {
        $user = $request()->user();
        if ($user["is_admin"] != 1 && $user["is_company_admin"] != 1) {
            return response([
                'message' => 'The user must be at least company admin.',
            ], 401);
        }
        if($user["is_company_admin"] == 1 && $user["company_id"] != $user->company_id) {
            return response([
                'message' => 'You can\'t see company reports.',
            ], 401);
        }

        $reports = TicketReportPdfExport::where('company_id', $user->company_id)
            ->where('is_approved_billing', true)
            ->orderBy('created_at', 'DESC')
            ->get();

        return response([
            'reports' => $reports,
        ], 200);
    }

    /**
     * Nuovo report
     */

    public function storePdfExport(Request $request) {

        try {
            $user = $request->user();
            if ($user["is_admin"] != 1 && $user["is_company_admin"] != 1) {
                return response([
                    'message' => 'The user must be at least company admin.',
                ], 401);
            }
            
            $company = Company::find($request->company_id);

            // $name = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($company->name)) . '_' . time() . '_' . $request->company_id . '_tickets.pdf';
            $name = time() . '_' . $request->company_id . '_tickets.pdf';

            // $file =  Excel::store(new TicketsExport($company, $request->start_date, $request->end_date), 'exports/' . $request->company_id . '/' . $name, 'gcs');

            $report = TicketReportPdfExport::create([
                'company_id' => $company->id,
                'file_name' => $name,
                'file_path' => 'pdf_exports/' . $request->company_id . '/' . $name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'optional_parameters' => json_encode($request->optional_parameters),
                'user_id' => $user->id,
            ]);

            dispatch(new GeneratePdfReport($report));
            
            return response ([
                'message' => 'Report created successfully',
                'report' => $report
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error generating the report',
                'error' => $e->getMessage()
            ], 500);
        }
        
    }
    
    /**
     * Preview (restituisce il link generato da google cloud storage)
     */

    public function pdfPreview(TicketReportPdfExport $ticketReportPdfExport, Request $request) {

        $user = $request->user();
        if ($user["is_admin"] != 1 && $user["is_company_admin"] != 1) {
            return response([
                'message' => 'The user must be at least company admin.',
            ], 401);
        }
        if($user["is_company_admin"] == 1 && $user["company_id"] != $ticketReportPdfExport->company_id) {
            return response([
                'message' => 'You can\'t download this report.',
            ], 401);
        }

        $url = $this->generatedSignedUrlForFile($ticketReportPdfExport->file_path);

        return response([
            'url' => $url,
            'filename' => $ticketReportPdfExport->file_name
        ], 200);
    }

    /**
     * Download (restituisce il file)
     */

    public function pdfDownload(TicketReportPdfExport $ticketReportPdfExport, Request $request) {

        $user = $request->user();
        if ($user["is_admin"] != 1 && $user["is_company_admin"] != 1) {
            return response([
                'message' => 'The user must be at least company admin.',
            ], 401);
        }
        if($user["is_company_admin"] == 1 && $user["company_id"] != $ticketReportPdfExport->company_id) {
            return response([
                'message' => 'You can\'t download this report.',
            ], 401);
        }

        $filePath = $ticketReportPdfExport->file_path;

        if (!Storage::disk('gcs')->exists($filePath)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        $fileContent = Storage::disk('gcs')->get($filePath);
        $fileName = $ticketReportPdfExport->file_name;

        return response($fileContent, 200)
            ->header('Content-Type', Storage::disk('gcs')->mimeType($filePath))
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }

    /**
     * Genera il link temporaneo per il file
     * @param string $path
     * @return string
     */
    
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
    public function show(TicketReportPdfExport $ticketReportPdfExport) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketReportPdfExport $ticketReportPdfExport) {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketReportPdfExport $ticketReportPdfExport) {
        //
    }

}
