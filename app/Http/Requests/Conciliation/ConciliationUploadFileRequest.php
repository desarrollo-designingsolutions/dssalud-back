<?php

namespace App\Http\Requests\Conciliation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConciliationUploadFileRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'file' => 'required|file',
            'user_id' => 'required|string',
            'company_id' => 'required|string',
            'reconciliation_group_id' => 'required|string',
        ];

        return $rules;
    }

    public function messages()
    {
        return [
            'file.required' => 'El archivo es obligatorio.',
            'file.file' => 'El archivo proporcionado no es válido.',
            'user_id.string' => 'El ID del docente debe ser una cadena de texto.',
            'user_id.required' => 'El archivo es obligatorio.',
            'company_id.string' => 'El ID de la compañía debe ser una cadena de texto.',
            'company_id.required' => 'El archivo es obligatorio.',
            'reconciliation_group_id.required' => 'El archivo es obligatorio.',
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
            'message' => 'Se evidencia algunos errores',
            'errors' => $validator->errors(),
        ], 422));
    }
}
