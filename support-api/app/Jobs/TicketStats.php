<?php

namespace App\Jobs;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TicketStats implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct() {
        //
    }

    private function getNightHours($start, $end) {
        $nightHours = 0;
        $startDay = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();
        $nightStart = $startDay->copy()->addHours(18);
        $nightEnd = $startDay->copy()->addHours(8);

        if ($startDay->isSameDay($endDay)) {
            if ($start->isBefore($nightStart) && $end->isAfter($nightEnd)) {
                $nightHours = 10;
            } else if ($start->isBefore($nightStart) && $end->isBefore($nightEnd)) {
                $nightHours = $start->diffInHours($nightEnd);
            } else if ($start->isAfter($nightStart) && $end->isAfter($nightEnd)) {
                $nightHours = $end->diffInHours($nightStart);
            }
        } else {
            $nightHours = $start->diffInHours($nightEnd);
            $nightHours += $end->diffInHours($nightStart);
        }

        return $nightHours;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
        $openTicekts = Ticket::where('status', '!=', '5')->with('ticketType.category')->get();

        $results = [
            'incident_open' => 0,
            'incident_in_progress' => 0,
            'incident_waiting' => 0,
            'incident_out_of_sla' => 0,
            'request_open' => 0,
            'request_in_progress' => 0,
            'request_waiting' => 0,
            'request_out_of_sla' => 0
        ];

        foreach ($openTicekts as $ticket) {


            switch ($ticket->ticketType->category->is_problem) {
                case 1:
                    switch ($ticket->status) {
                        case 0:
                            $results['incident_open']++;
                            break;
                        case 1:
                        case 2:
                            $results['incident_in_progress']++;
                            break;
                        case 3:
                            $results['incident_waiting']++;
                            break;
                    }
                    break;
                case 0:
                    switch ($ticket->status) {
                        case 0:
                            $results['request_open']++;
                            break;
                        case 1:
                        case 2:
                            $results['request_in_progress']++;
                            break;
                        case 3:
                            $results['request_waiting']++;
                            break;
                    }
                    break;
            }

            /*
                Per verificare se il ticket in sla bisogna utilizzare il campo sla_solve del ticketType. 

                Bisogna verificare che la differenza tra la data attuale e la data di creazione del ticket sia minore della data di sla_solve.
                Calcolando questa differenza bisogna tenere conto del fatto che le ore tra mezzanotte e le 8 del mattino non vanno calcolate.
                Calcolando questa differenza bisogna tenere conto del fatto che le ore tra le 18 e mezzanotte non vanno calcolate.
                Calcolando questa differenza bisogna tenere conto che il sabato, la domenica ed i giorni festivi non vanno calcolati.

            */

            $ticketType = $ticket->ticketType;
            $sla = $ticketType->sla_solve / 60;
            $ticketCreationDate = $ticket->created_at;
            $now = now();

            $diffInHours = $ticketCreationDate->diffInHours($now);

            // Rimuovere le ore tra mezzanotte e le 8 del mattino da $diffInHours

            $diffInHours -= $this->getNightHours($ticketCreationDate, $now);

            // Rimuovere le ore tra le 18:00 e mezzanotte da $diffInHours

            $diffInHours -= $this->getNightHours($ticketCreationDate->copy()->addHours(18), $now);


            if ($diffInHours > $sla) {
                switch ($ticket->ticketType->category->is_problem) {
                    case 1:
                        $results['incident_out_of_sla']++;
                        break;
                    case 0:
                        $results['request_out_of_sla']++;
                        break;
                }
            }
        }
    }
}
