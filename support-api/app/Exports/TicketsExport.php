<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\Office;
use App\Models\Ticket;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;

class TicketsExport implements FromArray {

    private $company_id;
    private $start_date;
    private $end_date;

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
        $headers = [
            "ID",
            "Autore",
            "Utente interessato", // referer
            "Data",
            "Tipologia",
            "Webform",
            "Chiusura",
            "Fatturabile",
            "Tempo di esecuzione (ore)",
            "Tempo in attesa (ore)",
            "Numero di volte in attesa",
            "Modalità di lavoro",
            "Form corretto",
            "Cliente autonomo",
            "Responsabilità del dato", // nel db per ora è is_user_error perchè veniva usato in un altro modo
            "Responsabilità del problema"
        ];

        foreach ($tickets as $ticket) {

            $messages = $ticket->messages;
            $webform = json_decode($messages->first()->message, true);
            $webform_text = "";
            $has_referer = false;
            $referer_name = "";
            
            if(isset($webform)){
                foreach ($webform as $key => $value) {
                    if ($key == "referer") {
                        $has_referer = true;
                    } else if ($key == "referer_it"){
    
                    } else if ($key == "office"){
                        $office = Office::find($value);
                        $office ? $webform_text .= $key . ": " . $office->name . "\n" : null;
                    } else {
                        $webform_text .= $key . ": " . (is_array($value) ? implode(', ', $value) : $value) . "\n";
                    }
                }
            }

            if ($has_referer) {
                if (isset($webform['referer'])) {
                    $referer = User::find($webform['referer']);
                    $referer_name = $referer ? $referer->name . " " . $referer->surname : null;
                }
            }

            $closingUpdate = $ticket->statusUpdates->where('status', 'closing')->last();
            $closingDate = $closingUpdate ? $closingUpdate->created_at : null;

            $waiting_times = $ticket->waitingTimes();
            $waiting_hours = $ticket->waitingHours();

            $processingTimeHours= $ticket->actual_processing_time ? floor($ticket->actual_processing_time / 60) : 0;
            $processingTimeMinutes = $ticket->actual_processing_time ? $ticket->actual_processing_time % 60 : 0;
            $processingTime = (!!$processingTimeHours ? ($processingTimeHours . ($processingTimeHours > 1 ? " ore " : " ora ")) : "") 
                . ((!$processingTimeHours || !$processingTimeMinutes) ? "" : "e ")
                . (!!$processingTimeMinutes ? ($processingTimeMinutes . ($processingTimeMinutes > 1 ? " minuti" : " minuto")) : "");
            
            $workModes = config('app.work_modes');
            $this_ticket = [
                $ticket->id,
                $ticket->user->name . " " . $ticket->user->surname,
                $referer_name,
                $ticket->created_at,
                $ticket->ticketType->name,
                $webform_text,
                $closingDate,
                isset($ticket->is_billable) ? ($ticket->is_billable ? "Si" : "No") : "Non definito",
                $processingTime,
                $waiting_hours,
                $waiting_times,
                $workModes && $ticket->work_mode ? $workModes[$ticket->work_mode] : $ticket->work_mode,
                $ticket->is_form_correct ? "Si" : "No",
                $ticket->was_user_self_sufficient ? "Si" : "No",
                $ticket->is_user_error ? "Cliente" : "Supporto", // nel db per ora è is_user_error perchè veniva usato in un altro modo
                $ticket->ticketType->is_problem ? ($ticket->is_user_error_problem ? "Cliente" : "Supporto") : "-"
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
