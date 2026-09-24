<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_games_hub(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/games')->assertOk();
    }

    public function test_guest_cannot_open_games_hub(): void
    {
        $this->get('/games')->assertRedirect('/login');
    }

    public function test_score_is_saved_with_authenticated_user_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/games/score', [
            'game_id' => 'memory-match',
            'score' => 480,
            'duration_seconds' => 45,
        ]);

        $response->assertOk()->assertJson(['saved' => true, 'best_score' => 480]);
        $this->assertDatabaseHas('game_scores', ['user_id' => $user->id, 'game_id' => 'memory-match', 'score' => 480]);
    }

    public function test_invalid_game_id_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/games/score', [
            'game_id' => 'not-a-real-game',
            'score' => 100,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('game_scores', 0);
    }

    public function test_invalid_score_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/games/score', [
            'game_id' => 'zen-tap',
            'score' => -5,
        ]);

        $response->assertStatus(422);
    }

    public function test_best_score_is_separate_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $userA->gameScores()->create(['game_id' => 'zen-tap', 'score' => 10, 'played_at' => now()]);
        $userB->gameScores()->create(['game_id' => 'zen-tap', 'score' => 999, 'played_at' => now()]);

        $response = $this->actingAs($userA)->postJson('/games/score', [
            'game_id' => 'zen-tap',
            'score' => 20,
        ]);

        $response->assertJson(['best_score' => 20]);
    }
}
