<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model {
    use HasFactory;

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
    ];

    /* get the owner */

    public function user() {
        return $this->belongsTo(User::class);
    }

    /* get the handler */

    public function handler() {
        // return User::find($this->admin_user_id);
        return $this->belongsTo(User::class, 'admin_user_id');
    }
    
    /* get the handler */

    public function referer() {
        return User::find(json_decode($this->messages[0]->message)->referer);
    }

    /** get  messages  */

    public function messages() {
        return $this->hasMany(TicketMessage::class);
    }

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

    public function waitingHours() {
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
                (strpos($statusUpdates[$i]->content, 'in attesa') !== false) || (strpos($statusUpdates[$i]->content, 'risolto') !== false)
            ) {
                $hasBeenWaiting = true;
                $waitingRecords[] = $statusUpdates[$i];
                $waitingEndingRecords[] = $statusUpdates[$i + 1];
            }
        }

        if ($hasBeenWaiting === false) {
            return 0;
        }

        for ($i = 0; $i < count($waitingRecords); $i++) {
            $waitingHours += $waitingRecords[$i]->created_at->diffInMinutes($waitingEndingRecords[$i]->created_at);
        }



        return $waitingHours;
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
