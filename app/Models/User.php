<?php

namespace App\Models;

use App\Traits\Cacheable;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Cacheable, HasApiTokens, HasFactory, HasPermissions, HasRoles, HasUuids, Notifiable, Searchable;

    use \OwenIt\Auditing\Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Sobrescribir el método para personalizar el texto de la acción
    public function getActionDescription($event)
    {
        return match ($event) {
            'created' => 'Creación de un usuario',
            'updated' => 'Actualización de un usuario',
            'deleted' => 'Eliminación de un usuario',
            default => ''
        };
    }

    // Auditoria
    public function getColumnsConfig()
    {
        return [
            'name' => [
                'label' => 'Nombres',
            ],
            'surname' => [
                'label' => 'Apellidos',
            ],
            'email' => [
                'label' => 'Correo electrónico',
            ],
            'role_id' => [
                'label' => 'Rol',
                'model' => 'Role',
                'model_field' => 'description',
            ],
        ];
    }

    // Método de acceso para combinar nombre y apellido
    public function getFullNameAttribute()
    {
        return $this->name . ' ' . $this->surname;
    }

    public function getAllPermissionsAttribute()
    {
        return $this->getAllPermissions();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function notificaciones()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    public function third()
    {
        return $this->belongsTo(Third::class);
    }

    public function thirds()
    {
        return $this->belongsToMany(Third::class, 'user_thirds', 'user_id', 'third_id');
    }
}
