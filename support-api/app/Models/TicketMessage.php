<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'attachment',
        'is_read'
    ];

    /* get the owner */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /* get the ticket */

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
