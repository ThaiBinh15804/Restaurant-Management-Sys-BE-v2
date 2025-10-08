<?php

namespace App\Models;

class DishCategory extends BaseModel
{
    protected $table = 'dish_categories';
    protected $idPrefix = 'DC';

    protected $fillable = [
        'name',
        'desc',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function dishes()
    {
        return $this->hasMany(Dish::class, 'category_id');
    }
}
