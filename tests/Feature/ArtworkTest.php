<?php

namespace Tests\Feature;

use App\Models\Artwork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtworkTest extends TestCase
{
    use RefreshDatabase;

    private function png(): string
    {
        $im = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($im);
        $data = ob_get_clean();

        return 'data:image/png;base64,'.base64_encode($data);
    }

    public function test_user_can_save_open_update_and_delete_artwork(): void
    {
        Storage::fake(Artwork::DISK);
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/draw/artworks', ['image' => $this->png(), 'title' => 'Matahari'])->assertOk()->assertJson(['saved' => true]);
        $art = Artwork::findOrFail($res->json('id'));
        Storage::disk(Artwork::DISK)->assertExists($art->path);
        $this->assertSame([20, 10], [$art->width, $art->height]);

        $this->actingAs($user)->get('/draw/gallery')->assertOk()->assertSee('Matahari');
        $this->actingAs($user)->get("/draw?artwork={$art->id}")->assertOk()->assertSee('data-artwork-image', false);
        $this->actingAs($user)->get("/draw/artworks/{$art->id}/image")->assertOk();
        $this->actingAs($user)->putJson("/draw/artworks/{$art->id}", ['image' => $this->png(), 'title' => 'Matahari 2'])->assertOk();
        $this->assertSame('Matahari 2', $art->fresh()->title);

        $this->actingAs($user)->delete("/draw/artworks/{$art->id}")->assertRedirect(route('draw.gallery'));
        Storage::disk(Artwork::DISK)->assertMissing($art->path);
        $this->assertDatabaseMissing('artworks', ['id' => $art->id]);
    }

    public function test_artwork_is_private_to_owner(): void
    {
        Storage::fake(Artwork::DISK);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $id = $this->actingAs($owner)->postJson('/draw/artworks', ['image' => $this->png()])->json('id');

        $this->actingAs($other)->get("/draw/artworks/{$id}/image")->assertNotFound();
        $this->actingAs($other)->get("/draw?artwork={$id}")->assertNotFound();
        $this->actingAs($other)->delete("/draw/artworks/{$id}")->assertNotFound();
        $this->actingAs($other)->putJson("/draw/artworks/{$id}", ['image' => $this->png()])->assertNotFound();
        auth()->logout();
        $this->get("/draw/artworks/{$id}/image")->assertRedirect('/login');
    }

    public function test_invalid_image_payloads_are_rejected(): void
    {
        Storage::fake(Artwork::DISK);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/draw/artworks', ['image' => 'data:image/png;base64,bm90IGFuIGltYWdl'])->assertStatus(422);
        $this->actingAs($user)->postJson('/draw/artworks', ['image' => 'data:text/html;base64,PGI+'])->assertStatus(422);
        $this->actingAs($user)->postJson('/draw/artworks', [])->assertStatus(422);
        $this->assertDatabaseCount('artworks', 0);
    }

    public function test_drawing_studio_and_bubble_pop_pages_load(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/draw')->assertOk()->assertSee('Hand Gesture Drawing');
        $this->actingAs($user)->get('/games/bubble-pop')->assertOk()->assertSee('Bubble Pop');
        $this->actingAs($user)->get('/games')->assertOk()->assertSee('Bubble Pop');
        // komponen Hand Studio ikut dirender di kedua halaman
        $this->actingAs($user)->get('/games/bubble-pop')->assertSee('data-hs-toggle', false)->assertSee('data-hs-fx', false)->assertSee('data-bp-start', false);
        $this->actingAs($user)->get('/draw')->assertSee('data-hs-settings', false)->assertSee('data-dh-palette', false)->assertSee('data-draw-stroke', false);
    }

    public function test_hand_filter_studio_page_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/filter')->assertOk()->assertSee('Hand Filter Studio')->assertSee('data-hf-canvas', false)->assertSee('data-hf-filter="glitch"', false);
        $this->actingAs($user)->get('/games')->assertOk()->assertSee('Hand Filter Studio');
        auth()->logout();
        $this->get('/filter')->assertRedirect('/login');
    }

    public function test_bubble_pop_score_is_saved_in_game_scores(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/games/score', ['game_id' => 'bubble-pop', 'score' => 77, 'duration_seconds' => 60])
            ->assertOk()->assertJson(['saved' => true, 'best_score' => 77]);
        $this->assertDatabaseHas('game_scores', ['user_id' => $user->id, 'game_id' => 'bubble-pop', 'score' => 77]);
    }
}
