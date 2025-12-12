<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class CommaSeparatedEmails implements Rule
{
    public function passes($attribute, $value)
    {
        // Dividir el string por comas y limpiar espacios
        $emails = array_map('trim', explode(',', $value));

        // Verificar que haya al menos un correo
        if (empty($emails)) {
            return false;
        }

        // Validar cada correo
        foreach ($emails as $email) {
            if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
        }

        return true;
    }

    public function message()
    {
        return 'El campo :attribute debe contener correos electrónicos válidos separados por comas.';
    }
}
