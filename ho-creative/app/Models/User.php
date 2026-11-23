<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'locale', 'customer_id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_staff' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function isOwner(): bool
    {
        return $this->is_staff && $this->hasRole('owner');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
