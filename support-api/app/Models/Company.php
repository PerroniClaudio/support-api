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
        'sla_take_low',
        'sla_take_medium',
        'sla_take_high',
        'sla_take_critical',
        'sla_solve_low',
        'sla_solve_medium',
        'sla_solve_high',
        'sla_solve_critical',
        'sla_prob_take_low',
        'sla_prob_take_medium',
        'sla_prob_take_high',
        'sla_prob_take_critical',
        'sla_prob_solve_low',
        'sla_prob_solve_medium',
        'sla_prob_solve_high',
        'sla_prob_solve_critical',
        'data_owner_name',
        'data_owner_surname',
        'data_owner_email',
    ];

    public function users() {
        return $this->hasMany(User::class);
    }

    // public function ticketTypes() {
    //     return $this->belongsToMany(TicketType::class, 'company_ticket_types')->withPivot('sla_taking_charge', 'sla_resolving');;
    // }
    
    public function ticketTypes() {
        return $this->hasMany(TicketType::class);
    }

    public function tickets() {
        return $this->hasMany(Ticket::class)->with(['user' => function ($query) {
            $query->select(['id', 'name', 'surname', 'is_admin', 'company_id', 'is_company_admin', 'is_deleted']); // Specify the columns you want to include
        }]);
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

    public function brands() {
        return  $this->ticketTypes()->get()->map(function ($ticketType) {
            return Brand::where('id', $ticketType->brand_id)->first();
        })->unique('id');
    }
}
