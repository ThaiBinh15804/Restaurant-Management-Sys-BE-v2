<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ingredients';

    /**
     * ID prefix for custom ID generation.
     *
     * @var string
     */
    protected $idPrefix = 'ING';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ingredient_category_id',
        'name',
        'unit',
        'current_stock',
        'min_stock',
        'max_stock',
        'is_active',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_stock' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'max_stock' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the category that owns the ingredient.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'ingredient_category_id');
    }

    /**
     * Get the stock import details for the ingredient.
     */
    public function stockImportDetails(): HasMany
    {
        return $this->hasMany(StockImportDetail::class, 'ingredient_id');
    }

    /**
     * Get the stock export details for the ingredient.
     */
    public function stockExportDetails(): HasMany
    {
        return $this->hasMany(StockExportDetail::class, 'ingredient_id');
    }

    /**
     * Get the stock losses for the ingredient.
     */
    public function stockLosses(): HasMany
    {
        return $this->hasMany(StockLoss::class, 'ingredient_id');
    }

    /**
     * Check if the ingredient stock is below minimum threshold.
     *
     * @return bool
     */
    public function isBelowMinStock(): bool
    {
        return $this->current_stock < $this->min_stock;
    }

    /**
     * Check if the ingredient stock is above maximum threshold.
     *
     * @return bool
     */
    public function isAboveMaxStock(): bool
    {
        return $this->max_stock !== null && $this->current_stock > $this->max_stock;
    }
}
