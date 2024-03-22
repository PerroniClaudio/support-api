<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\Ticket;
use Maatwebsite\Excel\Concerns\FromArray;

class TicketsExport implements FromArray {

    private $company;

    public function __construct(Company $company) {
        $this->company = $company;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function array(): array {

        $tickets = Ticket::where('company_id', $this->company->id)->whereBetween('created_at', [now()->subDays(30)->startOfMonth(), now()->subDays(30)->endOfMonth()])->get();

        $ticket_data = [];
        $headers = ["ID", "Autore", "Referente", "Data", "Tipologia", "Webform", "Chiusura", "Tempo in attesa", "Numero di volte in attesa"];

        foreach ($tickets as $ticket) {

            $messages = $ticket->messages;
            $webform = json_decode($messages->first()->message, true);
            $webform_text = "";

            foreach ($webform as $key => $value) {
                $webform_text .= $key . ": " . $value . "\n";
            }

            $this_ticket = [
                $ticket->id,
                $ticket->user->name,
                "",
                $ticket->created_at,
                $ticket->ticketType->name,
                $webform_text,
                $ticket->created_at,
                "0",
                "1"
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
