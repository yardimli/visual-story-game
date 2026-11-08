<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class GrantAiCredit extends Command {
 protected $signature='ai:grant-credit {email : User email} {amount : USD, e.g. 5 or 5.25} {--description=Admin credit : Reason shown to the user} {--reference= : Optional unique grant reference to prevent duplicate grants}';
 protected $description='Grant AI credit to a user (server administrator only)';
 public function handle(): int {
  $input=(string)$this->argument('amount');$description=trim((string)$this->option('description'));$reference=$this->option('reference');
  if(!preg_match('/^(\d{1,6})(?:\.(\d{1,6}))?$/',$input,$m)||!$description||mb_strlen($description)>255||($reference && (strlen($reference)>200||$reference==='signup'))){$this->error('Use a positive USD amount with at most six decimal places and a description up to 255 characters.');return self::FAILURE;}
  $amount=(int)$m[1]*1000000+(int)str_pad($m[2]??'',6,'0');if($amount<=0){$this->error('Credit must be greater than zero.');return self::FAILURE;}
  $user=User::where('email',$this->argument('email'))->first();if(!$user){$this->error('User not found.');return self::FAILURE;}
  $created=DB::transaction(function()use($user,$amount,$description,$reference){$locked=User::whereKey($user->id)->lockForUpdate()->firstOrFail();if($reference && $locked->tokenCredits()->where('grant_key',$reference)->exists())return false;$locked->tokenCredits()->create(['amount'=>$amount,'source'=>'admin','description'=>$description,'grant_key'=>$reference?:null]);return true;});
  if(!$created){$this->warn('This grant reference already exists. No credit added.');return self::FAILURE;}
  $this->info('Added $'.number_format($amount/1000000,6).' to '.$user->email.'. Available: $'.number_format($user->fresh()->availableCredit()/1000000,6));return self::SUCCESS;
 }
}
