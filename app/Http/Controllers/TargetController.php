<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

class TargetController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user(); $diary = $user->getOrCreateActiveDiary();
        $targets = $user->targets()->where('weekly_diary_id', $diary->id)->orderByDesc('created_at')->get()->sortBy(fn ($target) => match ($target->priority) { 'high' => 0, 'medium' => 1, default => 2 })->values();
        $totalTargets = $targets->count(); $completedCount = $targets->where('is_completed', true)->count(); $remainingCount = $totalTargets - $completedCount; $progress = $totalTargets ? (int) round($completedCount / $totalTargets * 100) : 0;
        return view('targets.index', compact('targets', 'diary', 'totalTargets', 'completedCount', 'remainingCount', 'progress'));
    }

    public function store(Request $request)
    {
        $diary = $request->user()->getOrCreateActiveDiary();
        $validated = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:1000'], 'priority' => ['required', 'in:low,medium,high']]);
        $request->user()->targets()->create(['weekly_diary_id' => $diary->id, ...$validated]);
        return redirect()->route('targets.index')->with('success', __('Target added successfully.'));
    }

    public function toggle(Request $request, $id)
    {
        $target = $request->user()->targets()->findOrFail($id);
        $completed = !$target->is_completed;
        $target->forceFill(['is_completed' => $completed, 'completed_at' => $completed ? Carbon::now() : null])->save();
        return redirect()->route('targets.index')->with('success', $completed ? __('Target completed.') : __('Target marked incomplete.'));
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->targets()->findOrFail($id)->delete();
        return redirect()->route('targets.index')->with('success', __('Target deleted successfully.'));
    }
}
