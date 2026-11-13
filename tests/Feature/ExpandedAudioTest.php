<?php
namespace Tests\Feature;
use App\Models\{User,Story,StorySound,AudioGeneration,LlmRequest};
use App\Services\{StoryAudio,LlmBudget,SceneDocument};
use App\Jobs\{GenerateStoryAudio,GenerateStorySpeech,ReconcileSpeechCost};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue,Storage,Cache};
use Illuminate\Support\Str;
use Tests\TestCase;
class ExpandedAudioTest extends TestCase {
 use RefreshDatabase;
 private function story():Story {$u=User::factory()->create();$this->actingAs($u);return Story::create(['user_id'=>$u->id,'title'=>'Audio','premise'=>'Test']);}
 private function catalog(string $id='heygen/heygen-voice-1-turbo'):void {Cache::forget('speech.catalog.v1');Http::fake(['openrouter.ai/api/v1/models*'=>Http::response(['data'=>[['id'=>$id,'name'=>'Voice','supported_voices'=>['nadine'],'pricing'=>['prompt'=>'0.000004','completion'=>'0.000009']]]])]);}
 public function test_music_defaults_save_and_reject_foreign_sounds():void {
  $s=$this->story();$sound=StorySound::create(['story_id'=>$s->id,'name'=>'Music','provider'=>'fal','path'=>'x','mime'=>'audio/wav']);
  $data=['music_id'=>$sound->id,'music_loop'=>true,'defaults'=>['pickup'=>['click'=>$sound->id,'action'=>null]]];
  $this->putJson(route('audio.settings',$s),$data)->assertOk();$this->getJson(route('audio.index',$s))->assertJsonPath('settings.music_loop',true);
  $other=$this->story();$this->putJson(route('audio.settings',$other),$data)->assertUnprocessable();
  $this->getJson(route('audio.index',$s))->assertNotFound();
 }
 public function test_three_voice_presets_are_saved_and_validated():void {
  $s=$this->story();$preset=['model'=>'google/gemini-3.8-flash-tts','voice'=>'Kore','instructions'=>'Warm','temperature'=>0.7,'language'=>''];
  $data=['music_id'=>null,'music_loop'=>false,'defaults'=>[],'voices'=>['scene'=>$preset,'area'=>array_replace($preset,['voice'=>'Puck']),'inventory'=>array_replace($preset,['voice'=>'Zephyr'])]];
  $this->putJson(route('audio.settings',$s),$data)->assertOk();
  $this->getJson(route('audio.index',$s))->assertJsonPath('settings.voices.scene.voice','Kore')->assertJsonPath('settings.voices.area.voice','Puck')->assertJsonPath('settings.voices.inventory.temperature',0.7);
  $data['voices']['scene']['model']='invalid';$this->putJson(route('audio.settings',$s),$data)->assertUnprocessable()->assertJsonValidationErrors('voices.scene.model');
 }
 public function test_audio_categories_can_be_renamed_added_and_assigned():void {
  $s=$this->story();$categories=\App\Http\Controllers\AudioController::CATEGORIES;$categories['scenes']='Narration';$categories['custom']='Weather';
  $this->putJson(route('audio.index',$s).'/categories',['categories'=>$categories])->assertOk();
  $this->getJson(route('audio.index',$s))->assertJsonPath('categories.scenes','Narration');
  $sound=StorySound::create(['story_id'=>$s->id,'name'=>'Rain','provider'=>'fal','path'=>'x','mime'=>'audio/wav']);
  $this->putJson(route('audio.index',$s).'/sounds/'.$sound->id,['category'=>'custom'])->assertOk();$this->assertSame('custom',$sound->fresh()->category);
  $this->putJson(route('audio.index',$s).'/sounds/'.$sound->id,['category'=>'unknown'])->assertUnprocessable();
  $other=$this->story();$this->putJson(route('audio.index',$other).'/sounds/'.$sound->id,['category'=>'effects'])->assertNotFound();
 }
 public function test_companion_prepares_missing_audio_without_charges_and_starts_only_on_request():void {
  $s=$this->story();Queue::fake();$s->update(['audio_settings'=>['voices'=>['scene'=>['model'=>'heygen/heygen-voice-1-turbo','voice'=>'nadine','language'=>'en']]]]);
  $s->pages()->create(['position'=>1,'title'=>'Hall','narrative'=>'Quiet hall.','visual_document'=>['visuals'=>[['name'=>'Room','kind'=>'scene','description'=>'Quiet hall.','hotspots'=>[['label'=>'Door','description'=>'Locked.']]]]]]);
  StorySound::create(['story_id'=>$s->id,'name'=>'Door','source_text'=>'Locked.','provider'=>'openrouter','path'=>'x','mime'=>'audio/wav']);
  $s->refresh();$proposal=['changes'=>[['scope'=>'missing_audio']]];$proposal['_review']=app(\App\Services\CompanionChanges::class)->prepare($s,$proposal);$this->assertCount(1,$proposal['_review']['items']);
  $m=$s->messages()->create(['role'=>'assistant','content'=>'Drafts','revision'=>$s->revision,'proposal'=>$proposal]);app(\App\Services\CompanionChanges::class)->apply($s,$m,[0]);
  $job=AudioGeneration::firstOrFail();$this->assertSame('draft',$job->status);$this->assertSame('nadine',$job->settings['voice']);$this->assertDatabaseCount('llm_requests',0);Queue::assertNothingPushed();
  $this->assertCount(0,app(\App\Services\AudioDrafts::class)->missing($s->fresh()));
  config(['story.openrouter_key'=>'test']);$this->catalog();$this->postJson(route('audio.index',$s).'/jobs/'.$job->id.'/start')->assertAccepted();$this->assertSame('queued',$job->fresh()->status);Queue::assertPushed(GenerateStorySpeech::class,1);
 }
 public function test_editing_queued_audio_releases_credit_and_invalidates_old_worker():void {
  $s=$this->story();Queue::fake();config(['story.openrouter_key'=>'test']);$this->catalog();
  $id=$this->postJson(route('audio.index',$s).'/speech',['request_key'=>(string)Str::uuid(),'name'=>'Voice','prompt'=>'Hello','source_text'=>'Hello','model'=>'heygen/heygen-voice-1-turbo','settings'=>['voice'=>'nadine']])->assertAccepted()->json('id');
  $edited=$this->putJson(route('audio.index',$s).'/jobs/'.$id,['name'=>'New','prompt'=>'Good evening','source_text'=>'Hello','category'=>'scenes','model'=>'heygen/heygen-voice-1-turbo','settings'=>['voice'=>'nadine'],'seconds'=>0])->assertOk()->json('id');
  $this->assertNotEquals($id,$edited);$this->assertNull(AudioGeneration::find($id));$this->assertEquals(0,auth()->user()->fresh()->llm_reserved);$this->assertSame('draft',AudioGeneration::find($edited)->status);
  Http::fake();(new GenerateStorySpeech($id))->handle(new StoryAudio(),new LlmBudget());Http::assertNothingSent();
  $this->deleteJson(route('audio.index',$s).'/jobs/'.$edited)->assertOk();$this->assertNull(AudioGeneration::find($edited));
 }
 public function test_started_audio_cannot_be_edited_or_deleted_and_sound_delete_clears_usage():void {
  $s=$this->story();$g=AudioGeneration::create(['story_id'=>$s->id,'request_key'=>(string)Str::uuid(),'name'=>'Running','prompt'=>'Hello','seconds'=>3,'status'=>'queued','submitted_at'=>now()]);
  $this->deleteJson(route('audio.index',$s).'/jobs/'.$g->id)->assertConflict();
  Storage::fake('local');Storage::disk('local')->put('sound.wav','audio');$sound=StorySound::create(['story_id'=>$s->id,'name'=>'Sound','provider'=>'fal','path'=>'sound.wav','mime'=>'audio/wav']);
  $s->update(['audio_settings'=>['music_id'=>$sound->id,'defaults'=>['pickup'=>['action'=>$sound->id]]],'inventory_document'=>['visuals'=>[['name'=>'Key','sound_id'=>$sound->id]]]]);
  $p=$s->pages()->create(['position'=>1,'title'=>'Hall','visual_document'=>['visuals'=>[['name'=>'Room','music_mode'=>'replace','music_id'=>$sound->id,'hotspots'=>[['label'=>'Door','action_sound_id'=>$sound->id]]]]]]);
  $url=route('audio.index',$s).'/sounds/'.$sound->id;$this->getJson($url.'/usage')->assertOk()->assertJsonCount(5,'uses');$this->deleteJson($url,['confirm'=>true])->assertOk();
  $this->assertNull($s->fresh()->audio_settings['music_id']);$this->assertSame('silent',$s->fresh()->inventory_document['visuals'][0]['sound_id']);$this->assertSame('stop',$p->fresh()->visual_document['visuals'][0]['music_mode']);$this->assertSame('silent',$p->fresh()->visual_document['visuals'][0]['hotspots'][0]['action_sound_id']);
 }
 public function test_cassette_reserves_platform_credit_even_with_personal_openrouter_key_and_settles_once():void {
  $s=$this->story();auth()->user()->update(['openrouter_key'=>'sk-or-personal-test']);Queue::fake();Storage::fake('local');config(['services.fal.key'=>'test']);
  $data=['request_key'=>(string)Str::uuid(),'name'=>'Door','prompt'=>'Door creaks','seconds'=>3,'model'=>'cassetteai/sound-effects-generator'];
  $id=$this->postJson(route('audio.generate',$s),$data)->assertAccepted()->json('id');$g=AudioGeneration::findOrFail($id);$a=$g->accounting;
  $this->assertSame('platform',$a->funding);$this->assertEquals(60000,$a->reserved);
  $this->postJson(route('audio.generate',$s),$data)->assertAccepted();$this->assertDatabaseCount('llm_requests',1);
  $base='https://queue.fal.run/cassetteai/sound-effects-generator';
  Http::fake([$base=>Http::response(['request_id'=>'cassette-1']),$base.'/requests/cassette-1/status*'=>Http::response(['status'=>'COMPLETED']),$base.'/requests/cassette-1'=>Http::response(['audio_file'=>['url'=>'https://fal.media/a.wav']]),'fal.media/*'=>Http::response(\App\Services\SpeechCatalog::wav(str_repeat("\0",144000),24000))]);
  $job=new GenerateStoryAudio($id);$job->handle(new StoryAudio());$job->handle(new StoryAudio());$job->handle(new StoryAudio());
  Http::assertSent(fn($r)=>$r->method()==='POST'&&$r['duration']===3&&!isset($r['seconds_total']));
  $this->assertSame('completed',$g->fresh()->status);$this->assertEquals(60000,$a->fresh()->cost);$this->assertEquals(0,auth()->user()->fresh()->llm_reserved);$this->assertEquals(60000,auth()->user()->fresh()->llm_spent);
 }
 public function test_speech_is_queued_private_billed_by_characters_and_pcm_becomes_wav():void {
  $s=$this->story();Queue::fake();Storage::fake('local');config(['story.openrouter_key'=>'test','story.openrouter_referer'=>'https://vsg.playground.computer']);$this->catalog();
  $data=['request_key'=>(string)Str::uuid(),'name'=>'Door','prompt'=>'Hello','source_text'=>'A locked door.','category'=>'click_areas','model'=>'heygen/heygen-voice-1-turbo','settings'=>['voice'=>'nadine','language'=>'en']];
  $url=route('audio.index',$s).'/speech';$id=$this->postJson($url,$data)->assertAccepted()->json('id');$this->postJson($url,$data)->assertAccepted();Queue::assertPushed(GenerateStorySpeech::class,1);
  Http::fake(['openrouter.ai/api/v1/audio/speech'=>Http::response(str_repeat("\0",88200),200,['Content-Type'=>'audio/pcm','X-Generation-Id'=>'speech-1'])]);
  $job=new GenerateStorySpeech($id);$job->handle(new StoryAudio(),new LlmBudget());$job->handle(new StoryAudio(),new LlmBudget());Http::assertSentCount(1);
  Http::assertSent(fn($r)=>$r->hasHeader('HTTP-Referer','https://vsg.playground.computer')&&$r->hasHeader('X-OpenRouter-Title',config('app.name'))&&$r['response_format']==='pcm');
  $g=AudioGeneration::findOrFail($id);$this->assertSame('completed',$g->status);$this->assertEquals(20,$g->accounting->cost);$sound=StorySound::findOrFail($g->sound_id);$this->assertSame('audio/wav',$sound->mime);$this->assertSame('A locked door.',$sound->source_text);$this->assertSame('click_areas',$sound->category);$this->assertStringStartsWith('RIFF',Storage::disk('local')->get($sound->path));
  $this->assertStringNotContainsString('Bearer',json_encode($g->accounting->request_content));
 }
 public function test_audio_queue_age_does_not_release_reservation():void {
  $s=$this->story();$budget=new LlmBudget();$a=$budget->reserve(auth()->user(),$s,(string)Str::uuid(),'fal-ai/stable-audio',60000,true);
  AudioGeneration::create(['story_id'=>$s->id,'llm_request_id'=>$a->id,'request_key'=>$a->request_key,'name'=>'Queued','prompt'=>'Door','seconds'=>3]);$this->travel(20)->minutes();$budget->expireOverdue();$this->assertSame('reserved',$a->fresh()->status);$this->assertEquals(60000,auth()->user()->fresh()->llm_reserved);
 }
 public function test_gemini_cost_uses_native_tokens_not_zero_byok_cost():void {
  $s=$this->story();$this->catalog('google/gemini-3.8-flash-tts');Queue::fake();Storage::fake('local');config(['story.openrouter_key'=>'test']);
  $id=$this->postJson(route('audio.index',$s).'/speech',['request_key'=>(string)Str::uuid(),'name'=>'Hello','prompt'=>'Hello','source_text'=>'Hello','model'=>'google/gemini-3.8-flash-tts','settings'=>['voice'=>'nadine','instructions'=>'Warm','temperature'=>.7]])->assertAccepted()->json('id');
  Http::fake(['openrouter.ai/api/v1/audio/speech'=>Http::response(str_repeat("\0",48000),200,['Content-Type'=>'audio/pcm','X-Generation-Id'=>'google-1']),'openrouter.ai/api/v1/generation*'=>Http::response(['data'=>['native_tokens_prompt'=>10,'native_tokens_completion'=>25,'total_cost'=>0]])]);
  (new GenerateStorySpeech($id))->handle(new StoryAudio(),new LlmBudget());$g=AudioGeneration::find($id);$this->assertEquals(265,$g->accounting->cost);$this->assertEquals(25,$g->accounting->completion_tokens);
 }
 public function test_failed_speech_releases_credit_without_retrying_submission():void {
  $s=$this->story();$this->catalog();Queue::fake();config(['story.openrouter_key'=>'test']);
  $id=$this->postJson(route('audio.index',$s).'/speech',['request_key'=>(string)Str::uuid(),'name'=>'Voice','prompt'=>'Hello','source_text'=>'Hello','model'=>'heygen/heygen-voice-1-turbo','settings'=>['voice'=>'nadine']])->assertAccepted()->json('id');
  Http::fake(['openrouter.ai/api/v1/audio/speech'=>Http::response(['error'=>'Rejected'],400)]);
  $job=new GenerateStorySpeech($id);$job->handle(new StoryAudio(),new LlmBudget());$job->handle(new StoryAudio(),new LlmBudget());Http::assertSentCount(1);
  $g=AudioGeneration::find($id);$this->assertSame('failed',$g->status);$this->assertEquals(0,$g->accounting->cost);$this->assertEquals(0,auth()->user()->fresh()->llm_reserved);
 }
 public function test_speech_cost_reconciliation_does_not_generate_audio_again():void {
  $s=$this->story();config(['story.openrouter_key'=>'test']);$budget=new LlmBudget();
  $a=$budget->reserve(auth()->user(),$s,(string)Str::uuid(),'google/gemini-3.8-flash-tts',100000);$a->update(['pricing_snapshot'=>['prompt'=>.0000005,'completion'=>.000009]]);
  $g=AudioGeneration::create(['story_id'=>$s->id,'llm_request_id'=>$a->id,'request_key'=>$a->request_key,'model'=>$a->model,'name'=>'Voice','prompt'=>'Hello','seconds'=>0,'status'=>'completed','provider_request_id'=>'gen-1']);
  $budget->settle($a,null,'completed','gen-1');$this->travel(20)->minutes();$budget->expireOverdue();$this->assertSame('uncertain',$a->fresh()->status);
  Http::fake(['openrouter.ai/api/v1/generation*'=>Http::response(['data'=>['native_tokens_prompt'=>10,'native_tokens_completion'=>20,'total_cost'=>0]])]);
  (new ReconcileSpeechCost($g->id))->handle($budget);$this->assertEquals(185,$a->fresh()->cost);$this->assertEquals(0,auth()->user()->fresh()->llm_reserved);Http::assertSent(fn($r)=>$r->method()==='GET');
 }
 public function test_scene_and_inventory_sound_settings_round_trip():void {
  $s=$this->story();$sound=StorySound::create(['story_id'=>$s->id,'name'=>'Sound','provider'=>'fal','path'=>'x','mime'=>'audio/wav']);
  $scene=['id'=>(string)Str::uuid(),'name'=>'Hall','kind'=>'scene','asset_id'=>null,'prompt'=>'','hotspots'=>[],'music_mode'=>'replace','music_id'=>$sound->id,'music_loop'=>true,'sound_id'=>null,'action_sound_id'=>'silent'];
  $item=['id'=>(string)Str::uuid(),'name'=>'Key','kind'=>'object','asset_id'=>null,'prompt'=>'','hotspots'=>[],'sound_id'=>'silent','action_sound_id'=>$sound->id];
  $valid=app(SceneDocument::class)->validate($s,['visuals'=>[$scene,$item]]);$this->assertSame('silent',$valid['visuals'][1]['sound_id']);$this->assertSame($sound->id,$valid['visuals'][0]['music_id']);
 }
}
