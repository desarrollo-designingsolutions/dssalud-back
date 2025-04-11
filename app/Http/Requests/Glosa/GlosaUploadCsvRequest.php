<?php

namespace App\Http\Requests\Glosa;

use App\Helpers\Constants;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GlosaUploadCsvRequest extends FormRequest
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
            'user_id' => 'required',
            'archiveCsv' => 'required|file|extensions:csv',
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'El campo es obligatorio',
            'archiveCsv.extensions' => 'El campo es archvio solo permite archivo de tipo csv',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->id ? formattedElement($this->id) : null,
            'user_id' => $this->user_id ? formattedElement($this->user_id) : null,
            'invoice_audit_id' => $this->invoice_audit_id ? formattedElement($this->invoice_audit_id) : null,
            'patient_id' => $this->patient_id ? formattedElement($this->patient_id) : null,
            'third_id' => $this->third_id ? formattedElement($this->third_id) : null,
            'assignment_batch_id' => $this->assignment_batch_id ? formattedElement($this->assignment_batch_id) : null,
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
