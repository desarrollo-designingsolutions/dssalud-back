<?php

namespace App\Http\Requests\Authentication;

use App\Helpers\Constants;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PassportAuthRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|max:55',
            'surname' => 'required|max:55',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
            // 'password' => 'required|min:8|max:12|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d]{8,12}$/',
            'role_id' => 'required|exists:roles,id', // Asegura que role_id exista en la tabla roles

        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre debe tener un máximo de 55 caracteres.',
            'surname.required' => 'El apellido es obligatorio.',
            'surname.max' => 'El apellido debe tener un máximo de 55 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser una dirección válida.',
            'email.unique' => 'El correo electrónico ya está en uso.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no puede exceder los 12 caracteres.',
            'password.regex' => 'La contraseña debe contener al menos una letra mayúscula, una letra minúscula y un número.',
            'role_id.required' => 'El rol es obligatorio.',
            'role_id.exists' => 'El rol seleccionado no existe en la base de datos.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([]);
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'status' => 422,
            'message' => Constants::ERROR_MESSAGE_VALIDATION_BACK,
            'errors' => $validator->errors(),
        ]));
    }
}
