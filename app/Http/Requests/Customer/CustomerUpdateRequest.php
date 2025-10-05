<?php

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerUpdateRequest extends FormRequest
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
        $customerId = $this->route('id');

        return [
            // Customer information
            'full_name' => 'sometimes|required|string|max:255',
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('customers', 'phone')->ignore($customerId),
            ],
            'gender' => 'sometimes|required|string|in:male,female,other',
            'address' => 'nullable|string|max:500',
            'membership_level' => [
                'sometimes',
                'required',
                'integer',
                Rule::in([
                    Customer::MEMBERSHIP_BRONZE,
                    Customer::MEMBERSHIP_SILVER,
                    Customer::MEMBERSHIP_GOLD,
                    Customer::MEMBERSHIP_TITANIUM,
                ]),
            ],
            'user_id' => [
                'nullable',
                'string',
                'exists:users,id',
                Rule::unique('customers', 'user_id')->ignore($customerId),
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
            'full_name' => 'full name',
            'phone' => 'phone number',
            'gender' => 'gender',
            'address' => 'address',
            'membership_level' => 'membership level',
            'user_id' => 'user ID',
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
            'full_name.required' => 'The full name is required.',
            'phone.required' => 'The phone number is required.',
            'phone.unique' => 'This phone number is already registered.',
            'gender.required' => 'The gender is required.',
            'gender.in' => 'The gender must be male, female, or other.',
            'membership_level.required' => 'The membership level is required.',
            'membership_level.in' => 'The membership level must be 0 (Bronze), 1 (Silver), 2 (Gold), or 3 (Titanium). You can also use string labels: Bronze, Silver, Gold, or Titanium.',
            'user_id.exists' => 'The selected user does not exist.',
            'user_id.unique' => 'This user already has a customer profile.',
        ];
    }
}
