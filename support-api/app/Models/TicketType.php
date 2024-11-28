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
        'is_deleted',
        'brand_id',
        'warning',
        'it_referer_limited',
        'description',
        'is_massive_enabled',
        'expected_processing_time',
    ];

    public function tickets() {
        // Questo non funziona perchè non c'è la foreign key ticket_type_id nella tabella tickets
        // Essendo ogni tipo collegato ad un'azienda si possono ottenere i ticket di un tipo con una certa azienda
        // return $this->hasMany(Ticket::class);
        return Ticket::where('type_id', $this->id)->get();
    }
    
    // Restituisce il numero di ticket di questo tipo e con questa compagnia (ogni tipo è associato ad una sola compagnia)
    public function countRelatedTickets()
    {
        return $this->company->tickets()->where('type_id', $this->id)->count();
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

    public function brand() {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

}
