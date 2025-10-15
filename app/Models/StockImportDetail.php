<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockImportDetail extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_import_details';

    /**
     * ID prefix for custom ID generation.
     *
     * @var string
     */
    protected $idPrefix = 'SID';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ordered_quantity',
        'received_quantity',
        'unit_price',
        'total_price',
        'stock_import_id',
        'ingredient_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ordered_quantity' => 'decimal:2',
        'received_quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the stock import that owns this detail.
     */
    public function stockImport(): BelongsTo
    {
        return $this->belongsTo(StockImport::class, 'stock_import_id');
    }

    /**
     * Get the ingredient for this detail.
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    /**
     * Calculate total price (received_quantity * unit_price).
     *
     * @return float
     */
    public function calculateTotalPrice(): float
    {
        return $this->received_quantity * $this->unit_price;
    }

    /**
     * Get the quantity difference (ordered vs received).
     *
     * @return float
     */
    public function getQuantityDifference(): float
    {
        return $this->ordered_quantity - $this->received_quantity;
    }
}
