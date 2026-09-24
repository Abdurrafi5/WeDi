<div class="flash" aria-live="polite" role="status">
    @if (session('success'))
        <div class="flash-item"><i class="ri-checkbox-circle-fill" aria-hidden="true"></i><span>{{ session('success') }}</span></div>
    @endif
    @if (session('error'))
        <div class="flash-item error"><i class="ri-error-warning-fill" aria-hidden="true"></i><span>{{ session('error') }}</span></div>
    @endif
</div>
