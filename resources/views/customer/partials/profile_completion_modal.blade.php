@php
    $user = auth()->user();
    $missingFields = $user?->missingCheckoutProfileFields() ?? [];
@endphp

<div class="sf-modal is-open" data-profile-modal>
    <button type="button" class="sf-modal__backdrop" data-profile-modal-close aria-label="Close"></button>
    <div class="sf-modal__panel" role="dialog" aria-modal="true" aria-label="Complete your profile">
        <div class="sf-modal__title">Update your profile to checkout</div>
        <div class="sf-modal__text">
            @if(!empty($missingFields))
                Missing: <strong>{{ implode(', ', $missingFields) }}</strong>.
            @else
                Please update your phone number and shipping address to proceed with checkout.
            @endif
        </div>

        <div class="sf-modal__actions">
            <a class="btn btn-primary" href="{{ route('profile.edit') }}">Update profile</a>
            <button type="button" class="btn btn-outline" data-profile-modal-later>Remind me later</button>
        </div>
    </div>
</div>

<script>
    (function () {
        const modal = document.querySelector('[data-profile-modal]');
        if (!modal) return;

        const closeButtons = modal.querySelectorAll('[data-profile-modal-close]');
        const laterBtn = modal.querySelector('[data-profile-modal-later]');

        const closeModal = () => {
            modal.classList.remove('is-open');
        };

        closeButtons.forEach((btn) => btn.addEventListener('click', closeModal));

        if (laterBtn) {
            laterBtn.addEventListener('click', async () => {
                try {
                    await fetch(@json(route('customer.profile-prompt.dismiss')), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json',
                            ...(document.querySelector('meta[name="csrf-token"]')?.content
                                ? { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                                : {}),
                        },
                        body: JSON.stringify({}),
                    });
                } catch (_) {
                    // ignore network errors
                }

                closeModal();
            });
        }
    })();
</script>

