<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LlmRequest extends Model { public function imageGeneration() {return $this->hasOne(ImageGeneration::class);}
 protected $guarded = ['id']; protected $casts = ['pricing_snapshot'=>'array','request_content'=>'array','response_content'=>'array']; }
