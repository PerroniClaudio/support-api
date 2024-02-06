<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model {
    use HasFactory;

    protected $fillable = ["name"];

    /* get the users */

    public function users() {
        return $this->belongsToMany(User::class, 'user_groups', 'group_id', 'user_id');
    }

    public function ticketTypes() {
        return $this->belongsToMany(TicketType::class, 'ticket_type_group', 'group_id', 'ticket_type_id');
    }

    public function tickets() {
        return $this->hasMany(Ticket::class);
    }

    public function ticketsWithUser() {
        return $this->hasMany(Ticket::class)->with('user');
    }

    // public function allTickets()
    // {
    //     return $this->tickets()->where('group_id', $this->id);
    // }
}
