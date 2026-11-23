<main class="mx-auto grid max-w-5xl gap-5 px-4 py-8 sm:px-8" data-platform-support-workspace>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-3xl font-semibold tracking-tight">Platform desteği</h1>
        <div class="flex flex-wrap items-center gap-3">
            <p class="text-sm" data-admin-account></p>
            <button type="button" class="offer-button offer-button-secondary" data-admin-logout>Çıkış yap</button>
        </div>
    </div>
    <div class="flex flex-wrap gap-3">
        <button type="button" class="offer-button w-fit" data-admin-support>Bana atanan talepler</button>
        <button type="button" class="offer-button offer-button-secondary" data-admin-notifications hidden>
            Bildirimler <span data-admin-notification-count aria-hidden="true" hidden></span>
        </button>
    </div>
    <p role="status" class="text-sm" data-admin-dashboard-status hidden></p>
</main>
@include('support-tickets-dialog')
@include('workspace-notifications')
