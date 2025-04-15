<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Mail\OtpEmail;
use Illuminate\Support\Facades\Mail;

class User extends Authenticatable {
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'surname',
        'phone',
        'city',
        'zip_code',
        'address',
        'is_admin',
        'company_id',
        'is_company_admin',
        'microsoft_token',
        'is_deleted',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the company that owns the user.
     */

    public function company() {
        return $this->belongsTo(Company::class);
    }

    /**
     * get user's tickets
     */

    public function tickets() {
        return $this->hasMany(Ticket::class)->with(['user' => function ($query) {
            $query->select(['id', 'name', 'surname', 'is_admin', 'company_id', 'is_company_admin', 'is_deleted']); // Specify the columns you want to include
        }]);
    }

    /**
     * get user's tickets as referer (seen in the webform message)
     */
    public function refererTickets() {
        $filteredTickets = $this->company
            ? $this->company->tickets->filter(function ($ticket) {
                    return $ticket->referer() && ($ticket->referer()->id == $this->id);
                })
            :  collect();
        $ids = $filteredTickets->pluck('id')->all();
        $tickets = Ticket::whereIn('id', $ids)->with(['user' => function ($query) {
            $query->select(['id', 'name', 'surname', 'is_admin', 'company_id', 'is_company_admin', 'is_deleted']); // Specify the columns you want to include
        }])->get();
        return $tickets;
    }

    /**
     * get user's tickets merge as user and as referer (seen in the webform message)
     */
    public function ownTicketsMerged() {
        $ticketsTemp = $this->tickets->merge($this->refererTickets());
        foreach ($ticketsTemp as $ticket) {
            $ticket->referer = $ticket->referer();
            if ($ticket->referer) {
                $ticket->referer->makeHidden(['email_verified_at', 'microsoft_token', 'created_at', 'updated_at', 'phone', 'city', 'zip_code', 'address']);
            }
            // Nascondere i dati utente se è stato aperto dal supporto. Essendo lato admin al momento non serve
            // if ($ticket->user->is_admin) {
            //     $ticket->user->id = 1;
            //     $ticket->user->name = "Supporto";
            //     $ticket->user->surname = "";
            //     $ticket->user->email = "Supporto";
            // }
        }
        return $ticketsTemp;
    }


    /**
     * get user's groups
     */

    public function groups() {
        return $this->belongsToMany(Group::class, 'user_groups', 'user_id', 'group_id');
    }

    /**
     * get user's custom groups
     */

    public function customUserGroups() {
        return $this->belongsToMany(CustomUserGroup::class, 'user_custom_groups', 'user_id', 'custom_user_group_id');
    }

    /**
     * get user's attendances
     */

    public function attendances() {
        return $this->hasMany(Attendance::class);
    }

    /**
     * get user's time off requests
     */

    public function timeOffRequests() {
        return $this->hasMany(TimeOffRequest::class);
    }

    public function businessTrips() {
        return $this->hasMany(BusinessTrip::class);
    }

    public function hardware() {
        return $this->belongsToMany(Hardware::class, 'hardware_user', 'user_id', 'hardware_id')
            ->using(HardwareUser::class)
            ->withPivot('created_by', 'responsible_user_id', 'created_at', 'updated_at');
    }

    public function createOtp() {
        // $otp = Otp::create([
        //     'email' => $this->email,
        //     'otp' => rand(1000, 9999),
        //     'expires_at' => now()->addMinutes(120),
        // ]);

        // Mail::to($this->email)->send(new OtpEmail($otp->otp));

        // return $otp;
        return true;
    }
}
