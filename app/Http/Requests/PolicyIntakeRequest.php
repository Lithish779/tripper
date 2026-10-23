<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PolicyIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_request_id' => ['required', 'string', 'max:100'],
            'agent_id' => ['required', 'string', 'max:100'],
            'customer_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:trip_start'],
            'destination' => ['required', 'string', 'in:DOMESTIC,SCHENGEN,INTERNATIONAL,US_CANADA'],
            'trip_start' => ['required', 'date'],
            'trip_end' => ['required', 'date', 'after_or_equal:trip_start'],
            'plan_code' => ['required', 'string', 'in:BASIC,PLUS,COMPREHENSIVE'],
            'sum_insured' => ['required', 'numeric', 'in:100000,300000,500000,1000000'],
            'mock_scenario' => ['nullable', 'string', 'in:SUCCESS,TIMEOUT,REJECTED,TEMP_ERROR,SLOW_RESPONSE'],
        ];
    }

    public function messages(): array
    {
        return [
            'trip_end.after_or_equal' => 'The trip_end date must be greater than or equal to trip_start.',
            'date_of_birth.before' => 'The date_of_birth must be before trip_start.',
            'plan_code.in' => 'The selected plan_code is invalid. Supported plans: BASIC, PLUS, COMPREHENSIVE.',
            'destination.in' => 'The selected destination is invalid. Supported destinations: DOMESTIC, SCHENGEN, INTERNATIONAL, US_CANADA.',
            'sum_insured.in' => 'The sum_insured tier is unsupported. Supported coverage tiers: 100000, 300000, 500000, 1000000.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed for policy intake request.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
