<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaticContent extends Model
{
    protected $fillable = [
        'icon',
        'title',
        'summary',
        'position',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }
}