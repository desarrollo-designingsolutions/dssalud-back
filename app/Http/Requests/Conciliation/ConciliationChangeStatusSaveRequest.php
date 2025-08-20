<?php

namespace App\Http\Requests\Conciliation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConciliationChangeStatusSaveRequest extends FormRequest
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
            'reconciliation_group_id' => 'required',
            'status' => 'required',
            'reason' => 'required',
            'user_id' => 'required',
            'company_id' => 'required',
        ];

        return $rules;
    }

    public function messages()
    {
        return [
            'reconciliation_group_id.required' => 'El campo es obligatorio',
            'status.required' => 'El campo es obligatorio',
            'reason.required' => 'El campo es obligatorio',
            'company_id.required' => 'El campo es obligatorio',
            'user_id.required' => 'El campo es obligatorio',
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
