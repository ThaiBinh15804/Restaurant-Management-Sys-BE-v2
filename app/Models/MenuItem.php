<?php

namespace App\Models;

class MenuItem extends BaseModel
{
    protected $table = 'menu_items';
    protected $idPrefix = 'MI';

    protected $fillable = [
        'menu_id',
        'dish_id',
        'price',
        'notes',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function dish()
    {
        return $this->belongsTo(Dish::class, 'dish_id');
    }
}
