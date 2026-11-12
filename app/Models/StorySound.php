<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StorySound extends Model {
 protected $guarded=['id'];protected $hidden=['path'];protected $appends=['url'];
 public function getUrlAttribute(){return route('audio.file',$this);}
}
