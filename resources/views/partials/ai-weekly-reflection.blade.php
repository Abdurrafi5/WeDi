<section class="card ai-reflection" data-ai-reflection data-url="{{ route('recap.ai.generate') }}" data-save-url="{{ url('/recap/ai') }}">
    <div class="panel-heading">
        <div><span class="panel-kicker">{{ __('Weekly Reflection AI') }}</span><h2>{{ __('Bantu aku menyusun refleksi') }}</h2></div>
        <span class="ai-badge lg"><i class="ri-sparkling-2-line" aria-hidden="true"></i></span>
    </div>

    <ol class="ai-steps" data-ai-steps>
        <li class="active" data-step="1"><b>1</b><span>{{ __('Pilih jurnal') }}</span></li>
        <li data-step="2"><b>2</b><span>{{ __('Setujui') }}</span></li>
        <li data-step="3"><b>3</b><span>{{ __('Edit & simpan') }}</span></li>
    </ol>

    @if($journals->isEmpty())
    <p class="muted">{{ __('Tulis minimal satu jurnal minggu ini agar AI bisa membantu merangkum.') }}</p>
    @else
    <p class="muted">{{ __('Pilih jurnal yang boleh dibaca AI. Hanya jurnal yang kamu centang yang akan dikirim.') }}</p>
    <div class="ai-journal-pick">
        @foreach($journals as $j)
        <label class="ai-pick"><input type="checkbox" name="journal_ids[]" value="{{ $j->id }}"><i class="{{ $j->mood_emoji }} ai-pick-mood" aria-hidden="true"></i><span><strong>{{ $j->entry_date->isoFormat('ddd, D MMM') }}</strong><small>{{ $j->title ?: \Illuminate\Support\Str::limit($j->content, 38) }}</small></span><i class="ri-check-line ai-pick-check" aria-hidden="true"></i></label>
        @endforeach
    </div>
    <div class="ai-reflection-options">
        <button type="button" class="btn btn-ghost btn-sm" data-ai-select-all>{{ __('Pilih semua') }}</button>
        <span class="ai-sel-count" data-ai-count>0 / {{ $journals->count() }}</span>
        <label class="ai-check"><input type="checkbox" data-ai-targets> {{ __('Sertakan target minggu ini') }}</label>
    </div>
    <label class="ai-check consent"><input type="checkbox" data-ai-consent> {{ __('Saya setuju jurnal yang dipilih dikirim ke OpenAI untuk membuat refleksi ini.') }}</label>
    <div class="ai-reflection-actions">
        <button type="button" class="btn btn-primary" data-ai-generate disabled><i class="ri-magic-line" aria-hidden="true"></i> {{ __('Generate refleksi') }}</button>
        <span class="muted" data-ai-status role="status"></span>
    </div>
    <div class="ai-skeleton block" data-ai-loading hidden aria-hidden="true"><i class="sk"></i><i class="sk"></i><i class="sk short"></i><i class="sk"></i><i class="sk short"></i></div>
    <div class="ai-assist-error" data-ai-error hidden role="alert"></div>

    <form method="POST" class="ai-reflection-result" data-ai-result hidden>
        @csrf
        <p class="eyebrow">{{ __('Hasil AI — kamu bisa mengedit sebelum menyimpan') }}</p>
        <div class="ai-result-grid">
        @foreach([
            'summary' => [__('Ringkasan minggu'), 'ri-article-line', 'c1'],
            'highlights' => [__('Sorotan'), 'ri-trophy-line', 'c2'],
            'mood_overview' => [__('Gambaran mood'), 'ri-emotion-line', 'c3'],
            'personal_growth' => [__('Pertumbuhan pribadi'), 'ri-seedling-line', 'c4'],
            'reflection_questions' => [__('Pertanyaan refleksi'), 'ri-question-answer-line', 'c5'],
            'next_week_suggestions' => [__('Saran minggu depan'), 'ri-route-line', 'c6'],
        ] as $key => [$label, $icon, $tone])
        <div class="ai-result-card {{ $tone }}"><label for="ai-{{ $key }}"><i class="{{ $icon }}" aria-hidden="true"></i> {{ $label }}</label><textarea class="textarea" id="ai-{{ $key }}" name="{{ $key }}" rows="5" maxlength="3000"></textarea></div>
        @endforeach
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary"><i class="ri-save-line" aria-hidden="true"></i> {{ __('Simpan ke Weekly Recap') }}</button><span class="muted">{{ __('Ini akan mengisi kolom Recap di bawah.') }}</span></div>
    </form>
    @endif
</section>
@push('scripts')<script defer src="{{ asset('js/ai.js') }}?v={{ filemtime(public_path('js/ai.js')) }}"></script>@endpush
