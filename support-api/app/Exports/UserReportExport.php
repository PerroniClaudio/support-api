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
            "Utente interessato", // referer
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
            "SLA Aggiornata risoluzione",
            "Modalità di lavoro",
            // "Form corretto",
            // "Cliente autonomo",
            // "Responsabilità del dato", // nel db per ora è is_user_error perchè veniva usato in un altro modo
            // "Responsabilità del problema"
        ];

        $tickets = Ticket::where('company_id', $this->report->company_id)->whereBetween('created_at', [
            $this->report->start_date,
            $this->report->end_date
        ])->get();

        foreach ($tickets as $ticket) {

            $messages = $ticket->messages;
            $webform = json_decode($messages->first()->message, true);
            $webform_text = "";
            $has_referer = false;
            $has_referer_it = false;
            $referer_name = "";
            $referer_it_name = "";

            foreach ($webform as $key => $value) {
                $webform_text .= $key . ": " . (is_array($value) ? implode(', ', $value) : $value) . "\n";

                if ($key == "referer") {
                    if ($value != 0) {
                        $has_referer = true;
                    } else {
                        unset($webform[$key]);
                    }
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

            if($ticket->statusUpdates){
                for ($i = 0; $i < count($ticket->statusUpdates); $i++) {
                    if ($ticket->statusUpdates[$i]->type == 'closing') {
                        $data_chiusura = $ticket->statusUpdates[$i]->created_at;
                        $commento_chiusura = $ticket->statusUpdates[$i]->content;
                    } else if ($ticket->statusUpdates[$i]->type == 'status') {
                        $cambiamenti_stato .= ((strlen($cambiamenti_stato) > 0) ? " - " : "") . $ticket->statusUpdates[$i]->created_at . " - " . $ticket->statusUpdates[$i]->content;
                    } else if ($ticket->statusUpdates[$i]->type == 'sla') {
                        $cambiamenti_priorità .= ((strlen($cambiamenti_priorità) > 0) ? " - " : "") . $ticket->statusUpdates[$i]->created_at . " - " . $ticket->statusUpdates[$i]->content;
                    }
                }
            }

            $workModes = config('app.work_modes');
            $this_ticket = [
                $ticket->id,
                $ticket->user->is_admin ? "Supporto" : $ticket->user->name . " " . $ticket->user->surname,
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
                $ticket->sla_solve,
                $workModes && $ticket->work_mode ? $workModes[$ticket->work_mode] : $ticket->work_mode,
                // $ticket->is_form_correct ? "Si" : "No",
                // $ticket->was_user_self_sufficient ? "Si" : "No",
                // $ticket->is_user_error ? "Cliente" : "Supporto", // nel db per ora è is_user_error perchè veniva usato in un altro modo
                // $ticket->ticketType->is_problem ? ($ticket->is_user_error_problem ? "Cliente" : "Supporto") : "-"
            ];
            
            $ticket_data[] = $this_ticket;
        }
        
        return [
            $headers,
            $ticket_data
        ];
    }
}
