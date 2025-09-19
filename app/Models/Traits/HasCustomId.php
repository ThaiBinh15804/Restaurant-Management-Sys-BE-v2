<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;

trait HasCustomId
{
    /**
     * Boot the trait.
     */
    protected static function bootHasCustomId()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = $model->generateCustomId();
            }
        });
    }

    /**
     * Generate a custom ID.
     *
     * @return string
     */
    protected function generateCustomId(): string
    {
        $prefix = $this->getIdPrefix();
        $length = $this->getIdLength();
        
        do {
            $id = $prefix . strtoupper(Str::random($length - strlen($prefix)));
        } while (static::where($this->getKeyName(), $id)->exists());

        return $id;
    }

    /**
     * Get the prefix for the ID.
     *
     * @return string
     */
    protected function getIdPrefix(): string
    {
        return property_exists($this, 'idPrefix') ? $this->idPrefix : '';
    }

    /**
     * Get the length of the ID.
     *
     * @return int
     */
    protected function getIdLength(): int
    {
        return property_exists($this, 'idLength') ? $this->idLength : 10;
    }
}