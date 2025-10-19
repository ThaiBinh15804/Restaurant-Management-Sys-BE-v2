<?php

namespace App\Models;

class Dish extends BaseModel
{
    protected $table = 'dishes';
    protected $idPrefix = 'DI';

    protected $fillable = [
        'name',
        'price',
        'desc',
        'category_id',
        'cooking_time',
        'image',
        'is_active',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cooking_time' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(DishCategory::class, 'category_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'dish_id');
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class, 'dish_id');
    }

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'dish_ingredient', 'dish_id', 'ingredient_id')
            ->withPivot('quantity', 'note')
            ->withTimestamps();
    }
}
