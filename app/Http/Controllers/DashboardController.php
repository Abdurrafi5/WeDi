<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $diary = $user->getOrCreateActiveDiary();
        $user->weeklyDiaries()->where('status', 'active')->where('id', '!=', $diary->id)->update(['status' => 'completed']);
        $journals = $diary->journals()->where('user_id', $user->id)->orderBy('entry_date', 'desc')->get();
        $targets = $diary->targets()->where('user_id', $user->id)->get();
        $completedTargets = $targets->where('is_completed', true);
        $totalTargets = $targets->count();
        $completedCount = $completedTargets->count();
        $progress = $totalTargets ? (int) round(($completedCount / $totalTargets) * 100) : 0;
        $lastJournal = $journals->first();
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $diary->week_start->copy()->addDays($i);
            $entry = $journals->first(fn ($journal) => $journal->entry_date->isSameDay($date));
            $weekDays[] = ['date' => $date, 'entry' => $entry, 'is_today' => $date->isToday()];
        }
        $focusTargets = $targets->where('is_completed', false)->sortByDesc('priority')->take(3);
        return view('dashboard', compact('user', 'diary', 'journals', 'targets', 'totalTargets', 'completedCount', 'progress', 'lastJournal', 'weekDays', 'focusTargets'));
    }
}
