<div class="ai-assist" data-ai-journal data-url="{{ route('ai.journal') }}">
    <div class="ai-assist-head">
        <strong><span class="ai-badge"><i class="ri-sparkling-2-line" aria-hidden="true"></i></span> {{ __('Asisten AI') }}</strong>
        <span class="muted">{{ __('Teks jurnal yang kamu tulis dikirim ke OpenAI hanya saat kamu menekan salah satu tombol di bawah.') }}</span>
    </div>
    <div class="ai-action-grid">
        @foreach([
            ['starter', 'ri-quill-pen-line', 'Writing Starter', __('Bantu mulai menulis')],
            ['expand', 'ri-expand-diagonal-line', 'Expand My Writing', __('Kembangkan tulisan')],
            ['clarity', 'ri-focus-3-line', 'Improve Clarity', __('Perjelas kalimat')],
            ['words', 'ri-chat-quote-line', 'Find the Right Words', __('Cari ungkapan pas')],
            ['questions', 'ri-question-answer-line', 'Reflection Questions', __('Pertanyaan refleksi')],
        ] as [$key, $icon, $label, $desc])
        <button type="button" class="ai-action-card" data-ai-action="{{ $key }}"><i class="{{ $icon }}" aria-hidden="true"></i><span><b>{{ __($label) }}</b><small>{{ $desc }}</small></span></button>
        @endforeach
    </div>
    <div class="ai-assist-preview" data-ai-preview hidden>
        <div class="ai-skeleton" data-ai-loading hidden aria-live="polite"><span class="ai-dots"><i></i><i></i><i></i></span> <em>{{ __('AI sedang berpikir…') }}</em><i class="sk"></i><i class="sk"></i><i class="sk short"></i></div>
        <div class="ai-assist-error" data-ai-error hidden role="alert"></div>
        <div data-ai-result-wrap hidden>
            <p class="eyebrow">{{ __('Pratinjau hasil AI') }}</p>
            <div class="ai-assist-result" data-ai-result></div>
            <div class="ai-assist-buttons">
                <button type="button" class="btn btn-primary btn-sm" data-ai-insert><i class="ri-corner-down-left-line" aria-hidden="true"></i> {{ __('Insert') }}</button>
                <button type="button" class="btn btn-secondary btn-sm" data-ai-copy><i class="ri-file-copy-line" aria-hidden="true"></i> {{ __('Copy') }}</button>
                <button type="button" class="btn btn-ghost btn-sm" data-ai-dismiss>{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>
@push('scripts')<script defer src="{{ asset('js/ai.js') }}?v={{ filemtime(public_path('js/ai.js')) }}"></script>@endpush
