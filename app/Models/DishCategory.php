<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DishCategory extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'desc'];

    public function dishes()
    {
        return $this->hasMany(Dish::class, 'category_id');
    }
}