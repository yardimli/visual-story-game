<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VideoGeneration extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['first_path', 'last_path', 'reference_paths', 'output_path', 'claim_key', 'worker_token_id', 'origin_token_id'];

    protected $casts = [
        'parameters' => 'array', 'reference_asset_ids' => 'array', 'reference_paths' => 'array', 'seed' => 'integer', 'engine_version' => 'integer',
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
            'first_frame_url' => $this->story_id ? ($this->first_path ? route('videos.frame', [$this->story_id, $this, 'first']) : ($this->first_asset_id ? route('assets.show', $this->first_asset_id) : null)) : null,
            'last_frame_url' => $this->story_id ? ($this->last_path ? route('videos.frame', [$this->story_id, $this, 'last']) : ($this->last_asset_id ? route('assets.show', $this->last_asset_id) : null)) : null,
            'has_last_frame' => (bool) $this->last_path,
            'reference_count' => count($this->reference_paths ?? []),
            'worker_offline' => in_array($this->status, ['claimed', 'submitting', 'running', 'uploading'])
                && $this->heartbeat_at?->lt(now()->subMinutes(2)),
            'url' => $this->status === 'completed' && $this->story_id
                ? route('videos.file', [$this->story_id, $this]) : null,
        ];
    }
}
