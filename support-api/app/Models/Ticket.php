<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;

class Ticket extends Model {
    use HasFactory, Searchable;

    protected $fillable = [
        'company_id',
        'user_id',
        'status',
        'description',
        'type',
        'file',
        'duration',
        'admin_user_id',
        'group_id',
        'due_date',
        'type_id',
        'sla_take',
        'sla_solve',
        'priority',
        'wait_end',
        'is_user_error',
        'unread_mess_for_adm',
        'unread_mess_for_usr',
        'actual_processing_time',
    ];

    public function toSearchableArray() {
        return [
            'description' => $this->description,
            'status' => $this->status,
            'type' => $this->type,
        ];
    }

    /* get the owner */

    public function user() {
        return $this->belongsTo(User::class);
    }

    /* get the handler */

    public function handler() {
        // return User::find($this->admin_user_id);
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /* get the referer (referente in sede) */

    public function referer() {
        // Si usa newQueryWithoutRelationships per evitare di caricare i messaggi, che non servono
        $ticketWithoutMessages = $this->newQueryWithoutRelationships()->find($this->id);
        $messages = $ticketWithoutMessages->messages;
        if (count($messages) > 0) {
            $message_obj = json_decode($messages[0]->message);
            // Controllo se esiste la proprietà, perchè nei ticket vecchi non c'è e può dare errore.
            if (isset($message_obj->referer)) {
                return User::find($message_obj->referer);
            }
        }
        return User::find(0);
    }

    /* get the IT referer (referente IT) */

    public function refererIT() {
        $ticketWithoutMessages = $this->newQueryWithoutRelationships()->find($this->id);
        $messages = $ticketWithoutMessages->messages;
        if (count($messages) > 0) {
            $message_obj = json_decode($messages[0]->message);
            // Controllo se esiste la proprietà, perchè nei ticket vecchi non c'è e può dare errore.
            if (isset($message_obj->referer_it)) {
                return User::find($message_obj->referer_it);
            }
        }
        return User::find(0);
    }

    /** get  messages  */

    public function messages() {
        return $this->hasMany(TicketMessage::class);
    }

    // // Messaggi non letti inviati dagli utenti
    // public function unreadUsersMessages() {
    //     $ticketWithoutMessages = $this->newQueryWithoutRelationships()->find($this->id);
    //     $usersIds = User::all()->where('is_admin', 0)->pluck('id');
    //     $messages = $ticketWithoutMessages->messages->whereIn('user_id', $usersIds);
    //     $unreadMessages = $messages->where('is_read', 0);
    //     return count($unreadMessages);
    // }
    // // Questa funzione permette di usare $ticket->append('unread_users_messages') per aggiungere la proprietà al ticlet o $ticket->unread_users_messages per accedere alla proprietà (laravel esegue in automatico la funzione unreadUsersMessages per calcolarne il valore)
    // public function getUnreadUsersMessagesAttribute()
    // {
    //     return $this->unreadUsersMessages();
    // }
    // // Imposta i messaggi degli utenti come letti
    // public function setUsersMessagesAsRead() {
    //     $ticketWithoutMessages = $this->newQueryWithoutRelationships()->find($this->id);
    //     $usersIds = User::all()->where('is_admin', 0)->pluck('id');
    //     $messages = $ticketWithoutMessages->messages->whereIn('user_id', $usersIds);
    //     $unreadMessages = $messages->where('is_read', 0);
    //     foreach($unreadMessages as $message){
    //         $message->is_read = 1;
    //         $message->save();
    //     }
    // }

    // // Messaggi non letti inviati dagli admin
    // public function unreadAdminsMessages() {
    //     $ticketWithoutMessages = $this->newQueryWithoutRelationships()->find($this->id);
    //     $adminsIds = User::all()->where('is_admin', 1)->pluck('id');
    //     $messages = $ticketWithoutMessages->messages->whereIn('user_id', $adminsIds);
    //     $unreadMessages = $messages->where('is_read', 0);
    //     return count($unreadMessages);
    // }
    // // Questa funzione permette di usare $ticket->append('unread_admins_messages') per aggiungere la proprietà al ticlet o $ticket->unread_admins_messages per accedere alla proprietà (laravel esegue in automatico la funzione unreadAdminsMessages per calcolarne il valore)
    // public function getUnreadAdminsMessagesAttribute()
    // {
    //     return $this->unreadAdminsMessages();
    // }
    // // Imposta i messaggi degli admin come letti
    // public function setAdminsMessagesAsRead() {
    //     $ticketWithoutMessages = $this->newQueryWithoutRelationships()->find($this->id);
    //     $adminsIds = User::all()->where('is_admin', 1)->pluck('id');
    //     $messages = $ticketWithoutMessages->messages->whereIn('user_id', $adminsIds);
    //     $unreadMessages = $messages->where('is_read', 0);
    //     foreach($unreadMessages as $message){
    //         $message->is_read = 1;
    //         $message->save();
    //     }
    // }


    /** get  status updates  */

    public function statusUpdates() {
        return $this->hasMany(TicketStatusUpdate::class);
    }

    public function ticketType() {
        return $this->belongsTo(TicketType::class, 'type_id');
    }

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function files() {
        return $this->hasMany(TicketFile::class);
    }

    public function brandUrl() {
        $brand_id = $this->ticketType->brand->id;
        return env('APP_URL') . '/api/brand/' . $brand_id . '/logo';
    }

    // Invalida la cache per chi ha creato il ticket e per i referenti
    public function invalidateCache() {
        // $cacheKey = 'user_' . $user->id . '_tickets';
        $ticketUser = $this->user;
        $referer = $this->referer();
        $refererIT = $this->refererIT();
        if ($ticketUser) {
            Cache::forget('user_' . $ticketUser->id . '_tickets');
            Cache::forget('user_' . $ticketUser->id . '_tickets_with_closed');
        }
        if ($referer) {
            Cache::forget('user_' . $referer->id . '_tickets');
            Cache::forget('user_' . $referer->id . '_tickets_with_closed');
        }
        if ($refererIT) {
            Cache::forget('user_' . $refererIT->id . '_tickets');
            Cache::forget('user_' . $refererIT->id . '_tickets_with_closed');
        }
    }

    // In base al tipo di ticket si dovranno includere o meno i sabati, le domeniche, tutte le ore del giorno o anche le festività 
    public function waitingHours($includeSaturday = false, $includeSunday = false, $IncludeAllDayHours = false, $includeHolidays = false) {
        $waitingHours = 0;

        // Array delle festività italiane
        $holidays = [
            '01-01', // Capodanno
            '06-01', // Epifania
            '25-04', // Festa della Liberazione
            '01-05', // Festa dei Lavoratori
            '02-06', // Festa della Repubblica
            '15-08', // Ferragosto
            '01-11', // Ognissanti
            '08-12', // Immacolata Concezione
            '25-12', // Natale
            '26-12', // Santo Stefano
        ];

        /*
            Se il ticket è stato in attesa almeno una volta bisogna calcolare il tempo totale in cui è rimasto in attesa.
        */

        $statusUpdates = $this->statusUpdates()->whereIn('type', ['status', 'closing'])->get();

        $hasBeenWaiting = false;
        $waitingRecords = [];
        $waitingEndingRecords = [];
        $waitingMinutes = 0;

        for ($i = 0; $i < count($statusUpdates); $i++) {
            if (
                (strpos(strtolower($statusUpdates[$i]->content), 'in attesa') !== false) || (strpos(strtolower($statusUpdates[$i]->content), 'risolto') !== false)
            ) {
                $hasBeenWaiting = true;
                $waitingRecords[] = $statusUpdates[$i];
                $waitingEndingRecords[] = $statusUpdates[$i + 1] ?? null;
            }
        }

        if ($hasBeenWaiting === false) {
            return 0;
        }

        for ($i = 0; $i < count($waitingRecords); $i++) {
            $start = $waitingRecords[$i]->created_at;
            $end = $waitingEndingRecords[$i] != null ? $waitingEndingRecords[$i]->created_at : now();
            $totalMinutes = $start->diffInMinutes($end);

            $excludedMinutes = 0;
            $current = $start->copy();

            while ($current->lessThan($end)) {
                $isExcludedDay = (!$includeSunday && $current->isSunday())
                    || (!$includeSaturday && $current->isSaturday())
                    || (!$includeHolidays && in_array($current->format('m-d'), $holidays));
                $isExcludedHour = !$IncludeAllDayHours && ($current->hour >= 20 || $current->hour < 8);
                if ($isExcludedHour || $isExcludedDay) {
                    $excludedMinutes++;
                }
                $current->addMinute();
            }

            $waitingMinutes += ($totalMinutes - $excludedMinutes);
        }

        $waitingHours = $waitingMinutes / 60;

        return $waitingHours;
    }

    public function waitingTimes() {
        $waitingHours = 0;

        /*
            Se il ticket è stato in attesa almeno una volta bisogna calcolare il tempo totale in cui è rimasto in attesa.
        */

        $statusUpdates = $this->statusUpdates()->where('type', 'status')->get();

        $hasBeenWaiting = false;
        $waitingRecords = [];
        $waitingEndingRecords = [];

        for ($i = 0; $i < count($statusUpdates); $i++) {
            if (
                (strpos(strtolower($statusUpdates[$i]->content), 'in attesa') !== false) || (strpos(strtolower($statusUpdates[$i]->content), 'risolto') !== false)
            ) {
                $hasBeenWaiting = true;
                $waitingRecords[] = $statusUpdates[$i];
                if (count($statusUpdates) > ($i + 1)) {
                    $waitingEndingRecords[] = $statusUpdates[$i + 1];
                }
            }
        }

        if ($hasBeenWaiting === false) {
            return 0;
        }

        return count($waitingRecords);
    }

    // public function calculateRemainingTime() {

    //     // $statusUpdatesGo = $this->statusUpdates()->where('type', 'status')->where(function ($query) {
    //     //     $query->where('content', 'not like', '%in attesa%')
    //     //         ->orWhere('content', 'not like', '%risolto%')
    //     //         ->orWhere('content', 'not like', '%chiuso%');
    //     // })->get();

    //     // $statusUpdatesStops = $this->statusUpdates()->where('type', 'status')->where(function ($query) {
    //     //     $query->where('content', 'like', '%in attesa%')
    //     //         ->orWhere('content', 'like', '%risolto%')
    //     //         ->orWhere('content', 'like', '%chiuso%');
    //     // })->get();

    //     // Prende tutti gli staus update di tipo status, in ordine cronologico 
    //     // Crea l'array vuoto $time_frames (conterrà elementi di questo tipo: {type: go/pause, start: 0, end: 0})
    //     // Crea la variabile $now_going = true


    //     $totalTime = $this->sla; // Total available time to solve the ticket
    //     $timeToSubtract = 0; // Time to subtract from the total available time

    //     foreach ($statusUpdates as $update) {
    //         // Calculate the time between status updates
    //         // You may need to adjust this part based on your specific requirements and the format of your timestamps
    //         $timeDiff = $update->created_at->diffInMinutes($this->created_at);

    //         $totalTime -= $timeDiff;
    //     }

    //     return $totalTime;
    // }
}
