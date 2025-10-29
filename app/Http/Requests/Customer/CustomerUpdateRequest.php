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
     */
    public function rules(): array
    {
        $customerId = $this->route('id');

        return [
            // Basic information (optional fields)
            'full_name' => ['sometimes', 'nullable', 'string', 'max:255'],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('customers', 'phone')->ignore($customerId),
            ],

            'gender' => ['sometimes', 'nullable', Rule::in(['Nam', 'Nữ', 'Khác'])],

            'address' => ['sometimes', 'nullable', 'string', 'max:500'],

            'membership_level' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::in([
                    Customer::MEMBERSHIP_BRONZE,
                    Customer::MEMBERSHIP_SILVER,
                    Customer::MEMBERSHIP_GOLD,
                    Customer::MEMBERSHIP_TITANIUM,
                ]),
            ],

            'user_id' => [
                'sometimes',
                'nullable',
                'string',
                'exists:users,id',
                Rule::unique('customers', 'user_id')->ignore($customerId),
            ],

            // Avatar upload (multipart/form-data)
            'avatar' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,gif,webp',
                'max:2048', // 2MB
            ],
        ];
    }

    /**
     * Custom attribute labels.
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
            'avatar' => 'avatar image',
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number is already registered.',
            'gender.in' => 'The gender must be male, female, or other.',
            'membership_level.in' => 'The membership level must be one of: Bronze, Silver, Gold, or Titanium.',
            'user_id.exists' => 'The selected user does not exist.',
            'user_id.unique' => 'This user already has a customer profile.',
            'avatar.image' => 'The avatar must be an image file.',
            'avatar.mimes' => 'The avatar must be a file of type: jpeg, jpg, png, gif, or webp.',
            'avatar.max' => 'The avatar may not be greater than 2MB.',
        ];
    }
}
