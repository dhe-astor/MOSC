<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar_path',
        'default_diocese_id',
        'default_church_id',
        'active_church_id',
        'preferred_language',
        'last_login_at',
        'is_active',
        'phone_verified_at',
        'two_factor_enabled',
        'two_factor_confirmed_at',
        'two_factor_otp_hash',
        'two_factor_otp_expires_at',
        'two_factor_last_verified_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_otp_hash'
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_otp_expires_at' => 'datetime',
            'two_factor_last_verified_at' => 'datetime'
        ];
    }

    public function requires2Fa(): bool
    {
        $sensitiveRoles = [
            'Super Admin',
            'Diocese Admin',
            'Diocese Treasurer',
            'Diocese Auditor',
            'Diocese Secretary'
        ];

        if ($this->two_factor_enabled) {
            return true;
        }

        foreach ($sensitiveRoles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        $sensitivePermissions = [
            'manage_roles',
            'manage_permissions',
            'export_member_reports',
            'export_child_reports',
            'export_finance_reports',
            'export_gdpr_reports',
            'export_audit_reports',
            'view_unmasked_report_contacts',
            'view_unmasked_notification_recipients',
            'download_report_exports',
            'cancel_receipts'
        ];

        foreach ($sensitivePermissions as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    public function defaultDiocese()
    {
        return $this->belongsTo(Diocese::class, 'default_diocese_id');
    }

    public function defaultChurch()
    {
        return $this->belongsTo(Church::class, 'default_church_id');
    }

    public function activeChurch()
    {
        return $this->belongsTo(Church::class, 'active_church_id');
    }

    public function churchAccess()
    {
        return $this->hasMany(UserChurchAccess::class);
    }

    public function priest()
    {
        return $this->hasOne(Priest::class);
    }

    public function priestProfile()
    {
        return $this->hasOne(PriestProfile::class);
    }
}
