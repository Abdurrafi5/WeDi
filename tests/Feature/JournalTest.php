<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_journal_entry(): void
    {
        $user = User::factory()->create();
        $diary = $user->getOrCreateActiveDiary();

        $response = $this->actingAs($user)->post('/journal', [
            'entry_date' => $diary->week_start->toDateString(),
            'title' => 'Hari yang baik',
            'content' => 'Hari ini menyenangkan.',
            'mood' => 'senang',
        ]);

        $response->assertRedirect(route('journal.index'));
        $this->assertDatabaseHas('journals', [
            'user_id' => $user->id,
            'weekly_diary_id' => $diary->id,
            'title' => 'Hari yang baik',
        ]);
    }

    public function test_index_only_shows_own_journals(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $diaryA = $userA->getOrCreateActiveDiary();
        $diaryB = $userB->getOrCreateActiveDiary();

        $userA->journals()->create([
            'weekly_diary_id' => $diaryA->id,
            'entry_date' => $diaryA->week_start,
            'content' => 'Punya A',
        ]);
        $userB->journals()->create([
            'weekly_diary_id' => $diaryB->id,
            'entry_date' => $diaryB->week_start,
            'content' => 'Punya B',
        ]);

        $response = $this->actingAs($userA)->get('/journal');

        $response->assertSee('Punya A');
        $response->assertDontSee('Punya B');
    }

    public function test_user_can_update_own_journal(): void
    {
        $user = User::factory()->create();
        $diary = $user->getOrCreateActiveDiary();
        $journal = $user->journals()->create([
            'weekly_diary_id' => $diary->id,
            'entry_date' => $diary->week_start,
            'content' => 'Awal',
        ]);

        $response = $this->actingAs($user)->put("/journal/{$journal->id}", [
            'entry_date' => $diary->week_start->toDateString(),
            'content' => 'Sudah diperbarui',
        ]);

        $response->assertRedirect(route('journal.index'));
        $this->assertDatabaseHas('journals', ['id' => $journal->id, 'content' => 'Sudah diperbarui']);
    }

    public function test_user_can_delete_own_journal(): void
    {
        $user = User::factory()->create();
        $diary = $user->getOrCreateActiveDiary();
        $journal = $user->journals()->create([
            'weekly_diary_id' => $diary->id,
            'entry_date' => $diary->week_start,
            'content' => 'Hapus saya',
        ]);

        $this->actingAs($user)->delete("/journal/{$journal->id}")->assertRedirect(route('journal.index'));

        $this->assertDatabaseMissing('journals', ['id' => $journal->id]);
    }

    public function test_user_cannot_edit_or_delete_another_users_journal(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $diary = $owner->getOrCreateActiveDiary();
        $journal = $owner->journals()->create([
            'weekly_diary_id' => $diary->id,
            'entry_date' => $diary->week_start,
            'content' => 'Rahasia',
        ]);

        $this->actingAs($intruder)->get("/journal/{$journal->id}/edit")->assertNotFound();
        $this->actingAs($intruder)->put("/journal/{$journal->id}", [
            'entry_date' => $diary->week_start->toDateString(),
            'content' => 'Diubah paksa',
        ])->assertNotFound();
        $this->actingAs($intruder)->delete("/journal/{$journal->id}")->assertNotFound();

        $this->assertDatabaseHas('journals', ['id' => $journal->id, 'content' => 'Rahasia']);
    }

    public function test_empty_content_is_rejected(): void
    {
        $user = User::factory()->create();
        $diary = $user->getOrCreateActiveDiary();

        $response = $this->actingAs($user)->post('/journal', [
            'entry_date' => $diary->week_start->toDateString(),
            'content' => '',
        ]);

        $response->assertSessionHasErrors('content');
    }

    public function test_date_outside_active_week_is_rejected(): void
    {
        $user = User::factory()->create();
        $diary = $user->getOrCreateActiveDiary();

        $response = $this->actingAs($user)->post('/journal', [
            'entry_date' => $diary->week_start->copy()->subWeek()->toDateString(),
            'content' => 'Di luar minggu aktif',
        ]);

        $response->assertSessionHasErrors('entry_date');
        $this->assertDatabaseMissing('journals', ['content' => 'Di luar minggu aktif']);
    }
}
