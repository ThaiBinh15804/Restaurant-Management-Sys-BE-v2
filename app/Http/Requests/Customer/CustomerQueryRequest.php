<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseQueryRequest;
use App\Models\Customer;
use Illuminate\Validation\Rule;

class CustomerQueryRequest extends BaseQueryRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the query-specific validation rules.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function queryRules(): array
    {
        return [
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'gender' => 'sometimes|string|in:male,female,other',
            'membership_level' => [
                'sometimes',
                'integer',
                Rule::in([
                    Customer::MEMBERSHIP_BRONZE,
                    Customer::MEMBERSHIP_SILVER,
                    Customer::MEMBERSHIP_GOLD,
                    Customer::MEMBERSHIP_TITANIUM,
                ]),
            ],
            'user_id' => 'sometimes|string|exists:users,id',
        ];
    }

    /**
     * Get custom filter parameters.
     *
     * @return array
     */
    public function filters(): array
    {
        return $this->only([
            'full_name',
            'email',
            'phone',
            'gender',
            'membership_level',
            'user_id',
        ]);
    }
}
