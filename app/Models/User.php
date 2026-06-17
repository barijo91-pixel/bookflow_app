<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'login_id', 'email', 'password', 'phone',
    'role_code', 'admin_level', 'status_code',
    'region_id', 'address', 'address_detail',
    'phone_verified_at', 'email_verified_at',
    'approved_by', 'approved_at', 'last_login_at',
    'password_change_required',
    // 정산·세무 (총판 입금계좌 / 영업자 사업자·정산계좌)
    'business_type', 'business_no', 'business_name',
    'bank_code', 'bank_account', 'bank_holder',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'password_change_required' => 'boolean',
        ];
    }

    // --- Convenience role helpers ---
    public function isAdmin(): bool       { return $this->role_code === 'admin'; }
    public function isDistributor(): bool { return $this->role_code === 'distributor'; }
    public function isAgent(): bool       { return $this->role_code === 'agent'; }
    public function isAcademy(): bool     { return $this->role_code === 'academy'; }
    public function isSuperAdmin(): bool  { return $this->isAdmin() && $this->admin_level === 'super'; }
    public function isActive(): bool      { return $this->status_code === 'active'; }
}
