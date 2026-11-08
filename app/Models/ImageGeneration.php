<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ImageGeneration extends Model {
 protected $guarded=['id'];
 protected $casts=['payload'=>'array','references'=>'array','pricing'=>'array','usage'=>'array','asset_ids'=>'array','started_at'=>'datetime'];
}
