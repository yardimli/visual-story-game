<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AudioGeneration extends Model {
 public function accounting(){return $this->belongsTo(LlmRequest::class,'llm_request_id');}
 protected $guarded=['id'];protected $casts=['settings'=>'array','logs'=>'array','submitted_at'=>'datetime','started_at'=>'datetime'];
}
