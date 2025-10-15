<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'suppliers';

    /**
     * ID prefix for custom ID generation.
     *
     * @var string
     */
    protected $idPrefix = 'SUP';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'phone',
        'contact_person_name',
        'contact_person_phone',
        'email',
        'address',
        'is_active',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the stock imports from this supplier.
     */
    public function stockImports(): HasMany
    {
        return $this->hasMany(StockImport::class, 'supplier_id');
    }
}
