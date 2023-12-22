<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketType extends Model {
    use HasFactory;

    protected $fillable = [
        'name',
        'ticket_type_category_id',
        'default_priority',
        'default_sla_take',
        'default_sla_solve',
        'company_id',
    ];

    public function tickets() {
        return $this->hasMany(Ticket::class);
    }

    public function typeFormField() {
        return $this->hasMany(TypeFormFields::class, 'ticket_type_id');
    }

    // public function companies() {
    //     return $this->belongsToMany(Company::class, 'company_ticket_types')->withPivot('sla_taking_charge', 'sla_resolving');
    // }

    public function company() {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function category() {
        return $this->belongsTo(TicketTypeCategory::class, 'ticket_type_category_id');
    }

    public function groups() {
        return $this->belongsToMany(Group::class, 'ticket_type_group', 'ticket_type_id', 'group_id');
    }
}
