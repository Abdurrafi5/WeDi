<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        $this->actingAs($user)->post('/settings/profile', ['name' => 'New Name'])->assertRedirect(route('settings'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_user_can_upload_a_profile_picture(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/settings/profile', ['name' => $user->name, 'avatar' => UploadedFile::fake()->image('avatar.png', 120, 120)])->assertRedirect(route('settings'));
        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        $this->assertFileExists(public_path($user->avatar_path));
        @unlink(public_path($user->avatar_path));
    }

    public function test_locale_preference_is_stored_in_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/settings/locale', ['locale' => 'en'])->assertRedirect(route('settings'));
        $this->assertSame('en', session('locale'));
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('oldpassword')]);
        $this->actingAs($user)->post('/settings/password', ['current_password' => 'wrong', 'password' => 'newpassword123', 'password_confirmation' => 'newpassword123'])->assertSessionHasErrors('current_password');
    }

    public function test_password_can_be_changed_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('oldpassword')]);
        $this->actingAs($user)->post('/settings/password', ['current_password' => 'oldpassword', 'password' => 'newpassword123', 'password_confirmation' => 'newpassword123'])->assertRedirect(route('settings'));
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }
}
