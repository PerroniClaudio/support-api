<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TicketsExport;
use App\Models\TicketReportExport;

class GenerateReport implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 360; // Timeout in seconds
    public $tries = 2; // Number of attempts

    private $report;

    /**
     * Create a new job instance.
     */
    public function __construct(TicketReportExport $report) {
        //
        $this->report = $report;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
        Excel::store(new TicketsExport($this->report->company_id, $this->report->start_date, $this->report->end_date, $this->report->id), 'exports/' . $this->report->company_id . '/' . $this->report->file_name, 'gcs');
        $this->report->is_generated = true;
        $this->report->save();
    }
}
