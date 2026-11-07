<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // Explicit opt-in seeder; never run automatically in production deployment.
        $user = User::firstOrNew(['email' => 'demo@demo.com']);
        $user->forceFill([
            'name' => 'Demo Creator',
            'password' => Hash::make('demo'),
            'email_verified_at' => now(),
        ])->save();
    }
}
