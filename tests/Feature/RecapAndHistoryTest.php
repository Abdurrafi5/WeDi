<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecapAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_recap(): void
    {
        $user = User::factory()->create();
        $user->getOrCreateActiveDiary();

        $response = $this->actingAs($user)->post('/recap', [
            'achievement' => 'Menulis setiap hari',
            'reflection' => 'Cukup produktif',
        ]);

        $response->assertRedirect(route('recap.index'));
        $this->assertDatabaseHas('recaps', ['user_id' => $user->id, 'achievement' => 'Menulis setiap hari']);
    }

    public function test_saving_recap_twice_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $user->getOrCreateActiveDiary();

        $this->actingAs($user)->post('/recap', ['achievement' => 'Versi 1']);
        $this->actingAs($user)->post('/recap', ['achievement' => 'Versi 2']);

        $this->assertDatabaseCount('recaps', 1);
        $this->assertDatabaseHas('recaps', ['user_id' => $user->id, 'achievement' => 'Versi 2']);
    }

    public function test_user_cannot_view_another_users_recap_via_history(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $diary = $owner->getOrCreateActiveDiary();

        $this->actingAs($intruder)->get("/history/{$diary->id}/recap")->assertNotFound();
    }

    public function test_history_only_lists_own_weeks(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userA->getOrCreateActiveDiary();
        $userB->getOrCreateActiveDiary();

        $response = $this->actingAs($userA)->get('/history');

        $response->assertOk();
        $ids = $response->viewData('diaries')->pluck('user_id')->unique();
        $this->assertEquals([$userA->id], $ids->all());
    }
}
