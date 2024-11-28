<?php

namespace App\Jobs;

use App\Exports\UserReportExport;
use App\Models\TicketReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class GenerateUserReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $report;

    /**
     * Create a new job instance.
     */
    public function __construct(TicketReportExport $report)
    {
        //

        $this->report = $report;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //

        Excel::store(new UserReportExport($this->report), $this->report->file_path, 'gcs');
        $this->report->is_generated = true;
        $this->report->save();

    }
}
