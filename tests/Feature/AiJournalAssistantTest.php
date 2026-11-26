<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiJournalAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function fake(string $content): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::fake(['api.openai.com/*' => Http::response([
            'model' => 'gpt-4o-mini',
            'choices' => [['message' => ['content' => $content]]],
            'usage' => ['total_tokens' => 20],
        ])]);
    }

    public function test_journal_assistant_returns_preview_text(): void
    {
        $this->fake('1. Hari ini aku merasa...');
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/ai/journal-assist', ['action' => 'starter', 'mood' => 'lelah'])
            ->assertOk()->assertJson(['result' => '1. Hari ini aku merasa...']);
        $this->assertDatabaseHas('ai_usage_logs', ['user_id' => $user->id, 'feature' => 'journal_starter']);
    }

    public function test_actions_that_need_text_reject_empty_content(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/ai/journal-assist', ['action' => 'expand', 'content' => 'pendek'])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_invalid_action_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/ai/journal-assist', ['action' => 'hack'])->assertStatus(422);
    }

    public function test_weekly_reflection_requires_consent_and_own_journals(): void
    {
        $this->fake(json_encode(['summary' => 'Ringkasan', 'highlights' => ['- a', '- b'], 'mood_overview' => 'Mood', 'personal_growth' => 'Tumbuh', 'reflection_questions' => '1. ?', 'next_week_suggestions' => '- c']));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $diary = $user->getOrCreateActiveDiary();
        $journal = $user->journals()->create(['weekly_diary_id' => $diary->id, 'entry_date' => now()->toDateString(), 'content' => 'Hari yang panjang tapi menyenangkan.']);
        $otherDiary = $other->getOrCreateActiveDiary();
        $foreign = $other->journals()->create(['weekly_diary_id' => $otherDiary->id, 'entry_date' => now()->toDateString(), 'content' => 'Rahasia orang lain.']);

        // tanpa persetujuan
        $this->actingAs($user)->postJson('/recap/ai/generate', ['journal_ids' => [$journal->id]])->assertStatus(422);
        // jurnal milik orang lain
        $this->actingAs($user)->postJson('/recap/ai/generate', ['journal_ids' => [$foreign->id], 'consent' => true])->assertStatus(422);
        Http::assertNothingSent();

        $res = $this->actingAs($user)->postJson('/recap/ai/generate', ['journal_ids' => [$journal->id], 'consent' => true])->assertOk();
        $this->assertSame("- a\n- b", $res->json('sections.highlights'));
        $this->assertDatabaseHas('weekly_reflection_generations', ['user_id' => $user->id, 'weekly_diary_id' => $diary->id]);

        // isi jurnal orang lain tidak pernah dikirim
        Http::assertSent(fn ($r) => ! str_contains(json_encode($r->data()), 'Rahasia orang lain'));
    }

    public function test_reflection_can_be_edited_and_saved_to_recap(): void
    {
        $user = User::factory()->create();
        $diary = $user->getOrCreateActiveDiary();
        $generation = $user->reflectionGenerations()->create(['weekly_diary_id' => $diary->id, 'journal_ids' => [1], 'result' => ['summary' => 'x']]);

        $this->actingAs($user)->post("/recap/ai/{$generation->id}/save", [
            'summary' => 'Minggu yang baik', 'highlights' => '- Selesai proyek', 'mood_overview' => 'Stabil',
            'personal_growth' => 'Lebih sabar', 'reflection_questions' => '1. Apa yang membuatmu bangga?', 'next_week_suggestions' => '- Istirahat cukup',
        ])->assertRedirect(route('recap.index'));

        $recap = $user->recaps()->where('weekly_diary_id', $diary->id)->first();
        $this->assertSame('- Selesai proyek', $recap->achievement);
        $this->assertSame('Lebih sabar', $recap->lesson_learned);
        $this->assertSame('- Istirahat cukup', $recap->next_week_plan);
        $this->assertStringContainsString('Minggu yang baik', $recap->reflection);
        $this->assertNotNull($generation->fresh()->saved_at);
    }

    public function test_pages_render_with_ai_panels(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/journal/create')->assertOk()->assertSee('data-ai-journal', false);
        $this->actingAs($user)->get('/recap')->assertOk()->assertSee('Weekly Reflection AI');
        $this->actingAs($user)->get('/ai')->assertOk();
    }
}
