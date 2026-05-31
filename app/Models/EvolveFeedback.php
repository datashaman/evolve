<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EvolveFeedback extends Model
{
    protected $table = 'evolve_feedback';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'type',
        'message',
        'source',
        'author',
        'url',
        'artifact_kind',
        'artifact_id',
        'context',
        'status',
        'priority',
        'labels',
        'assignee',
        'triage_notes',
        'triaged_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (EvolveFeedback $feedback): void {
            if (blank($feedback->id)) {
                $feedback->id = 'fb_'.Str::lower(Str::random(12));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'labels' => 'array',
            'triaged_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNotIn('status', ['rejected', 'done']);
    }

    #[Scope]
    protected function newest(Builder $query): void
    {
        $query->orderByDesc('created_at');
    }
}
