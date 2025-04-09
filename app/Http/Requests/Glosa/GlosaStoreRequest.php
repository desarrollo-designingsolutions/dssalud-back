<?php

namespace App\Http\Requests\Glosa;

use App\Helpers\Constants;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

use function PHPUnit\Framework\isArray;

class GlosaStoreRequest extends FormRequest
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
            'service_id' => 'required',
            'code_glosa_id' => 'required',
            'glosa_value' => 'required|numeric|gt:0',
            'observation' => 'required',
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'El campo es obligatorio',
            'service_id.required' => 'El campo es obligatorio',
            'code_glosa_id.required' => 'El campo es obligatorio',
            'glosa_value.required' => 'El campo es obligatorio',
            'glosa_value.numeric' => 'El valor debe ser numérico',
            'glosa_value.gt' => 'El valor debe ser mayor que cero',
            'observation.required' => 'El campo es obligatorio',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code_glosa_id' => isArray($this->code_glosa_id) ? $this->code_glosa_id['value'] : $this->code_glosa_id,
            'glosa_value' => ! is_numeric($this->glosa_value) ? floatval($this->glosa_value) : $this->glosa_value,
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
