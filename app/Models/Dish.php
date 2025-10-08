<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dish extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'price', 'desc', 'category_id', 'cooking_time', 'image', 'is_active'
    ];

    public function category()
    {
        return $this->belongsTo(DishCategory::class, 'category_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'target_id')->where('target_type', 1);
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class, 'dish_id');
    }
}