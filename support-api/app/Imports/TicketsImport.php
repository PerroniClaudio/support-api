<?php

namespace App\Imports;

use App\Jobs\SendOpenTicketEmail;
use App\Models\Ticket;
use App\Models\TicketFile;
use App\Models\TicketMessage;
use App\Models\TicketType;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use App\Exports\RowsExport;

class TicketsImport implements ToCollection
{

    protected $additionalData;

    public function __construct($additionalData)
    {
        $this->additionalData = $additionalData;
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function collection(Collection $rows)
    {
        $mergeRows = $this->additionalData['formData']->merge_rows;

        $user = $this->additionalData['user'];
        $formData = $this->additionalData['formData'];

        // Gestire qui le righe del file Excel
        $generatedTickets = [];

        try{
            if($mergeRows){
    
                $distinctValues = [];
                foreach ($rows as $index => $row) {
                    if ($index > 0 && isset($row[0])) {
                        $distinctValues[] = $row[0];
                    }
                }
                $distinctValues = array_unique($distinctValues);
    
                foreach ($distinctValues as $currentValue) {
    
                    $formData->messageData->Identificativo = $currentValue;
    
                    $ticketType = TicketType::find($formData->type_id);
                    $group = $ticketType->groups->first();
                    $groupId = $group ? $group->id : null;
    
                    $ticket = Ticket::create([
                        'description' => $formData->description,
                        'type_id' => $ticketType->id,
                        'group_id' => $groupId,
                        'user_id' => $user->id,
                        'status' => '0',
                        'company_id' => $formData->company,
                        'file' => null,
                        'duration' => 0,
                        'sla_take' => $ticketType['default_sla_take'],
                        'sla_solve' => $ticketType['default_sla_solve'],
                        'priority' => $ticketType['default_priority'],
                        'unread_mess_for_adm' => 0,
                        'unread_mess_for_usr' => 1,
                    ]);
    
                    $generatedTickets[] = 'Ticket ID: ' . $ticket->id . ' - Identificativo valore file import: ' . $currentValue . " - Tipo di apertura ticket: raggruppato";
    
                    cache()->forget('user_' . $user->id . '_tickets');
                    cache()->forget('user_' . $user->id . '_tickets_with_closed');
    
                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'message' => json_encode($formData->messageData),
                    ]);
    
                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'message' => $formData->description,
                    ]);
    
                    $brand_url = $ticket->brandUrl();
                    dispatch(new SendOpenTicketEmail($ticket, $brand_url));
    
                    // Salva il file come allegato del ticket
                    $filteredRows = $rows->filter(function ($row) use ($currentValue) {
                        return isset($row[0]) && $row[0] == $currentValue;
                    });
    
                    $fileName = '';
    
                    // Crea il file se ci sono più righe raggruppate
                    if ($filteredRows->isNotEmpty()) {
                        $filteredRows->prepend($rows[0]); // Add the first row to the filtered rows
    
                        $fileName = 'file_' . $currentValue . '_' . time() . substr(uniqid(), -3) . '.xlsx';
                    }
                    $path = "tickets/" . $ticket->id . "/";

                    $export = new RowsExport($filteredRows);
                    Excel::store($export, $path . $fileName, "gcs");
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $size = Storage::disk('gcs')->size($path . $fileName);
                    $ticketFile = TicketFile::create([
                        'ticket_id' => $ticket->id,
                        'filename' => $fileName,
                        'path' => $path . $fileName,
                        'extension' => $extension,
                        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'size' => $size,
                    ]);
                }
    
            } else {
                foreach ($rows as $index => $row) {
                    if ($index == 0) {
                        continue;
                    }

                    $currentValue = $row[0];
    
                    $formData->messageData->Identificativo = $currentValue;

                    $ticketType = TicketType::find($formData->type_id);
                    $group = $ticketType->groups->first();
                    $groupId = $group ? $group->id : null;
    
                    $ticket = Ticket::create([
                        'description' => $formData->description,
                        'type_id' => $ticketType->id,
                        'group_id' => $groupId,
                        'user_id' => $user->id,
                        'status' => '0',
                        'company_id' => $formData->company,
                        'file' => null,
                        'duration' => 0,
                        'sla_take' => $ticketType['default_sla_take'],
                        'sla_solve' => $ticketType['default_sla_solve'],
                        'priority' => $ticketType['default_priority'],
                        'unread_mess_for_adm' => 0,
                        'unread_mess_for_usr' => 1,
                    ]);
    
                    $generatedTickets[] = 'Ticket ID: ' . $ticket->id . ' - Identificativo valore file import: ' . $currentValue . " - Tipo di apertura ticket: suddiviso - Indice riga: " . $index;
    
                    cache()->forget('user_' . $user->id . '_tickets');
                    cache()->forget('user_' . $user->id . '_tickets_with_closed');

                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'message' => json_encode($formData->messageData),
                    ]);
    
                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'message' => $formData->description,
                    ]);
    
                    $brand_url = $ticket->brandUrl();
                    dispatch(new SendOpenTicketEmail($ticket, $brand_url));
    
                    // Salva il file come allegato del ticket
                    $filteredRows = collect([$row]);

                    $fileName = '';
    
                    if ($filteredRows->isNotEmpty()) {
                        $filteredRows->prepend($rows[0]); // Add the first row to the filtered rows
    
                        $fileName = 'file_' . $currentValue . '_' . time() . substr(uniqid(), -3) . '.xlsx';
                    }
                    $path = "tickets/" . $ticket->id . "/";
                    
                    $export = new RowsExport($filteredRows);
                    Excel::store($export, $path . $fileName, "gcs");
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $size = Storage::disk('gcs')->size($path . $fileName);
                    $ticketFile = TicketFile::create([
                        'ticket_id' => $ticket->id,
                        'filename' => $fileName,
                        'path' => $path . $fileName,
                        'extension' => $extension,
                        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'size' => $size,
                    ]);
                    
                }
            }
        } catch (\Exception $e) {
            $mailSubject = "Errore generazione ticket";
            $mailContent = implode("\n\n", $generatedTickets) . "\n\n" . $e->getMessage();

            // Questo dovrà poi andare nel suo file apposito
            // Send email to user
            Mail::raw($mailContent, function ($message) use ($user, $mailSubject) {
                $message->to($user->email)
                        ->subject($mailSubject);
            });

            throw $e;
        }
    }
}
