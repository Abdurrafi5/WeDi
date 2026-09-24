<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyDiaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_diary_is_created_automatically_on_dashboard_visit(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseCount('weekly_diaries', 0);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertDatabaseCount('weekly_diaries', 1);
        $this->assertDatabaseHas('weekly_diaries', ['user_id' => $user->id, 'status' => 'active']);
    }

    public function test_visiting_dashboard_repeatedly_does_not_duplicate_diary(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard');
        $this->actingAs($user)->get('/dashboard');
        $this->actingAs($user)->get('/dashboard');

        $this->assertDatabaseCount('weekly_diaries', 1);
    }

    public function test_week_boundary_is_monday_to_sunday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24')); // a Thursday
        $user = User::factory()->create();

        $diary = $user->getOrCreateActiveDiary();

        $this->assertSame('2026-09-21', $diary->week_start->toDateString()); // Monday
        $this->assertSame('2026-09-27', $diary->week_end->toDateString());   // Sunday

        Carbon::setTestNow();
    }

    public function test_diaries_are_not_shared_between_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $diaryA = $userA->getOrCreateActiveDiary();
        $diaryB = $userB->getOrCreateActiveDiary();

        $this->assertNotEquals($diaryA->id, $diaryB->id);
        $this->assertSame($userA->id, $diaryA->user_id);
        $this->assertSame($userB->id, $diaryB->user_id);
    }
}
