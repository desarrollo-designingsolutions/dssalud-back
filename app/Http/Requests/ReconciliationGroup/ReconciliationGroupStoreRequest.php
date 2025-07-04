<?php

namespace App\Http\Requests\ReconciliationGroup;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReconciliationGroupStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'company_id' => 'required',
            'name' => 'required',
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'El campo es obligatorio',
            'name.required' => 'El campo es obligatorio',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('third_id')) {
            $merge['third_id'] = getValueSelectInfinite($this->third_id);
        }

        $this->merge($merge);
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
