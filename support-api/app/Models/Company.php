<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model {
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'sla',
        'note',
    ];

    public function users() {
        return $this->hasMany(User::class);
    }

    public function ticketTypes() {
        return $this->belongsToMany(TicketType::class, 'company_ticket_types')->withPivot('sla_taking_charge', 'sla_resolving');;
    }

    public function tickets() {
        return $this->hasMany(Ticket::class);
    }

    public function offices() {
        return $this->hasMany(Office::class);
    }

    public function expenses() {
        return $this->hasMany(BusinessTripExpense::class);
    }

    public function transfers() {
        return $this->hasMany(BusinessTripTransfer::class);
    }
}
