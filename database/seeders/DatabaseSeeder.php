<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\PrefixedIdService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $prefixedIds = app(PrefixedIdService::class);

        $admin = User::firstOrNew(['email' => 'vivethan@soss.com']);
        if (! $admin->exists) {
            $admin->user_id = $prefixedIds->next(User::PREFIXED_PRIMARY_KEY_COUNTER);
        }
        $admin->fill([
            'name' => 'Vivi Admin',
            'password' => Hash::make('vivi1234'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->save();

        $staff = User::firstOrNew(['email' => 'staff@soss.com']);
        if (! $staff->exists) {
            $staff->user_id = $prefixedIds->next(User::PREFIXED_PRIMARY_KEY_COUNTER);
        }
        $staff->fill([
            'name' => 'SOSS Staff',
            'password' => Hash::make('staff1234'),
            'role' => 'staff',
            'status' => 'active',
        ]);
        $staff->save();

        $testUser = User::firstOrNew(['email' => 'test@example.com']);
        if (! $testUser->exists) {
            $testUser->user_id = $prefixedIds->next(User::PREFIXED_PRIMARY_KEY_COUNTER);
        }
        $testUser->fill([
            'name' => 'Test User',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $testUser->save();
    }
}
