<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VideoToken extends Command
{
    protected $signature = 'videos:token {role : worker or relay} {user : Owner email} {name : Unique credential name} {--revoke}';

    protected $description = 'Issue or revoke a scoped video API credential on the hub';

    public function handle(): int
    {
        if (config('video.mode') !== 'hub' || ! in_array($this->argument('role'), ['worker', 'relay'])) {
            $this->error('Run on the hub with role worker or relay.');

            return self::FAILURE;
        }
        $user = User::where('email', $this->argument('user'))->firstOrFail();
        $query = DB::table('video_access_tokens')->where('user_id', $user->id)
            ->where('role', $this->argument('role'))->where('name', $this->argument('name'));
        if ($this->option('revoke')) {
            // Preserve ownership of imported jobs; replace the hash so the credential no longer works.
            $query->update(['token_hash' => hash('sha256', Str::random(80)), 'updated_at' => now()]);
            $this->info('Credential revoked.');

            return self::SUCCESS;
        }
        $token = Str::random(64);
        if ($query->exists()) {
            $query->update(['token_hash' => hash('sha256', $token), 'updated_at' => now()]);
        } else {
            DB::table('video_access_tokens')->insert(['user_id' => $user->id, 'role' => $this->argument('role'),
                'name' => $this->argument('name'), 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->line($token);
        $this->info('Save this credential securely. Reissuing the same name rotates it without losing its jobs.');

        return self::SUCCESS;
    }
}
