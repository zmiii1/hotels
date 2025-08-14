<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Permission;
use App\Notifications\AdminResetPasswordNotification;
use DB;

class User extends Authenticatable
{
    // FIX: Remove duplicate traits and HasApiTokens if not using Sanctum
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

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
     * Helper method to get permissions by group name
     */
    public static function getpermissionGroups()
    {
        $permission_groups = DB::table('permissions')
            ->select('group_name')
            ->groupBy('group_name')
            ->get();
        return $permission_groups;
    }

    /**
     * Get permissions by group name
     */
    public static function getpermissionByGroupName($group_name)
    {
        $permissions = DB::table('permissions')
            ->select('name','id')
            ->where('group_name', $group_name)
            ->get();
        return $permissions;
    }

    /**
     * Check if role has permissions
     */
    public static function roleHasPermissions($role, $permissions)
    {
        $hasPermission = true;
        foreach ($permissions as $permission) {
            if (!$role->hasPermissionTo($permission->name)) {
                $hasPermission = false;
                return $hasPermission; // FIX: Move return inside the if
            }
        }
        return $hasPermission;
    }

    /**
     * Check if user is any type of admin
     */
    public function isAdmin()
    {
        return $this->hasAnyRole(['Super Admin', 'Admin', 'Receptionist', 'Cashier']);
    }

    /**
     * Check if user is receptionist
     */
    public function isReceptionist()
    {
        return $this->hasRole('Receptionist');
    }

    /**
     * Check if user is cashier
     */
    public function isCashier()
    {
        return $this->hasRole('Cashier');
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin()
    {
        return $this->hasRole('Super Admin');
    }

    /**
     * Override the default password reset notification
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        // Check if user is admin/staff
        if ($this->hasAnyRole(['Super Admin', 'Admin', 'Receptionist', 'Cashier'])) {
            // Use custom notification for admin
            $this->notify(new AdminResetPasswordNotification($token));
        } else {
            // For regular users, use Laravel's default
            parent::sendPasswordResetNotification($token);
        }
    }
}