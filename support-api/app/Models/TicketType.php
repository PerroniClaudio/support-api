<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    public function tickets() {
        return $this->hasMany(Ticket::class);
    }

    public function typeFormField() {
        return $this->hasMany(TypeFormFields::class);
    }

    public function companies() {
        return $this->belongsToMany(Company::class, 'company_ticket_types');
    }
}
