<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StoryPage extends Model {
    protected $guarded = ['id'];
    public function story() { return $this->belongsTo(Story::class); }
}
