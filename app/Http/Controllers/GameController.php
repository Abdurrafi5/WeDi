<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GameScore;

class GameController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $bestScores = [];
        foreach (GameScore::VALID_GAMES as $gameId) {
            $best = $user->gameScores()
                ->where('game_id', $gameId)
                ->orderByDesc('score')
                ->first();
            $bestScores[$gameId] = $best ? $best->score : null;
        }

        return view('games.index', compact('bestScores'));
    }

    public function saveScore(Request $request)
    {
        $validated = $request->validate([
            'game_id' => ['required', 'string', 'in:' . implode(',', GameScore::VALID_GAMES)],
            'score' => ['required', 'integer', 'min:0', 'max:99999'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $user = $request->user();

        $user->gameScores()->create([
            'game_id' => $validated['game_id'],
            'score' => $validated['score'],
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'played_at' => now(),
        ]);

        $bestScore = $user->gameScores()
            ->where('game_id', $validated['game_id'])
            ->max('score');

        return response()->json([
            'saved' => true,
            'best_score' => $bestScore,
        ]);
    }
}
