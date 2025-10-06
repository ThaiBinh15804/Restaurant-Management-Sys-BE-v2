<?php

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerStatusRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'membership_level' => [
                'required',
                'integer',
                Rule::in([
                    Customer::MEMBERSHIP_BRONZE,
                    Customer::MEMBERSHIP_SILVER,
                    Customer::MEMBERSHIP_GOLD,
                    Customer::MEMBERSHIP_TITANIUM,
                ]),
            ],
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'membership_level' => 'membership level',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'membership_level.required' => 'The membership level is required.',
            'membership_level.in' => 'The membership level must be 0 (Bronze), 1 (Silver), 2 (Gold), or 3 (Titanium).',
        ];
    }
}
