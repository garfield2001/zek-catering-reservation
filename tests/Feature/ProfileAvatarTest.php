<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->user = User::factory()->create(['name' => 'Admin Tester']);
        $this->user->roles()->attach(Role::where('code', 'admin')->sole());
        $this->actingAs($this->user)->withSession(['access_version' => $this->user->access_version]);
    }

    public function test_user_can_upload_replace_and_remove_their_own_profile_photo(): void
    {
        $this->put(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('avatar.png', 900, 600),
        ])->assertSessionHasNoErrors();

        $this->user->refresh();
        $first = $this->user->avatar_path;
        $this->assertMatchesRegularExpression('/^avatars\/[a-z0-9]+\.jpg$/', (string) $first);
        Storage::disk('public')->assertExists([$this->user->avatar_path, $this->user->avatar_thumbnail_path]);
        $this->get($this->user->avatar_url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->put(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('replacement.webp', 400, 400),
        ])->assertSessionHasNoErrors();
        $this->user->refresh();
        $this->assertNotSame($first, $this->user->avatar_path);
        Storage::disk('public')->assertMissing($first);

        $this->put(route('profile.avatar.update'), ['remove_avatar' => true])->assertSessionHasNoErrors();
        $this->user->refresh();
        $this->assertNull($this->user->avatar_url);
        $this->assertSame(3, AuditEvent::where('action', 'access.profile_avatar_updated')->count());
    }

    public function test_avatar_update_requires_an_actual_change_and_no_other_user_can_read_it(): void
    {
        $this->put(route('profile.avatar.update'), ['remove_avatar' => false])
            ->assertSessionHasErrors('avatar');
        $this->put(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->createWithContent('not-an-image.jpg', 'not an image'),
        ])->assertSessionHasErrors('avatar');

        $this->put(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ])->assertSessionHasNoErrors();
        $url = $this->user->fresh()->avatar_url;
        $other = User::factory()->create();
        $this->actingAs($other)->withSession(['access_version' => $other->access_version]);
        $this->get($url)->assertNotFound();
    }
}
