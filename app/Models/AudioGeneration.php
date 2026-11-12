<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AudioGeneration extends Model {
 protected $guarded=['id'];protected $casts=['logs'=>'array','submitted_at'=>'datetime','started_at'=>'datetime'];
}
