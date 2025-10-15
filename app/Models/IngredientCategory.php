<?php

namespace App\Models;

use App\Models\Traits\HasAuditFields;
use App\Models\Traits\HasCustomId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class IngredientCategory
 * 
 * @property string $id
 * @property string $name
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * 
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Ingredient[] $ingredients
 */
class IngredientCategory extends BaseModel
{
    use HasCustomId, HasAuditFields;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ingredient_categories';

    /**
     * Get the prefix for the custom ID.
     *
     * @return string
     */
    protected function getIdPrefix(): string
    {
        return 'ICA';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the ingredients for the category.
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class, 'ingredient_category_id');
    }

    /**
     * Get active ingredients for the category.
     */
    public function activeIngredients(): HasMany
    {
        return $this->ingredients()->where('is_active', true);
    }
}
