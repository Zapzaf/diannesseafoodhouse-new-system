<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'can_approve_deliveries',
        'branch_id',
        'phone',
        'profile_photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_approve_deliveries' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function managedBranch(): HasMany
    {
        return $this->hasMany(Branch::class, 'manager_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isBranchManager(): bool
    {
        return $this->role === 'branch_manager';
    }

    public function isRegularUser(): bool
    {
        return $this->role === 'regular_user';
    }

    /**
     * Whether this account can approve/reject Delivery records — either by
     * role (admin always can) or via the standalone can_approve_deliveries
     * flag, which lets a specific person (e.g. a designated reviewer) be
     * granted delivery-approval rights without giving them full
     * admin/branch_manager access.
     */
    public function canApproveDeliveries(): bool
    {
        return $this->isAdmin() || (bool) $this->can_approve_deliveries;
    }
}
