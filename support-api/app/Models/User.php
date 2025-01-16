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
        $filteredTickets = $this->company->tickets->filter(function ($ticket) {
            return $ticket->referer() && ($ticket->referer()->id == $this->id);
        });
        $ids = $filteredTickets->pluck('id')->all();
        $tickets = Ticket::whereIn('id', $ids)->with(['user' => function ($query) {
            $query->select(['id', 'name', 'surname', 'is_admin', 'company_id', 'is_company_admin', 'is_deleted']); // Specify the columns you want to include
        }])->get();
        return $tickets;
    }

    /**
     * get user's groups
     */

    public function groups() {
        return $this->belongsToMany(Group::class, 'user_groups', 'user_id', 'group_id');
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

    public function createOtp() {
        $otp = Otp::create([
            'email' => $this->email,
            'otp' => rand(1000, 9999),
            'expires_at' => now()->addMinutes(120),
        ]);

        Mail::to($this->email)->send(new OtpEmail($otp->otp));

        return $otp;
    }

    public function hardware() {
        return $this->hasMany(Hardware::class);
    }
}
