<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ScheduleConciliationStoreRequest extends FormRequest
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
            'title' => 'required',
            'description' => 'required',
            'end_date' => 'required',
            'start_date' => 'required',
            'third_id' => 'required',
            'user_id' => 'required',
            'emails' => 'nullable|array',
            'emails.*' => 'email',
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'El campo es obligatorio',
            'title.required' => 'El campo es obligatorio',
            'description.required' => 'El campo es obligatorio',
            'end_date.required' => 'El campo es obligatorio',
            'start_date.required' => 'El campo es obligatorio',
            'third_id.required' => 'El campo tercero es obligatorio',
            'user_id.required' => 'El campo usuario es obligatorio',
            'emails.array' => 'El campo debe ser un arreglo de correos electrónicos',
            'emails.*.email' => 'El campo :attribute debe ser un correo electrónico válido',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('emails') && is_string($this->emails)) {
            $emailsArray = array_filter(array_unique(array_map('trim', explode(',', $this->emails))));
            $merge['emails'] = $emailsArray;
        }

        if ($this->has('all_day')) {
            $merge['all_day'] = $this->all_day ? 1 : 0;
        }

        if ($this->has('user_id')) {
            $merge['user_id'] = getValueSelectInfinite($this->user_id);
        }

        if ($this->has('third_id')) {
            $merge['third_id'] = getValueSelectInfinite($this->third_id);
        }

        if ($this->has('reconciliation_group_id')) {
            $merge['reconciliation_group_id'] = getValueSelectInfinite($this->reconciliation_group_id);
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
