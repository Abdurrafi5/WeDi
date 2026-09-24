<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

class RecapController extends Controller
{
    private function viewData($user, $diary): array
    {
        $recap = $user->recaps()->where('weekly_diary_id', $diary->id)->first();
        $journals = $user->journals()->where('weekly_diary_id', $diary->id)->orderBy('entry_date')->get();
        $targets = $user->targets()->where('weekly_diary_id', $diary->id)->orderByDesc('is_completed')->get();
        $journalCount = $journals->count(); $totalTargets = $targets->count(); $completedCount = $targets->where('is_completed', true)->count(); $progress = $totalTargets ? (int) round(($completedCount / $totalTargets) * 100) : 0;
        $moodCounts = $journals->groupBy(fn ($journal) => $journal->mood_label ?: __('No mood'))->map->count();
        $weekDays = collect(range(0, 6))->map(function ($offset) use ($diary, $journals) { $date = $diary->week_start->copy()->addDays($offset); return ['date' => $date, 'journal' => $journals->first(fn ($journal) => $journal->entry_date->isSameDay($date))]; });
        return compact('diary', 'recap', 'journals', 'targets', 'journalCount', 'totalTargets', 'completedCount', 'progress', 'moodCounts', 'weekDays');
    }

    public function index(Request $request)
    {
        return view('recap', $this->viewData($request->user(), $request->user()->getOrCreateActiveDiary()));
    }

    public function store(Request $request)
    {
        $diary = $request->user()->getOrCreateActiveDiary();
        $validated = $request->validate(['achievement' => ['nullable', 'string', 'max:2000'], 'reflection' => ['nullable', 'string', 'max:2000'], 'lesson_learned' => ['nullable', 'string', 'max:2000'], 'next_week_plan' => ['nullable', 'string', 'max:2000']]);
        $request->user()->recaps()->updateOrCreate(['weekly_diary_id' => $diary->id], $validated);
        return redirect()->route('recap.index')->with('success', __('Weekly recap saved.'));
    }

    public function show(Request $request, $diaryId)
    {
        $user = $request->user(); $diary = $user->weeklyDiaries()->findOrFail($diaryId); $data = $this->viewData($user, $diary); $isReadOnly = true; return view('history-recap', array_merge($data, compact('isReadOnly')));
    }
}
