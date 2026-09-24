<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $diaries = $user->weeklyDiaries()->withCount(['journals', 'targets', 'completedTargets'])->with('recap')->orderByDesc('week_start')->get();
        return view('history', compact('diaries'));
    }
}
