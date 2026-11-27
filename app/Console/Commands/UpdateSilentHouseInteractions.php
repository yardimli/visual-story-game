<?php
namespace App\Console\Commands;

use App\Models\Story;
use App\Services\SceneDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Storage};

class UpdateSilentHouseInteractions extends Command
{
    protected $signature = 'story:update-silent-house-interactions {--apply : Save the reviewed changes}';
    protected $description = 'Back up and update Silent House interaction settings and puzzle progression';

    public function handle(): int
    {
        return DB::transaction(function () {
            $story = Story::whereKey(2)->lockForUpdate()->firstOrFail();
            if ($story->title !== 'The Silent House') { $this->error('Story 2 is not The Silent House.'); return 1; }
            $pages = $story->pages()->lockForUpdate()->get();
            $backup = ['story' => $story->only(['id','game_flags','inventory_document','inventory_revision','flags_revision']),
                'pages' => $pages->map->only(['id','visual_document','visual_revision'])->all()];
            $flags = $story->game_flags ?? [];
            if (!in_array('letter_read', array_column($flags, 'key'), true)) $flags[] = ['key'=>'letter_read','label'=>'Letter read','initial'=>false];
            $story->game_flags = $flags;
            $inventory = $story->inventory_document;
            foreach ($inventory['visuals'] as &$item) foreach ($item['actions'] ?? [] as $i => $action) {
                if (($action['target_id'] ?? '') === '58c1b363-da46-443d-be7d-0ec5b5683dba')
                    $item['actions'][$i]['flag_effects'] = [['key'=>'letter_read','value'=>true]];
            }
            unset($item);
            $story->inventory_document = $inventory;
            $count = 0;
            foreach ($pages as $page) {
                $doc = $page->visual_document;
                foreach ($doc['visuals'] as &$scene) foreach ($scene['hotspots'] as &$h) {
                    $count++;
                    $h['click_behavior'] ??= empty($h['description']) ? 'direct' : 'text';
                    $h['action_preset'] ??= match ($h['action']) {
                        'pickup_v2' => 'take', 'state_v2' => empty($h['auto_state']['enabled']) ? 'open_close' : 'open',
                        default => !empty($h['move_after_examine']) ? (empty($h['description']) ? 'back' : 'look') : 'continue',
                    };
                    if (in_array($h['id'], ['4b7958a8-e493-4516-bcf9-f5ffff82feb1','46b758de-eed1-4f1b-b8a2-98f988b8f3d5'])) {
                        $h['required_items'] = []; $h['flag_conditions'] = [['key'=>'letter_read','value'=>true]]; $h['action_preset']='search';
                    }
                    if ($h['id']==='f26a8b2a-4f76-4795-b083-d69e318cd77b') {
                        $h['required_items']=[]; $h['flag_conditions']=[['key'=>'key_collected','value'=>true]];
                        $h['action_preset']='custom_single'; $h['action_label']='Try the door';
                    }
                    if ($h['id']==='f3601c46-3b2f-44ba-9b05-a04729633fd2') $h['flag_effects']=[['key'=>'key_collected','value'=>true]];
                    if ($h['id']==='373461bc-0109-4e4b-99da-9399b95354b5') {
                        foreach ($h['inventory_rules'] as &$rule) {
                            $rule['flag_effects']=array_values(array_filter($rule['flag_effects']??[],fn($f)=>$f['key']!=='key_collected'));
                            $rule['flag_effects'][]=['key'=>'key_collected','value'=>true];
                        }
                        unset($rule);
                    }
                    if ($h['id']==='5c345beb-a7dd-4321-bf36-1e99afa84120') $h['action_preset']='enter';
                    if (in_array($h['id'],['5c218de1-1705-4b45-a511-372c1fdd1e98','b996e6a7-72b0-48c5-82db-bf860c263799'])) {
                        $h['action_preset']='custom_single'; $h['action_label']='Remove';
                        $h['state_description']=$h['id']==='5c218de1-1705-4b45-a511-372c1fdd1e98'
                            ? 'The moon medallion sits flush in the circular groove. Its notch lines up with the brass rim.'
                            : 'The violet crystal stands securely in the angular groove, touching the thin metal contacts.';
                        $key=$h['id']==='5c218de1-1705-4b45-a511-372c1fdd1e98'?'coin_in_chest':'crystal_in_chest';
                        $h['flag_effects']=array_values(array_filter($h['flag_effects']??[],fn($f)=>$f['key']!==$key));
                        $h['flag_effects'][]=['key'=>$key,'value'=>false];
                    }
                    if ($h['id']==='4d78cfdc-0fb9-4f2c-8a50-334bf9751e08') {
                        $h['auto_state']['sticky']=true; $h['action_preset']='custom_single'; $h['action_label']='Open chest';
                    }
                    if ($h['label']==='RESET') {
                        $h['restart_game']=true; $h['action_preset']='custom_single'; $h['action_label']='Press RESET';
                    }
                }
                unset($h,$scene);
                $page->visual_document=app(SceneDocument::class)->validate($story,$doc,$page->id);
            }
            $story->inventory_document=app(SceneDocument::class)->validate($story,$inventory);
            if (!$this->option('apply')) { $this->info("Validated {$pages->count()} pages and $count hotspots. Run with --apply to save."); return 0; }
            $path='backups/silent-house-interactions-'.now()->format('Ymd-His').'.json';
            if (!Storage::disk('local')->put($path,json_encode($backup,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))) throw new \RuntimeException('Backup failed.');
            if ($story->isDirty('game_flags')) $story->flags_revision++;
            if ($story->isDirty('inventory_document')) $story->inventory_revision++;
            $story->save();
            foreach ($pages as $page) if ($page->isDirty('visual_document')) { $page->visual_revision++; $page->save(); }
            $this->info("Updated {$pages->count()} pages and $count hotspots. Backup: storage/app/$path");
            return 0;
        });
    }
}
