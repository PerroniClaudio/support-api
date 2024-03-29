<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;

class TicketsExport implements FromArray {

    private $company_id;
    private $start_date;
    private $end_date;
    private $job_id;

    public function __construct($company_id, $start_date, $end_date) {
        $this->company_id = $company_id;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function array(): array {

        // $tickets = Ticket::where('company_id', $this->company->id)->whereBetween('created_at', [now()->subDays(30)->startOfMonth(), now()->subDays(30)->endOfMonth()])->get();
        $tickets = Ticket::where('company_id', $this->company_id)->whereBetween('created_at', [
            $this->start_date,
            $this->end_date
        ])->get();

        $ticket_data = [];
        $headers = ["ID", "Autore", "Referente", "Data", "Tipologia", "Webform", "Chiusura", "Tempo in attesa (ore)", "Numero di volte in attesa"];

        foreach ($tickets as $ticket) {

            $messages = $ticket->messages;
            $webform = json_decode($messages->first()->message, true);
            $webform_text = "";

            foreach ($webform as $key => $value) {
                $webform_text .= $key . ": " . $value . "\n";
            }

            if (isset($webform->referer)) {
                $referer = User::find($webform->referer);
                $webform->referer = $referer ? $referer->name . " " . $referer->surname : null;
            }

            $this_ticket = [
                $ticket->id,
                $ticket->user->name . " " . $ticket->user->surname,
                $webform->referer,
                $ticket->created_at,
                $ticket->ticketType->name,
                $webform_text,
                $ticket->created_at,
                $ticket->waitingHours(),
                $ticket->waitingTimes()
            ];



            foreach ($ticket->messages as $message) {

                if ($message == $ticket->messages->first()) {
                    continue;
                }

                $this_ticket[] = $message->created_at;
                $this_ticket[] = $message->message;
            }

            $ticket_data[] = $this_ticket;
        }



        return [
            $headers,
            $ticket_data
        ];
    }
}
