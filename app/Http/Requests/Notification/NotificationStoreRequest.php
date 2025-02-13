<?php

namespace App\Http\Requests\Notification;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class NotificationStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'exists:notifications,id', // Validar que los IDs existan
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'notification_ids.required' => 'El campo de IDs de notificación es obligatorio.',
            'notification_ids.array' => 'El campo de IDs de notificación debe ser un arreglo.',
            'notification_ids.*.exists' => 'El ID de notificación :input no existe en la base de datos.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([]);
    }

    public function failedValidation(Validator $validator)
    {

        throw new HttpResponseException(response()->json([
            'code' => 422,
            'message' => 'Hubo un error en la validación del formulario',
            'errors' => $validator->errors(),
        ], 422));
    }
}
