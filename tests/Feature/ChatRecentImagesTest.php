<?php
namespace Tests\Feature;

use App\Models\{Story,User};
use App\Services\ChatContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatRecentImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_images_are_ten_updated_images_from_this_story_only(): void
    {
        $user=User::factory()->create();
        $story=Story::create(['user_id'=>$user->id,'title'=>'Story','premise'=>'Test']);
        $other=Story::create(['user_id'=>$user->id,'title'=>'Other','premise'=>'Test']);
        $images=[];
        for($i=0;$i<12;$i++) $images[]=$story->assets()->create([
            'name'=>'Image '.$i,'path'=>'image'.$i.'.png','mime'=>'image/png','bytes'=>1,'role'=>'scene',
            'created_at'=>now()->subDays(2),'updated_at'=>now()->subMinutes(20-$i),
        ]);
        // Editing an older image promotes it, and ties use the newer ID.
        $images[0]->update(['name'=>'Renamed image','updated_at'=>now()]);
        $images[11]->update(['archived_at'=>now()]);
        $other->assets()->create(['name'=>'Other image','path'=>'other.png','mime'=>'image/png','bytes'=>1,'role'=>'scene']);
        $story->assets()->create(['name'=>'Audio','path'=>'audio.mp3','mime'=>'audio/mpeg','bytes'=>1,'role'=>'reference']);
        $ids=app(ChatContext::class)->recentImageIds($story);
        $this->assertCount(10,$ids);
        $this->assertSame($images[0]->id,$ids[0]);
        $this->assertNotContains($images[1]->id,$ids);
        $this->assertNotContains($images[11]->id,$ids);
        $this->assertEqualsCanonicalizing(array_map(fn($i)=>$images[$i]->id,[0,2,3,4,5,6,7,8,9,10]),$ids);
    }
}
