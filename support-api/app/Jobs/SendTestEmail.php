<?php

namespace App\Jobs;

use App\Mail\TestEmail;
use App\Mail\WelcomeEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTestEmail implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $url;


    /**
     * Create a new job instance.
     */
    public function __construct($email, $url) {
        //
        $this->email = $email;
        $this->url = $url;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
        //
        Mail::to($this->email)->send(new TestEmail($this->email, $this->url));
    }
}
