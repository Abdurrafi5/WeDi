@extends('layouts.app')
@section('title', __('WeDi AI — WeDi'))
@section('content')
@php
    $userName = auth()->user()->greeting_name;
    $groups = $conversations->groupBy(function ($c) {
        $d = $c->last_message_at ?? $c->created_at;
        return $d->isToday() ? __('Hari ini') : ($d->isYesterday() ? __('Kemarin') : __('Lebih lama'));
    });
    $suggestions = [
        ['ri-heart-pulse-line', __('Aku merasa lelah minggu ini, bantu aku memahaminya.')],
        ['ri-seedling-line', __('Bantu aku menemukan hal yang patut disyukuri hari ini.')],
        ['ri-route-line', __('Aku bingung menentukan prioritas minggu depan.')],
        ['ri-quill-pen-line', __('Beri aku ide untuk memulai tulisan diary malam ini.')],
    ];
@endphp
<div class="page-head">
    <div>
        <p class="page-kicker">{{ __('Asisten AI') }}</p>
        <h1>WeDi AI</h1>
        <p>{{ __('Ngobrol, jelajahi pikiranmu, dan temukan sudut pandang baru.') }}</p>
    </div>
    <form method="POST" action="{{ route('ai.store') }}">@csrf
        <button type="submit" class="btn btn-primary"><i class="ri-add-line" aria-hidden="true"></i> {{ __('Chat baru') }}</button>
    </form>
</div>

<div class="ai-layout">
    <details class="card ai-sidebar ai-history" open aria-label="{{ __('Riwayat percakapan') }}">
        <summary class="ai-sidebar-head"><strong><i class="ri-history-line" aria-hidden="true"></i> {{ __('Riwayat') }} <small>({{ $conversations->count() }})</small></strong>
            @if($conversations->isNotEmpty())
            <form method="POST" action="{{ route('ai.destroyAll') }}" data-confirm="{{ __('Hapus semua riwayat percakapan? Tindakan ini tidak bisa dibatalkan.') }}">@csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm" title="{{ __('Hapus semua') }}"><i class="ri-delete-bin-line" aria-hidden="true"></i></button>
            </form>
            @endif
        </summary>
        <div class="ai-conv-scroll" data-ai-conv-list>
            @forelse($groups as $label => $items)
            <p class="ai-conv-group">{{ $label }}</p>
            <ul class="ai-conv-list">
                @foreach($items as $c)
                <li class="{{ $conversation && $conversation->id === $c->id ? 'active' : '' }}">
                    <a href="{{ route('ai.show', $c->id) }}"><span data-conv-title="{{ $c->id }}">{{ $c->title ?: __('Percakapan baru') }}</span><small>{{ ($c->last_message_at ?? $c->created_at)->diffForHumans() }}</small></a>
                    <form method="POST" action="{{ route('ai.destroy', $c->id) }}" data-confirm="{{ __('Hapus percakapan ini?') }}">@csrf @method('DELETE')
                        <button type="submit" class="icon-btn" aria-label="{{ __('Hapus percakapan') }}"><i class="ri-close-line" aria-hidden="true"></i></button>
                    </form>
                </li>
                @endforeach
            </ul>
            @empty
            <p class="ai-conv-empty">{{ __('Belum ada percakapan.') }}</p>
            @endforelse
        </div>
    </details>

    <section class="card ai-chat" @if($conversation) data-ai-chat data-url="{{ route('ai.message', $conversation->id) }}" data-conv-id="{{ $conversation->id }}" @endif data-remaining="{{ $remaining }}">
        @if(! $configured)
        <div class="ai-notice warn"><i class="ri-error-warning-line" aria-hidden="true"></i> {{ __('Fitur AI belum dikonfigurasi (OPENAI_API_KEY belum diisi di server).') }}</div>
        @endif

        @if($conversation)
        <div class="ai-messages" data-ai-messages aria-live="polite">
            @if($messages->isEmpty())
            <div class="ai-empty ai-hero" data-ai-empty>
                <div class="ai-orb" aria-hidden="true"><i class="ri-sparkling-2-line"></i></div>
                <h3>{{ __('Hai, :name', ['name' => $userName]) }} 👋</h3>
                <p>{{ __('Apa yang ada di pikiranmu? Ceritakan harimu, minta ide tulisan, atau diskusikan sesuatu yang mengganjal.') }}</p>
                <div class="ai-suggest">
                    @foreach($suggestions as [$icon, $s])
                    <button type="button" class="ai-suggest-card" data-ai-suggest="{{ $s }}"><i class="{{ $icon }}" aria-hidden="true"></i><span>{{ $s }}</span></button>
                    @endforeach
                </div>
            </div>
            @endif
            @foreach($messages as $m)
            <div class="ai-msg {{ $m->role }}" data-time="{{ $m->created_at->format('H:i') }}"><div class="ai-bubble">{{ $m->content }}</div></div>
            @endforeach
        </div>
        <button type="button" class="ai-scroll-down" data-ai-scroll hidden aria-label="{{ __('Ke pesan terbaru') }}"><i class="ri-arrow-down-line" aria-hidden="true"></i></button>

        <form class="ai-form" data-ai-form>
            <div class="ai-input-wrap">
                <textarea class="textarea" rows="1" maxlength="4000" placeholder="{{ __('Tulis pesan… (Enter kirim, Shift+Enter baris baru)') }}" data-ai-input aria-label="{{ __('Pesan') }}"></textarea>
                <span class="ai-count" data-ai-count hidden></span>
            </div>
            <button type="submit" class="btn btn-primary ai-send" data-ai-send><i class="ri-send-plane-2-fill" aria-hidden="true"></i><span class="sr-only">{{ __('Kirim') }}</span></button>
        </form>
        <p class="ai-foot"><i class="ri-lock-line" aria-hidden="true"></i> {{ __('Pesanmu dikirim ke OpenAI untuk diproses dan hanya bisa dilihat olehmu di WeDi. Hindari data sensitif.') }} · <span class="ai-quota"><span data-ai-remaining>{{ $remaining }}</span>/{{ $dailyLimit }} {{ __('sisa pesan AI hari ini') }}</span></p>
        @else
        <div class="ai-empty ai-hero">
            <div class="ai-orb" aria-hidden="true"><i class="ri-chat-smile-3-line"></i></div>
            <h3>{{ __('Hai, :name', ['name' => $userName]) }} 👋</h3>
            <p>{{ __('WeDi AI membantumu berefleksi dan menulis. Percakapan bersifat pribadi dan bisa kamu hapus kapan saja.') }}</p>
            <form method="POST" action="{{ route('ai.store') }}">@csrf<button type="submit" class="btn btn-primary">{{ __('Mulai percakapan pertamamu') }}</button></form>
        </div>
        @endif
    </section>
</div>
@endsection
@push('scripts')<script defer src="{{ asset('js/ai.js') }}?v={{ filemtime(public_path('js/ai.js')) }}"></script>@endpush
