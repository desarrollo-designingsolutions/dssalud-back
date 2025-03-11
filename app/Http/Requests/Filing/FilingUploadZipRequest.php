<?php

namespace App\Http\Requests\Filing;

use App\Helpers\Constants;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class FilingUploadZipRequest extends FormRequest
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
            'company_id' => 'required',
            'user_id' => 'required',
            // 'archiveZip' => 'required|file|mimes:zip',
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'El campo es obligatorio',
            'user_id.required' => 'El campo es obligatorio',
            'archiveZip.mimes' => 'El campo es archvio solo permite archivo de tipo zip',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'id' => $this->id ? formattedElement($this->id) : null,
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
