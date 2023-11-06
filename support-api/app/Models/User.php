<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
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
        'microsoft_token'
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * get user's tickets
     */

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * get user's groups
     */

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'user_groups', 'user_id', 'group_id');
    }

    /**
     * get user's attendances
     */

    public function attendances()
    {
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

}
