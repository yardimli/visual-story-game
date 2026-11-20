<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VideoGeneration extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['first_path', 'last_path', 'output_path', 'claim_key', 'worker_token_id', 'origin_token_id'];

    protected $casts = [
        'parameters' => 'array', 'seed' => 'integer', 'engine_version' => 'integer',
        'queued_at' => 'datetime', 'started_at' => 'datetime',
        'heartbeat_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($job) {
            $job->id ??= (string) Str::uuid();
        });
    }

    public function presentation(): array
    {
        return $this->toArray() + [
            'has_last_frame' => (bool) $this->last_path,
            'worker_offline' => in_array($this->status, ['claimed', 'submitting', 'running', 'uploading'])
                && $this->heartbeat_at?->lt(now()->subMinutes(2)),
            'url' => $this->status === 'completed' && $this->story_id
                ? route('videos.file', [$this->story_id, $this]) : null,
        ];
    }
}
