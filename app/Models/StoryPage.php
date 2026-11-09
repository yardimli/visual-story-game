<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StoryPage extends Model {
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $casts = ['narrative_delta'=>'array','interaction_plan_delta'=>'array','visual_document'=>'array','visual_revision'=>'integer'];
    protected static function booted(){static::saving(function($page){foreach(['narrative','interaction_plan'] as $field)if($page->isDirty($field) && !$page->isDirty($field.'_delta'))$page->{$field.'_delta'}=null;});}
    // Old revision/proposal payloads must not recreate page-level generation prompts.
    public function setScenePromptAttribute($value): void {}
    public function setVideoPromptAttribute($value): void {}
    protected $guarded = ['id'];
    public function story() { return $this->belongsTo(Story::class); }
}
