<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'password',
        'employee_number',
        'role_id',
        'status'
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
    ];

    protected $appends = ['full_name', 'proper_full_name'];


    protected function fullName(): Attribute
    {
        return Attribute::get(
            fn () => "{$this->last_name}, {$this->first_name} {$this->middle_name}"
        );
    }

    protected function properFullName(): Attribute
    {
        $fullName = strtolower($this->first_name . ' ' . $this->last_name);
        if ($this->middle_name) {
            $middleInitial = strtoupper(substr($this->middle_name, 0, 1));
            $fullName = strtolower($this->first_name . ' ' . $middleInitial . '. ' . $this->last_name);
        }
        $properFullName = ucwords($fullName);
        return Attribute::get(
            fn () => $properFullName
        );
    }

    public function isActive() {
        if ($this->status === "active") {
            return true;
        }
        return false;
    }

    public function role()
    {
        return $this->belongsTo(Role::class); 
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Admin')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function employee() {
        return $this->hasOne(Employee::class, "employee_number", "employee_number");
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}
