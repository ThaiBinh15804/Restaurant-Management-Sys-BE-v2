<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'description', 'version', 'is_active'];

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class, 'menu_id');
    }
}