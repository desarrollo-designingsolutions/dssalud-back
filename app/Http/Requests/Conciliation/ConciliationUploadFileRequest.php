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
            // 'file' => 'required|file|mimes:csv|max:10240',
            'user_id' => 'nullable|string',
            'company_id' => 'nullable|string',
        ];

        return $rules;
    }

    public function messages()
    {
        return [
            'file.required' => 'El archivo es obligatorio.',
            'file.file' => 'El archivo proporcionado no es válido.',
            'file.mimes' => 'El archivo debe ser de tipo Excel (.csv).',
            'file.max' => 'El archivo no debe exceder los 10 MB.',
            'user_id.string' => 'El ID del docente debe ser una cadena de texto.',
            'company_id.string' => 'El ID de la compañía debe ser una cadena de texto.',
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
