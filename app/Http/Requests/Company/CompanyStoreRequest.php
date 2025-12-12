<?php

namespace App\Http\Requests\Company;

use App\Helpers\Constants;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CompanyStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required',
            'nit' => 'required',
            'phone' => 'required',
            'country_id' => 'required',
            'state_id' => 'required',
            'city_id' => 'required',
            'address' => 'required',
            'start_date' => 'required|date',
        ];

        if (! empty($this->email)) {
            $rules['email'] = 'required|unique:companies,email,'.$this->id.',id';
        }
        if (! empty($this->final_date)) {
            $rules['final_date'] = 'required|date|after:start_date';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'El Email ya existe',
            'nit.required' => 'El campo es obligatorio',
            'country_id.required' => 'El campo es obligatorio',
            'city_id.required' => 'El campo es obligatorio',
            'state_id.required' => 'El campo es obligatorio',
            'email.email' => 'El campo debe contener un correo valido',
            'start_date.required' => 'El campo es obligatorio',
            'final_date.required' => 'El campo es obligatorio',
            'final_date.after' => 'La fecha debe ser posterior a '.$this->start_date,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => formattedElement($this->id),
            'email' => formattedElement($this->email),
            'final_date' => formattedElement($this->final_date),
        ]);
    }

    public function failedValidation(Validator $validator)
    {

        throw new HttpResponseException(response()->json([
            'code' => 422,
            'message' => Constants::ERROR_MESSAGE_VALIDATION_BACK,
            'errors' => $validator->errors(),
        ], 422));
    }
}
