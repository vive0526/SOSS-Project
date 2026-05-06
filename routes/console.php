<?php

use App\Services\StripeReservationExpiryService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('orders:expire-stripe-reservations', function (StripeReservationExpiryService $service) {
    $expired = $service->expireDueReservations();
    $this->info("Expired {$expired} order(s).");
})->purpose('Cancel unpaid Stripe orders after reservation expiry and release reserved stock');
