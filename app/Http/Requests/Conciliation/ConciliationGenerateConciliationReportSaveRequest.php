<?php

namespace App\Http\Requests\Conciliation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConciliationGenerateConciliationReportSaveRequest extends FormRequest
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
            'company_id' => 'required',
            'user_id' => 'required',
            'dateConciliation' => 'required',
            'nameIPSrepresentative' => 'required',
            'positionIPSrepresentative' => 'required',
            'elaborator_id' => 'required',
            'elaborator_position' => 'required',
        ];

        return $rules;
    }

    public function messages()
    {
        return [
            'reconciliation_group_id.required' => 'El campo es obligatorio',
            'company_id.required' => 'El campo es obligatorio',
            'user_id.required' => 'El campo es obligatorio',
            'dateConciliation.required' => 'El campo es obligatorio',
            'nameIPSrepresentative.required' => 'El campo es obligatorio',
            'positionIPSrepresentative.required' => 'El campo es obligatorio',
            'elaborator_id.required' => 'El campo es obligatorio',
            'elaborator_position.required' => 'El campo es obligatorio',
        ];
    }

       protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('elaborator_id')) {
            $merge['elaborator_id'] = getValueSelectInfinite($this->elaborator_id);
        }
        if ($this->has('reviewer_id')) {
            $merge['reviewer_id'] = getValueSelectInfinite($this->reviewer_id);
        }
        if ($this->has('approver_id')) {
            $merge['approver_id'] = getValueSelectInfinite($this->approver_id);
        }
        if ($this->has('legal_representative_id')) {
            $merge['legal_representative_id'] = getValueSelectInfinite($this->legal_representative_id);
        }
        if ($this->has('health_audit_director_id')) {
            $merge['health_audit_director_id'] = getValueSelectInfinite($this->health_audit_director_id);
        }
        if ($this->has('vp_planning_control_id')) {
            $merge['vp_planning_control_id'] = getValueSelectInfinite($this->vp_planning_control_id);
        }

        $this->merge($merge);
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
