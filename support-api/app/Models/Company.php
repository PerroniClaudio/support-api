<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function ticketTypes()
    {
        return $this->belongsToMany(TicketType::class, 'company_ticket_types');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function offices()
    {
        return $this->hasMany(Office::class);
    }

    public function expenses() {
        return $this->hasMany(BusinessTripExpense::class);
    }

    public function transfers() {
        return $this->hasMany(BusinessTripTransfer::class);
    }
}
