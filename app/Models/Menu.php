<?php

namespace App\Models;

class Menu extends BaseModel
{
    protected $table = 'menus';
    protected $idPrefix = 'ME';

    protected $fillable = [
        'name',
        'description',
        'version',
        'is_active',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'version' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(MenuItem::class, 'menu_id');
    }
}
