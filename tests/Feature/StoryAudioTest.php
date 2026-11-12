<?php
namespace Tests\Feature;
use App\Models\{User,Story,StorySound,AudioGeneration};
use App\Services\{StoryAudio,SceneDocument};
use App\Jobs\GenerateStoryAudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Queue,Storage,Cache};
use Illuminate\Support\Str;
use Tests\TestCase;
class StoryAudioTest extends TestCase {
 use RefreshDatabase;
 private function story():Story {$u=User::factory()->create();$this->actingAs($u);return Story::create(['user_id'=>$u->id,'title'=>'Sound story','premise'=>'Test']);}
 private function wav():string {return 'RIFF'.pack('V',2036).'WAVEfmt '.pack('VvvVVvv',16,1,1,8000,16000,2,16).'data'.pack('V',2000).str_repeat("\0",2000);}
 public function test_search_cache_and_import_preserve_credit_metadata_and_are_private():void {
  $s=$this->story();Storage::fake('local');config(['services.freesound.key'=>'test-token']);$query='test-'.Str::uuid();$id=random_int(1000000,9000000);
  Http::fake(['freesound.org/apiv2/search/*'=>Http::response(['count'=>1,'next'=>null,'results'=>[['id'=>$id,'name'=>'Door','username'=>'Artist','license'=>'https://creativecommons.org/licenses/by/4.0/','url'=>'https://freesound.org/s/'.$id,'duration'=>2,'previews'=>['preview-hq-mp3'=>'https://cdn.freesound.org/test.mp3']]]]),'cdn.freesound.org/*'=>Http::response($this->wav(),200,['Content-Type'=>'audio/wav'])]);
  $url=route('audio.search',$s).'?query='.$query;
  $this->getJson($url)->assertOk()->assertJsonPath('results.0.creator','Artist');$this->getJson($url)->assertOk();Http::assertSentCount(1);
  Http::assertSent(fn($r)=>$r->hasHeader('Authorization','Token test-token'));
  $sound=$this->postJson(route('audio.add',$s),['id'=>$id])->assertCreated()->json();$this->assertArrayNotHasKey('path',$sound);
  $this->postJson(route('audio.add',$s),['id'=>$id])->assertOk();$this->assertDatabaseCount('story_sounds',1);Http::assertSentCount(2);
  $this->get(route('audio.file',$sound['id']))->assertOk()->assertHeader('Content-Type','audio/x-wav');
  $this->actingAs(User::factory()->create());$this->getJson(route('audio.index',$s))->assertNotFound();$this->get(route('audio.file',$sound['id']))->assertNotFound();
  Cache::store('file')->forget('freesound.search.v2.'.hash('sha256',$query.'|1|3'));Cache::store('file')->forget('freesound.sound.'.$id);
 }
 public function test_duration_filter_is_sent_and_cached_separately():void {
  $s=$this->story();config(['services.freesound.key'=>'test-token']);$query='duration-'.Str::uuid();
  $sound=['id'=>99999999,'name'=>'Short','username'=>'Artist','license'=>'CC0','url'=>'https://freesound.org/s/99999999','previews'=>['preview-hq-mp3'=>'https://cdn.freesound.org/short.mp3']];
  Http::fake(['freesound.org/apiv2/search/*'=>Http::response(['count'=>2,'next'=>null,'results'=>[$sound+['duration'=>3],array_merge($sound,['duration'=>4,'id'=>99999998])]])]);
  $url=route('audio.search',$s).'?query='.$query;
  $this->getJson($url)->assertOk()->assertJsonCount(1,'results');
  Http::assertSent(fn($r)=>$r['filter']==='duration:[0 TO 3]');
  $this->getJson($url.'&max_duration=3.0')->assertOk();Http::assertSentCount(1);
  $this->getJson($url.'&max_duration=5')->assertOk()->assertJsonCount(2,'results');Http::assertSentCount(2);
  $this->getJson($url.'&max_duration=')->assertOk()->assertJsonCount(2,'results');Http::assertSentCount(3);
  $this->getJson($url.'&max_duration=-1')->assertUnprocessable();Http::assertSentCount(3);
  foreach(['3','5','all'] as $limit)Cache::store('file')->forget('freesound.search.v2.'.hash('sha256',$query.'|1|'.$limit));
  foreach([99999999,99999998] as $id)Cache::store('file')->forget('freesound.sound.'.$id);
 }
 public function test_generation_is_queued_once_for_repeated_request_key():void {
  $s=$this->story();Queue::fake();config(['services.fal.key'=>'test']);$data=['request_key'=>(string)Str::uuid(),'name'=>'Door','prompt'=>'A creaky wooden door','seconds'=>5];
  $first=$this->postJson(route('audio.generate',$s),$data)->assertAccepted()->json();$this->postJson(route('audio.generate',$s),$data)->assertAccepted()->assertJsonPath('id',$first['id']);Queue::assertPushed(GenerateStoryAudio::class,1);Queue::assertPushed(GenerateStoryAudio::class,fn($job)=>$job->connection==='database' && $job->queue==='audio' && $job->afterCommit);$this->assertDatabaseCount('audio_generations',1);
 }
 public function test_worker_submits_once_polls_and_saves_audio():void {
  $s=$this->story();Storage::fake('local');config(['services.fal.key'=>'test']);$g=AudioGeneration::create(['story_id'=>$s->id,'request_key'=>(string)Str::uuid(),'name'=>'Door','prompt'=>'Door','seconds'=>5]);$base='https://queue.fal.run/fal-ai/stable-audio';
  Http::fake([$base=>Http::response(['request_id'=>'req-1']),$base.'/requests/req-1/status*'=>Http::sequence()->push(['status'=>'IN_QUEUE','queue_position'=>2])->push(['status'=>'IN_PROGRESS','logs'=>[['message'=>'Rendering']]])->push(['status'=>'COMPLETED']),$base.'/requests/req-1'=>Http::response(['audio_file'=>['url'=>'https://fal.media/sound.wav']]),'https://fal.media/sound.wav'=>Http::response($this->wav())]);
  $job=new GenerateStoryAudio($g->id);$service=new StoryAudio();$job->handle($service);$this->assertSame('queued',$g->fresh()->status);$this->assertSame('req-1',$g->fresh()->provider_request_id);
  $job->handle($service);$this->assertSame(2,$g->fresh()->queue_position);$this->assertNull($g->fresh()->started_at);
  $job->handle($service);$this->assertSame('running',$g->fresh()->status);$this->assertNotNull($g->fresh()->started_at);
  $job->handle($service);$this->assertSame('completed',$g->fresh()->status);$this->assertDatabaseCount('story_sounds',1);$job->handle($service);Http::assertSentCount(6);
 }
 public function test_uncertain_submission_is_never_posted_again():void {
  $s=$this->story();$g=AudioGeneration::create(['story_id'=>$s->id,'request_key'=>(string)Str::uuid(),'name'=>'Door','prompt'=>'Door','seconds'=>5,'status'=>'submitting','submitted_at'=>now()]);Http::fake();(new GenerateStoryAudio($g->id))->handle(new StoryAudio());$this->assertSame('failed',$g->fresh()->status);Http::assertNothingSent();
 }
 public function test_scene_sound_must_belong_to_story():void {
  $s=$this->story();$sound=StorySound::create(['story_id'=>$s->id,'name'=>'Door','provider'=>'fal','path'=>'test.wav','mime'=>'audio/wav']);
  $doc=['visuals'=>[['id'=>(string)Str::uuid(),'name'=>'Hall','kind'=>'scene','asset_id'=>null,'prompt'=>'','hotspots'=>[],'sound_id'=>$sound->id,'sound_volume'=>0.5,'sound_loop'=>true]]];
  $this->assertSame($sound->id,app(SceneDocument::class)->validate($s,$doc)['visuals'][0]['sound_id']);$other=$this->story();$this->expectException(\Illuminate\Validation\ValidationException::class);app(SceneDocument::class)->validate($other,$doc);
 }
 public function test_audio_download_rejects_non_provider_urls():void {
  Http::fake();try{(new StoryAudio())->download('http://127.0.0.1/private','fal');$this->fail('Expected URL rejection');}catch(\RuntimeException $e){$this->assertStringContainsString('unsupported',$e->getMessage());}Http::assertNothingSent();
 }
}
