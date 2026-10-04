<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Support\PublicAvatar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicAvatarTest extends TestCase
{
    use RefreshDatabase;

    private const JPEG = "\xFF\xD8\xFF\xE0fake-jpeg";

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        config(['services.avatar_lookup.enabled' => true]);
    }

    public function test_first_service_with_a_picture_wins(): void
    {
        Http::fake([
            'www.gravatar.com/*' => Http::response('', 404),
            'seccdn.libravatar.org/*' => Http::response(self::JPEG, 200, ['Content-Type' => 'image/jpeg']),
            'unavatar.io/*' => Http::response(self::JPEG, 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $user = User::factory()->customer()->create(['email' => ' Ali@Example.com ', 'avatar_path' => null]);

        $this->assertTrue(PublicAvatar::fill($user));
        $path = $user->fresh()->avatar_path;
        $this->assertMatchesRegularExpression('#^avatars/\w{40}\.jpg$#', $path);
        Storage::disk('public')->assertExists($path);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), md5('ali@example.com')));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'unavatar.io')); // stopped at the first match
    }

    public function test_nothing_found_or_not_an_image_leaves_the_user_alone(): void
    {
        Http::fake([
            'www.gravatar.com/*' => Http::response('', 404),
            'seccdn.libravatar.org/*' => Http::response('<html>', 200, ['Content-Type' => 'text/html']),
            'unavatar.io/*' => Http::response('', 404),
        ]);
        $user = User::factory()->customer()->create(['avatar_path' => null]);

        $this->assertFalse(PublicAvatar::fill($user));
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_a_user_with_a_picture_is_not_searched(): void
    {
        Http::fake();
        $user = User::factory()->customer()->create(['avatar_path' => 'avatars/mine.png']);

        $this->assertFalse(PublicAvatar::fill($user));
        Http::assertNothingSent();
    }

    public function test_saving_users_and_profiles(): void
    {
        Http::fake(['www.gravatar.com/*' => Http::response(self::JPEG, 200, ['Content-Type' => 'image/jpeg'])]);
        $dev = User::factory()->developer()->create(['avatar_path' => null]);
        $project = Project::create(['code' => 'P1', 'name' => 'Project one']);
        $project->members()->attach($dev->id);

        // A developer adds a customer: the customer gets a picture right after saving.
        $this->actingAs($dev)->post('/customers', [
            'first_name' => 'Sara', 'last_name' => 'K', 'email' => 'sara@example.com', 'mobile' => '09121110000',
            'password' => 'password1', 'password_confirmation' => 'password1', 'is_active' => '1', 'locale' => 'fa', 'calendar' => 'jalali',
            'projects' => [$project->id],
        ])->assertSessionHasNoErrors();
        $customer = User::where('email', 'sara@example.com')->firstOrFail();
        $this->assertNotNull($customer->avatar_path);

        // "Remove photo" ticked: removed, and not searched again.
        $this->put("/customers/{$customer->id}", [
            'first_name' => 'Sara', 'last_name' => 'K', 'email' => 'sara@example.com', 'mobile' => '09121110000',
            'is_active' => '1', 'locale' => 'fa', 'calendar' => 'jalali', 'projects' => [$project->id], 'remove_avatar' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertNull($customer->fresh()->avatar_path);

        // Staff saving their own profile get one; a customer saving their profile does not.
        $this->put('/profile', ['locale' => 'fa', 'calendar' => 'jalali'])->assertSessionHasNoErrors();
        $this->assertNotNull($dev->fresh()->avatar_path);
        $this->actingAs($customer->fresh())->put('/profile', ['locale' => 'fa', 'calendar' => 'jalali'])->assertSessionHasNoErrors();
        $this->assertNull($customer->fresh()->avatar_path);
    }
}
