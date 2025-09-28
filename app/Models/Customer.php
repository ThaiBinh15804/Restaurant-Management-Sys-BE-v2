<?php

namespace App\Models;

class Customer extends BaseAuthenticatable
{
    /**
     * ID prefix for custom ID generation.
     *
     * @var string
     */
    protected $idPrefix = 'C';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customers';

    protected $fillable = [
        'full_name',
        'phone',
        'gender',
        'address',
        'membership_level',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'membership_level' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
