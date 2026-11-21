<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoryCharacter extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['views' => 'array', 'revision' => 'integer'];
}
