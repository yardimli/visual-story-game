<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StoryPage extends Model {
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $casts = ['visual_document'=>'array','visual_revision'=>'integer'];
    protected $guarded = ['id'];
    public function story() { return $this->belongsTo(Story::class); }
}
