<?php

namespace App\Models;

class Employee extends BaseAuthenticatable
{
    /**
     * ID prefix cho Employee.
     *
     * @var string
     */
    protected $idPrefix = 'E';

    /**
     * Bảng trong database.
     *
     * @var string
     */
    protected $table = 'employees';

    protected $fillable = [
        'full_name',
        'phone',
        'gender',
        'address',
        'bank_account',
        'contract_type',
        'position',
        'base_salary',
        'hire_date',
        'is_active',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'base_salary' => 'decimal:2',
            'hire_date' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
