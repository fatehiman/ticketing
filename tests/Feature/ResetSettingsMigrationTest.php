<?php

namespace Tests\Feature;

use App\Models\GridPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_everyone_goes_back_to_the_default_folders_and_columns(): void
    {
        $user = User::factory()->developer()->create(['folder_settings' => ['order' => ['all'], 'hidden' => []]]);
        foreach (['tickets', 'tickets-status_done', 'tickets-custom_7', 'transactions'] as $key) {
            GridPreference::create(['user_id' => $user->id, 'grid_key' => $key, 'columns' => ['number']]);
        }

        (require database_path('migrations/2026_10_04_000300_reset_folder_and_column_settings.php'))->up();

        $this->assertNull($user->fresh()->folder_settings);
        $this->assertSame(['tickets-custom_7', 'transactions'], GridPreference::orderBy('grid_key')->pluck('grid_key')->all());
    }
}
