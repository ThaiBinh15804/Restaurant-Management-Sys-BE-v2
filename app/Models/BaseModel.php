<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasCustomId;
use App\Models\Traits\HasAuditFields;

abstract class BaseModel extends Model
{
    use HasCustomId, HasAuditFields;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'created_by',
        'updated_by',
    ];

    /**
     * Scope a query to only include active records.
     */
    public function scopeActive($query)
    {
        if (in_array('is_active', $this->getFillable()) || 
            array_key_exists('is_active', $this->getCasts())) {
            return $query->where('is_active', true);
        }
        
        return $query;
    }

    /**
     * Get all fillable attributes including base attributes.
     *
     * @return array
     */
    public function getFillable()
    {
        return array_merge($this->fillable, [
            'created_by',
            'updated_by'
        ]);
    }
}