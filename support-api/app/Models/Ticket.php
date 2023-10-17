<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
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
        'due_date'
    ];

    /* get the owner */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** get  messages  */

    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }

    /** get  status updates  */

    public function statusUpdates()
    {
        return $this->hasMany(TicketStatusUpdate::class);
    }

    public function ticketType() {
        return $this->belongsTo(TicketType::class);
    }
}
