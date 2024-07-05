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
        // Gestire qui i dati aggiuntivi

        // Questo valore dovrà essere passato dal frontend
        // $mergeRows = isset($this->additionalData->formData->merge_rows) ? $this->additionalData->formData->merge_rows : false;
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
    
                    // Genera il ticket
                    // {
                    //     "company":"1",
                    //     "isRequest":false,
                    //     "isProblem":true,
                    //     "category":10,
                    //     "type_id":26,
                    //     "description":"dqwd",
                    //     "messageData":{
                    //         "description":"dqwd",
                    //         "office":"1",
                    //         "referer_it":3
                    //     }
                    // }
    
                    $formData->messageData->Identificativo = $currentValue;
    
                    $ticketType = TicketType::find($formData->type_id);
                    $group = $ticketType->groups->first();
                    $groupId = $group ? $group->id : null;

                    $createFile = true;

                    // Descrizione per questo ticket specifico (viene modificata se c'è solo una riga e non si deve creare il file)
                    $rowDescription = $formData->description;

                    // Filtra le righe da raggruppare
                    $filteredRows = $rows->filter(function ($row) use ($currentValue) {
                        return isset($row[0]) && $row[0] == $currentValue;
                    });

                    if ($filteredRows->count() == 1) {
                        $createFile = false;
                        $addToDescription = '';
                        foreach ($filteredRows->first() as $n => $value) {
                            $addToDescription .= "\n" . $rows[0][$n] . ": " . $value;
                        }

                        $rowDescription .= $addToDescription;
                    }

                    // messageData per questo ticket specifico (viene modificato se c'è solo una riga e non si deve creare il file)
                    $rowMessageData = clone $formData->messageData;
                    $rowMessageData->description = $rowDescription;
    
                    $ticket = Ticket::create([
                        'description' => $rowDescription,
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
                        'message' => json_encode($rowMessageData),
                        // 'is_read' => 1
                    ]);
    
                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'message' => $rowDescription,
                        // 'is_read' => 0
                    ]);
    
                    $brand_url = $ticket->brandUrl();
                    dispatch(new SendOpenTicketEmail($ticket, $brand_url));
    
                    // Salva il file come allegato del ticket
                    // $filteredRows = $rows->filter(function ($row) use ($currentValue) {
                    //     return isset($row[0]) && $row[0] == $currentValue;
                    // });
    
                    $fileName = '';
    
                    // Crea il file se ci sono più righe raggruppate
                    if($createFile){
                        if ($filteredRows->isNotEmpty()) {
                            $filteredRows->prepend($rows[0]); // Add the first row to the filtered rows
        
                            $fileName = 'file_' . $currentValue . '_' . time() . '.xlsx';
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
    
            } else {
                foreach ($rows as $index => $row) {
    
                    // Genera il ticket
                    // {
                    //     "company":"1",
                    //     "isRequest":false,
                    //     "isProblem":true,
                    //     "category":10,
                    //     "type_id":26,
                    //     "description":"dqwd",
                    //     "messageData":{
                    //         "description":"dqwd",
                    //         "office":"1",
                    //         "referer_it":3
                    //     }
                    // }

                    if ($index == 0) {
                        continue;
                    }

                    $currentValue = $row[0];
    
                    $formData->messageData->Identificativo = $currentValue;
    
                    // Essendoci una riga sola in questo caso si aggiungono i dati nella descrizione e si evita di creare file inutilmente.
                    $addToDescription = '';
                    foreach ($row as $n => $value) {
                        $addToDescription .= "\n" . $rows[0][$n] . ": " . $value;
                    }
    
                    $rowDescription = $formData->description . $addToDescription;

                    $ticketType = TicketType::find($formData->type_id);
                    $group = $ticketType->groups->first();
                    $groupId = $group ? $group->id : null;
    
                    $ticket = Ticket::create([
                        'description' => $rowDescription,
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
    
                    $rowMessageData = clone $formData->messageData;
                    $rowMessageData->description = $rowDescription;

                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'message' => json_encode($rowMessageData),
                        // 'is_read' => 1
                    ]);
    
                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'message' => $rowDescription,
                        // 'is_read' => 0
                    ]);
    
                    $brand_url = $ticket->brandUrl();
                    dispatch(new SendOpenTicketEmail($ticket, $brand_url));
    
                    // Salva il file come allegato del ticket
                    // $filteredRows = $rows->filter(function ($row) use ($currentValue) {
                    //     return isset($row[0]) && $row[0] == $currentValue;
                    // });
    
                    // $fileName = '';
    
                    // if ($filteredRows->isNotEmpty()) {
                    //     $filteredRows->prepend($rows[0]); // Add the first row to the filtered rows
    
                    //     $fileName = 'file_' . $currentValue . '_' . time() . '.xlsx';
                    // }
                    // $path = "tickets/" . $ticket->id . "/";
                    // Excel::store(new FromCollection($filteredRows), $path, $fileName, "gcs");
                    // $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    // $size = Storage::disk('gcs')->size($path . $fileName);
                    // $ticketFile = TicketFile::create([
                    //     'ticket_id' => $ticket->id,
                    //     'filename' => $fileName,
                    //     'path' => $path,
                    //     'extension' => $extension,
                    //     'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    //     'size' => $size,
                    // ]);
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
