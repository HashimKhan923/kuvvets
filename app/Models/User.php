<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uu_id',
        'name',
        'email',
        'phone',
        'address',
        'profile_image',
        'gender',
        'date_of_birth',
        'national_id',
        'job_title',
        'password',
        'department_id',
        'manager_id',
        'supervisor_id',
        'shift_id',
        'status',
        'role_id',
        'location_id',
        'forklift_id',


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

    public function shift()
    {
        return $this->hasOne(Shift::class,'id','shift_id');
    }

    public function location()
    {
        return $this->hasOne(Location::class,'id','location_id');
    }

    public function department()
    {
        return $this->hasOne(Department::class,'id','department_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function forklift()
    {
        return $this->belongsTo(ForkLift::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class);
    }

    public function departmentManager()
    {
        return $this->hasOne(Department::class, 'manager_id', 'id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class);
    }

    public function subordinates()
    {
        return $this->hasMany(User::class, 'manager_id', 'id');
    }

    public function supervisorSubordinates()
    {
        return $this->hasMany(User::class, 'supervisor_id', 'id');
    }

    

    public function leaveBalance()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function professionalDetails()
    {
        return $this->hasOne(ProfessionalDetails::class);
    }


    public function overTime()
    {
        return $this->hasOne(OverTime::class);
    }


    public function leaves()
    {
        return $this->hasMany(\App\Models\Leave::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(\App\Models\LeaveBalance::class);
    }

    public function attendances()
    {
        return $this->hasMany(\App\Models\Attendance::class);
    }

    public function tasks()
    {
        return $this->hasMany(\App\Models\Task::class);
    }
    
}
