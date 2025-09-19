<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Traits\HasCustomId;
use App\Models\Traits\HasAuditFields;

abstract class BaseAuthenticatable extends Authenticatable
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
     * Global scope to only show active records.
     */
    protected static function booted()
    {
        parent::booted();
        
        // Remove the global scope - let individual models define their own scopes
    }
}