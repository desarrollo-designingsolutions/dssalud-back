<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;
    use HasUuids;

    protected $primaryKey = 'id';

    public function allUsers()
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public function getTypesAttribute()
    {
        // Convertir la cadena en array y limpiar espacios
        $types = array_map('trim', explode(',', $this->type));

        // Verificar si hay IDs válidos
        if (empty($types) || $types[0] === '') {
            return collect(); // Devuelve colección vacía si no hay IDs
        }

        // Buscar los sujetos
        return $types;
    }
}
