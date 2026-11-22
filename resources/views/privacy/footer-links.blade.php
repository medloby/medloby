@inject('privacyFooter', 'App\Services\PrivacyPublication')

@php
    $showPrivacy = $privacyFooter->isPublished();
    $editorEmail = $privacyFooter->email('editor_email');
    $supportEmail = $privacyFooter->email('support_email');
@endphp

@if ($showPrivacy || $editorEmail || $supportEmail)
    <nav aria-label="Kişisel veriler ve iletişim" class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
        @if ($showPrivacy)
            <a class="underline underline-offset-4" href="{{ route('privacy.notice', 'hasta') }}">Hasta hesabı KVKK</a>
            <a class="underline underline-offset-4" href="{{ route('privacy.notice', 'klinik') }}">Klinik KVKK</a>
        @endif
        @if ($editorEmail)
            <a class="underline underline-offset-4" href="mailto:{{ $editorEmail }}">İçerik editörü</a>
        @endif
        @if ($supportEmail)
            <a class="underline underline-offset-4" href="mailto:{{ $supportEmail }}">Destek</a>
        @endif
    </nav>
@endif
