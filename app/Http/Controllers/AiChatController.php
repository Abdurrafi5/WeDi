<?php

namespace App\Http\Controllers;

use App\Exceptions\AiException;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiChatController extends Controller
{
    public function __construct(private AiService $ai) {}

    private function conversations(Request $request)
    {
        return $request->user()->aiConversations()
            ->orderByDesc('last_message_at')->orderByDesc('id')->limit(50)->get();
    }

    private function render(Request $request, $conversation = null)
    {
        return view('ai.index', [
            'conversations' => $this->conversations($request),
            'conversation' => $conversation,
            'messages' => $conversation ? $conversation->messages()->get() : collect(),
            'remaining' => $this->ai->remainingToday($request->user()),
            'dailyLimit' => $this->ai->dailyLimit(),
            'configured' => $this->ai->isConfigured(),
        ]);
    }

    public function index(Request $request)
    {
        $latest = $request->user()->aiConversations()->orderByDesc('last_message_at')->orderByDesc('id')->first();

        return $this->render($request, $latest);
    }

    public function show(Request $request, $id)
    {
        return $this->render($request, $request->user()->aiConversations()->findOrFail($id));
    }

    /** New Chat */
    public function store(Request $request)
    {
        $conversation = $request->user()->aiConversations()->create(['last_message_at' => now()]);

        return redirect()->route('ai.show', $conversation->id);
    }

    public function message(Request $request, $id)
    {
        $conversation = $request->user()->aiConversations()->findOrFail($id);
        $validated = $request->validate(['message' => ['required', 'string', 'max:4000']]);
        $text = trim($validated['message']);

        $history = $conversation->messages()->latest('id')->limit(20)->get()->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->values()->all();
        $history[] = ['role' => 'user', 'content' => $text];

        try {
            $result = $this->ai->chat($request->user(), 'chat', $this->ai->safetyRules(), $history);
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode], $e->status);
        }

        DB::transaction(function () use ($conversation, $text, $result) {
            $conversation->messages()->create(['role' => 'user', 'content' => $text]);
            $conversation->messages()->create(['role' => 'assistant', 'content' => $result['content']]);
            $conversation->update([
                'title' => $conversation->title ?: Str::limit($text, 60, '…'),
                'last_message_at' => now(),
            ]);
        });

        return response()->json([
            'reply' => $result['content'],
            'title' => $conversation->fresh()->title,
            'remaining' => $result['remaining'],
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->aiConversations()->findOrFail($id)->delete();

        return redirect()->route('ai.index')->with('success', __('Percakapan dihapus.'));
    }

    public function destroyAll(Request $request)
    {
        $request->user()->aiConversations()->delete();

        return redirect()->route('ai.index')->with('success', __('Semua riwayat percakapan dihapus.'));
    }
}
