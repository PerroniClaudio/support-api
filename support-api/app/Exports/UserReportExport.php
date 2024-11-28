<?php

namespace App\Exports;

use App\Models\Ticket;
use App\Models\TicketReportExport;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;

class UserReportExport implements FromArray
{
    private $report;
  
    public function __construct(TicketReportExport $report)
    {
        $this->report = $report;
    }
    
    public function array(): array {
        $optional_parameters = json_decode($this->report->optional_parameters, true);
        $ticket_data = [];
        $headers = [
            "ID",
            "Utente",
            "Referente",
            "Referente IT",
            "Problema/Richiesta",
            "Categoria",
            "Tipologia",
            "Data di apertura",
            "Data di chiusura",
            "Passaggi di stato",
            "Commento di chiusura",
            "SLA Prevista presa in carico",
            "SLA Prevista risoluzione",
            "Cambi di priorità",
            "SLA Aggiornata presa in carico",
            "SLA Aggiornata risoluzione"
        ];

        $tickets = Ticket::where('company_id', $this->report->company_id)->whereBetween('created_at', [
            $this->report->start_date,
            $this->report->end_date
        ]);

        foreach ($tickets as $ticket) {

            $messages = $ticket->messages;
            $webform = json_decode($messages->first()->message, true);
            $webform_text = "";
            $has_referer = false;
            $has_referer_it = false;
            $referer_name = "";
            $referer_it_name = "";

            foreach ($webform as $key => $value) {
                $webform_text .= $key . ": " . $value . "\n";

                if ($key == "referer") {
                    $has_referer = true;
                }
         
                if ($key == "referer_it") {
                    $has_referer_it = true;
                }
            }

            if ($has_referer) {
                if (isset($webform['referer'])) {
                    $referer = User::find($webform['referer']);
                    $referer_name = $referer ? $referer->name . " " . $referer->surname : null;
                }
            }

            if ($has_referer_it) {
                if (isset($webform['referer_it'])) {
                    $referer_it = User::find($webform['referer_it']);
                    $referer_it_name = $referer_it ? $referer_it->name . " " . $referer_it->surname : null;
                }
            }

            //? La data di chiusura va presa dall'ultimo status update

            $commento_chiusura = "";
            $data_chiusura = "";
            $cambiamenti_stato = "";
            $cambiamenti_priorità = "";

            for ($i = 0; $i < count($ticket->status_updates); $i++) {
                if ($ticket->status_updates[$i]->type == 'closing') {
                    $data_chiusura = $ticket->status_updates[$i]->created_at;
                    $commento_chiusura = $ticket->status_updates[$i]->content;
                } else if ($ticket->status_updates[$i]->type == 'status') {
                    $cambiamenti_stato .= $ticket->status_updates[$i]->created_at . " - " . $ticket->status_updates[$i]->content . "\n";
                } else if ($ticket->status_updates[$i]->type == 'sla') {
                    $cambiamenti_priorità .= $ticket->status_updates[$i]->created_at . " - " . $ticket->status_updates[$i]->content . "\n";
                }
            }

            $this_ticket = [
                $ticket->id,
                $ticket->user->name . " " . $ticket->user->surname,
                $referer_name,
                $referer_it_name,
                $ticket->ticketType->category->is_problem ? "Problema" : "Richiesta",
                $ticket->ticketType->category->name,
                $ticket->ticketType->name,
                $ticket->created_at,
                $data_chiusura,
                $cambiamenti_stato,
                $commento_chiusura,
                $ticket->ticketType->default_sla_take,
                $ticket->ticketType->default_sla_solve,
                $cambiamenti_priorità,
                $ticket->sla_take,
                $ticket->sla_solve
            ];
            
            $ticket_data[] = $this_ticket;
        }
        
        return [
            $headers,
            $ticket_data
        ];
    }
}
