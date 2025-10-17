<?php

namespace App\Models;

class Promotion extends BaseModel
{
    protected $table = 'promotions';
    protected $idPrefix = 'PRM';

    protected $fillable = [
        'code',
        'description',
        'discount_percent',
        'start_date',
        'end_date',
        'usage_limit',
        'used_count',
        'is_active',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'start_date'       => 'date',
        'end_date'         => 'date',
        'usage_limit'      => 'integer',
        'used_count'       => 'integer',
        'is_active'        => 'boolean',
    ];

    public function scopeCurrentlyValid($q)
    {
        $today = now()->toDateString();
        return $q->where('is_active', true)
                 ->where('start_date', '<=', $today)
                 ->where('end_date', '>=', $today)
                 ->where(function($s){
                     $s->whereColumn('used_count', '<', 'usage_limit')
                       ->orWhere('usage_limit', 0);
                 });
    }
}