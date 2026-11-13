<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Story extends Model {
    protected $guarded = ['id'];
    protected $casts = ['audio_settings'=>'array','plan_approved_at'=>'datetime','inventory_document'=>'array','inventory_revision'=>'integer','game_flags'=>'array','flags_revision'=>'integer','image_order'=>'array'];
    public function pages() { return $this->hasMany(StoryPage::class)->orderBy('position'); }
    public function messages() { return $this->hasMany(ChatMessage::class); }
    public function assets() { return $this->hasMany(Asset::class); }
    public function revisions() { return $this->hasMany(StoryRevision::class); }
}
