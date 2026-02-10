<?php

namespace App\Http\Requests\Contract;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ContractStoreRequest extends FormRequest
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
            'third_id' => 'required',
            'name' => [
                'required',
                Rule::unique('contracts')
                    ->where(function ($query) {
                        return $query->whereNull('deleted_at')
                            ->where('company_id', $this->company_id)
                            ->where('third_id', $this->third_id); // <-- Aquí está la validación del tercero
                    })
                    ->ignore($this->id),
            ],
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'El campo es obligatorio',
            'name.required' => 'El campo es obligatorio',
            'name.unique' => 'El nombre ya está en uso para este tercero en esta empresa',
            'third_id.required' => 'El campo es obligatorio',
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
