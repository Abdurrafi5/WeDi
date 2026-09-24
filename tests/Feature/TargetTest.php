<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_target(): void
    {
        $user = User::factory()->create();
        $user->getOrCreateActiveDiary();

        $response = $this->actingAs($user)->post('/targets', [
            'title' => 'Belajar Laravel',
            'priority' => 'high',
        ]);

        $response->assertRedirect(route('targets.index'));
        $this->assertDatabaseHas('targets', ['user_id' => $user->id, 'title' => 'Belajar Laravel']);
    }

    public function test_toggle_marks_target_complete_and_sets_completed_at(): void
    {
        $user = User::factory()->create();
        $diary = $user->getOrCreateActiveDiary();
        $target = $user->targets()->create([
            'weekly_diary_id' => $diary->id,
            'title' => 'Baca buku',
            'priority' => 'medium',
        ]);

        $this->actingAs($user)->patch("/targets/{$target->id}/toggle");

        $target->refresh();
        $this->assertTrue($target->is_completed);
        $this->assertNotNull($target->completed_at);
    }

    public function test_toggle_again_clears_completed_at(): void
    {
        $user = User::factory()->create();
        $diary = $user->getOrCreateActiveDiary();
        $target = $user->targets()->create([
            'weekly_diary_id' => $diary->id,
            'title' => 'Baca buku',
            'priority' => 'medium',
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $this->actingAs($user)->patch("/targets/{$target->id}/toggle");

        $target->refresh();
        $this->assertFalse($target->is_completed);
        $this->assertNull($target->completed_at);
    }

    public function test_user_can_delete_own_target(): void
    {
        $user = User::factory()->create();
        $diary = $user->getOrCreateActiveDiary();
        $target = $user->targets()->create([
            'weekly_diary_id' => $diary->id,
            'title' => 'Hapus target',
            'priority' => 'low',
        ]);

        $this->actingAs($user)->delete("/targets/{$target->id}")->assertRedirect(route('targets.index'));
        $this->assertDatabaseMissing('targets', ['id' => $target->id]);
    }

    public function test_user_cannot_modify_another_users_target(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $diary = $owner->getOrCreateActiveDiary();
        $target = $owner->targets()->create([
            'weekly_diary_id' => $diary->id,
            'title' => 'Punya owner',
            'priority' => 'low',
        ]);

        $this->actingAs($intruder)->patch("/targets/{$target->id}/toggle")->assertNotFound();
        $this->actingAs($intruder)->delete("/targets/{$target->id}")->assertNotFound();

        $target->refresh();
        $this->assertFalse($target->is_completed);
        $this->assertDatabaseHas('targets', ['id' => $target->id]);
    }

    public function test_progress_is_calculated_from_database(): void
    {
        $user = User::factory()->create();
        $diary = $user->getOrCreateActiveDiary();
        $user->targets()->create(['weekly_diary_id' => $diary->id, 'title' => 'A', 'priority' => 'low', 'is_completed' => true, 'completed_at' => now()]);
        $user->targets()->create(['weekly_diary_id' => $diary->id, 'title' => 'B', 'priority' => 'low']);

        $response = $this->actingAs($user)->get('/targets');

        $response->assertOk();
        $response->assertSee('50%');
    }
}
