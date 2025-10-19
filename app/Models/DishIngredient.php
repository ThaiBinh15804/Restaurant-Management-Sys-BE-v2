<?php

namespace App\Models;

class DishIngredient extends BaseModel
{
    protected $table = 'dish_ingredient';
    protected $idPrefix = 'DIING'; // prefix gợi ý, bạn có thể bỏ nếu không dùng auto-id

    protected $fillable = [
        'dish_id',
        'ingredient_id',
        'quantity',
        'note',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Món ăn (Dish) mà nguyên liệu này thuộc về
     */
    public function dish()
    {
        return $this->belongsTo(Dish::class, 'dish_id');
    }

    /**
     * Nguyên liệu (Ingredient) được sử dụng trong món ăn
     */
    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }
}
