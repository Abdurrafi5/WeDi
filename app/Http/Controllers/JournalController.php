<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use Carbon\Carbon;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    private function weekDays($diary, $journals): array
    {
        return collect(range(0, 6))->map(function ($i) use ($diary, $journals) { $date = $diary->week_start->copy()->addDays($i); return ['date' => $date, 'entry' => $journals->first(fn ($journal) => $journal->entry_date->isSameDay($date)), 'is_today' => $date->isToday()]; })->all();
    }

    private function allowedDate($value, $diary): ?Carbon
    {
        try { $date = Carbon::parse($value)->startOfDay(); } catch (\Throwable) { return null; }
        return $date->betweenIncluded($diary->week_start->copy()->startOfDay(), $diary->week_end->copy()->endOfDay()) ? $date : null;
    }

    public function index(Request $request)
    {
        $diary = $request->user()->getOrCreateActiveDiary(); $journals = $request->user()->journals()->where('weekly_diary_id', $diary->id)->orderBy('entry_date')->get();
        return view('journals.index', compact('diary', 'journals') + ['weekDays' => $this->weekDays($diary, $journals)]);
    }

    public function create(Request $request)
    {
        $diary = $request->user()->getOrCreateActiveDiary(); $selectedDate = $this->allowedDate($request->query('day', now()->toDateString()), $diary) ?: ($this->allowedDate(now(), $diary) ?: $diary->week_start);
        return view('journals.create', compact('diary', 'selectedDate') + ['moods' => Journal::MOODS]);
    }

    public function store(Request $request)
    {
        $diary = $request->user()->getOrCreateActiveDiary(); $validated = $request->validate(['entry_date' => ['required', 'date'], 'title' => ['nullable', 'string', 'max:160'], 'content' => ['required', 'string', 'max:10000'], 'mood' => ['nullable', 'string', 'in:' . implode(',', array_keys(Journal::MOODS))]]); $entryDate = $this->allowedDate($validated['entry_date'], $diary);
        if (!$entryDate) return back()->withErrors(['entry_date' => __('The selected day must be inside the active week.')])->withInput();
        $request->user()->journals()->updateOrCreate(['weekly_diary_id' => $diary->id, 'entry_date' => $entryDate->toDateString()], ['title' => $validated['title'] ?? null, 'content' => $validated['content'], 'mood' => $validated['mood'] ?? null]);
        return redirect()->route('journal.index')->with('success', __('Journal saved successfully.'));
    }

    public function edit(Request $request, $id)
    {
        $journal = $request->user()->journals()->with('weeklyDiary')->findOrFail($id);
        return view('journals.edit', ['journal' => $journal, 'diary' => $journal->weeklyDiary, 'moods' => Journal::MOODS, 'selectedDate' => $journal->entry_date]);
    }

    public function update(Request $request, $id)
    {
        $journal = $request->user()->journals()->findOrFail($id); $validated = $request->validate(['title' => ['nullable', 'string', 'max:160'], 'content' => ['required', 'string', 'max:10000'], 'mood' => ['nullable', 'string', 'in:' . implode(',', array_keys(Journal::MOODS))]]);
        $journal->update($validated); return redirect()->route('journal.index')->with('success', __('Journal updated successfully.'));
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->journals()->findOrFail($id)->delete(); return redirect()->route('journal.index')->with('success', __('Journal deleted successfully.'));
    }
}
