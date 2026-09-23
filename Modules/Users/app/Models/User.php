<?php

declare(strict_types=1);

namespace Modules\Users\Models;

use App\Contracts\AuditActor;
use App\Contracts\RoleAssignable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Users\Database\Factories\UserFactory;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable implements AuditActor, RoleAssignable
{
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'region', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'immutable_datetime',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function auditActorId(): int
    {
        return $this->id;
    }

    public function auditActorLabel(): string
    {
        return $this->email;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function permissionCodes(): array
    {
        return $this->getAllPermissions()->pluck('name')->sort()->values()->all();
    }
}
