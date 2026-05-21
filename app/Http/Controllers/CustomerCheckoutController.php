<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Coupon;
use App\Models\CouponClaim;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Notifications\CompleteProfileNotification;
use App\Notifications\OrderPlacedNotification;
use App\Services\StripeReservationExpiryService;
use App\Models\AppSetting;
use App\Models\CustomerAddress;
use App\Models\ShippingRate;
use App\Support\MalaysiaStates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CustomerCheckoutController extends Controller
{
    private const FALLBACK_SHIPPING_FEE = 5.00;
    private const FALLBACK_TAX_RATE = 0.06;

    private const PAYMENT_METHODS = [
        'cash_on_delivery' => 'Cash on Delivery',
        'stripe_card' => 'Card (Stripe)',
        'stripe_fpx' => 'FPX (Stripe)',
    ];

    private function isStripeConfigured(): bool
    {
        $secret = config('services.stripe.secret');

        return is_string($secret) && trim($secret) !== '';
    }

    /**
     * @return array<string, string>
     */
    private function enabledPaymentMethods(): array
    {
        $methods = self::PAYMENT_METHODS;

        if (!$this->isStripeConfigured()) {
            unset($methods['stripe_card'], $methods['stripe_fpx']);
        }

        return $methods;
    }

    public function index(Request $request)
    {
        $cart = $request->session()->get('cart', []);
        if (empty($cart)) {
            return redirect()->route('customer.cart.index')
                ->withErrors(['cart' => 'Your cart is empty.']);
        }

        $profileRedirect = $this->guardCheckoutProfile($request);
        if ($profileRedirect) {
            return $profileRedirect;
        }

        $priced = $this->priceCartFromDatabase($cart);
        if (!$priced['ok']) {
            return redirect()->route('customer.cart.index')
                ->withErrors(['cart' => $priced['message']]);
        }

        $removedInvalidCount = (int) ($priced['removedInvalidCount'] ?? 0);
        $normalizedCount = (int) ($priced['normalizedCount'] ?? 0);
        if (($removedInvalidCount + $normalizedCount) > 0) {
            $request->session()->put('cart', $priced['sanitizedCart'] ?? []);
            if (empty($priced['cart'] ?? [])) {
                return redirect()->route('customer.cart.index')
                    ->withErrors(['cart' => 'Your cart is empty.']);
            }
            if ($removedInvalidCount > 0) {
                $request->session()->flash('warning', "We removed {$removedInvalidCount} item(s) that are no longer available or invalid.");
            } elseif ($normalizedCount > 0) {
                $request->session()->flash('warning', 'We updated your cart to match the latest product information.');
            }
        }

        $address = $this->resolveCheckoutAddress($request);
        if ($address === null) {
            return redirect()
                ->route('customer.addresses.create')
                ->withErrors(['address' => 'Please add at least one delivery address before checkout.']);
        }

        $request->session()->put('checkout_address_id', $address->getKey());

        $shippingFee = $this->resolveShippingFee((string) $address->state_key, (string) $address->country);
        $taxRate = $this->resolveTaxRate();

        $claimedCoupons = CouponClaim::query()
            ->where('user_id', $request->user()->getKey())
            ->whereNull('redeemed_at')
            ->with('coupon')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CouponClaim $claim) => $claim->coupon)
            ->filter();

        $selectedCouponCode = (string) $request->query('coupon_code', old('coupon_code', ''));
        if ($selectedCouponCode === '') {
            $selectedCouponCode = '';
        }

        $discountPreview = 0.0;
        if ($selectedCouponCode !== '') {
            $resolved = $this->resolveCouponDiscount($request->user()->getKey(), $selectedCouponCode, (float) $priced['subtotal']);
            if ($resolved['ok']) {
                $discountPreview = (float) ($resolved['discount'] ?? 0);
            }
        }

        $taxable = max(0.0, ((float) $priced['subtotal']) - $discountPreview);
        $taxPreview = $this->roundMoney($taxable * $taxRate);
        $stripeConfigured = $this->isStripeConfigured();

        return view('customer.checkout.index', [
            'cart' => $priced['cart'],
            'subtotal' => $priced['subtotal'],
            'shippingFee' => $shippingFee,
            'discount' => $discountPreview,
            'tax' => $taxPreview,
            'taxRate' => $taxRate,
            'total' => $taxable + $taxPreview + $shippingFee,
            'paymentMethods' => $this->enabledPaymentMethods(),
            'stripeConfigured' => $stripeConfigured,
            'customer' => $request->user(),
            'claimedCoupons' => $claimedCoupons,
            'selectedCouponCode' => $selectedCouponCode,
            'addresses' => CustomerAddress::query()
                ->where('user_id', $request->user()->getKey())
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->get(),
            'selectedAddress' => $address,
            'shippingPolicyText' => AppSetting::getString('shipping_policy_text', ''),
            'taxPolicyText' => AppSetting::getString('tax_policy_text', ''),
        ]);
    }

    public function place(Request $request, StripeReservationExpiryService $stripeReservationExpiryService)
    {
        $cart = $request->session()->get('cart', []);
        if (empty($cart)) {
            return redirect()->route('customer.cart.index')
                ->withErrors(['cart' => 'Your cart is empty.']);
        }

        $profileRedirect = $this->guardCheckoutProfile($request);
        if ($profileRedirect) {
            return $profileRedirect;
        }

        $stripeReservationExpiryService->expireDueReservationsBestEffort();

        $addressId = (string) ($request->input('address_id') ?: $request->session()->get('checkout_address_id', ''));

        $paymentMethods = $this->enabledPaymentMethods();

        $data = $request->validate([
            'address_id' => 'nullable|string',
            'payment_method' => 'required|in:' . implode(',', array_keys($paymentMethods)),
            'coupon_code' => 'nullable|string|max:32',
        ]);

        /** @var \App\Models\CustomerAddress|null $address */
        $address = CustomerAddress::query()
            ->where('id', $addressId)
            ->where('user_id', $request->user()->getKey())
            ->first();

        if (!$address) {
            return redirect()
                ->route('customer.checkout.index')
                ->withErrors(['address' => 'Please select a valid delivery address.']);
        }

        $priced = $this->priceCartFromDatabase($cart);
        if (!$priced['ok']) {
            return redirect()->route('customer.cart.index')
                ->withErrors(['cart' => $priced['message']]);
        }

        $removedInvalidCount = (int) ($priced['removedInvalidCount'] ?? 0);
        $normalizedCount = (int) ($priced['normalizedCount'] ?? 0);
        if (($removedInvalidCount + $normalizedCount) > 0) {
            $cart = $priced['sanitizedCart'] ?? [];
            $request->session()->put('cart', $cart);
            if (empty($priced['cart'] ?? [])) {
                return redirect()->route('customer.cart.index')
                    ->withErrors(['cart' => 'Your cart is empty.']);
            }
            if ($removedInvalidCount > 0) {
                $request->session()->flash('warning', "We removed {$removedInvalidCount} item(s) that are no longer available or invalid.");
            } elseif ($normalizedCount > 0) {
                $request->session()->flash('warning', 'We updated your cart to match the latest product information.');
            }
        }

        $isStripe = in_array($data['payment_method'], ['stripe_card', 'stripe_fpx'], true);
        $reservedAt = now();
        $reservationExpiresAt = $isStripe ? now()->addMinutes(5) : null;

        try {
            $order = DB::transaction(function () use ($request, $data, $priced, $cart, $reservedAt, $reservationExpiresAt, $isStripe, $address) {
                $productIds = collect($cart)->pluck('product_id')->filter()->unique()->values()->all();
                $products = Product::query()
                    ->whereIn('product_id', $productIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                $pricedLocked = $this->priceCartFromProductMap($cart, $products);
                if (!$pricedLocked['ok']) {
                    throw new \RuntimeException((string) ($pricedLocked['message'] ?? 'Your cart is invalid. Please review your cart and try again.'));
                }

                $removedInvalidCount = (int) ($pricedLocked['removedInvalidCount'] ?? 0);
                $normalizedCount = (int) ($pricedLocked['normalizedCount'] ?? 0);
                if (($removedInvalidCount + $normalizedCount) > 0 || empty($pricedLocked['cart'] ?? [])) {
                    throw new \RuntimeException('Your cart has changed due to updated maintenance options or pricing. Please review checkout and try again.');
                }

                foreach ($pricedLocked['cart'] as $item) {
                    $productId = (string) ($item['product_id'] ?? '');
                    /** @var \App\Models\Product|null $product */
                    $product = $products->get($productId);
                    if (!$product) {
                        throw new \RuntimeException('A product in your cart is no longer available.');
                    }

                    $requestedQty = (int) ($item['quantity'] ?? 0);
                    if ($requestedQty < 1) {
                        throw new \RuntimeException('Your cart has an invalid quantity. Please update your cart and try again.');
                    }

                    $reservedQty = (int) ($product->reserved_quantity ?? 0);
                    $available = (int) $product->stock_quantity - $reservedQty;

                    $maintenanceYear = isset($item['maintenance_year']) && $item['maintenance_year'] !== null
                        ? (int) $item['maintenance_year']
                        : null;
                    if ($maintenanceYear === 0) {
                        $maintenanceYear = null;
                    }

                    if ($maintenanceYear && (bool) ($product->requires_maintenance ?? false)) {
                        $yearAvailable = $product->availableMaintenanceStock($maintenanceYear);
                        if ($yearAvailable < $requestedQty) {
                            throw new InsufficientStockException(
                                (string) $product->getKey(),
                                'Sorry — that maintenance year option is out of stock. Please select another year.'
                            );
                        }
                    }
                    if ($available < $requestedQty) {
                        throw new InsufficientStockException(
                            (string) $product->getKey(),
                            'Sorry for the inconvenience — the last item was just reserved by another customer. Please review the latest stock before trying again.'
                        );
                    }
                }

                foreach ($pricedLocked['cart'] as $item) {
                    $productId = (string) ($item['product_id'] ?? '');
                    /** @var \App\Models\Product $product */
                    $product = $products->get($productId);

                    $requestedQty = (int) ($item['quantity'] ?? 0);
                    $product->reserved_quantity = (int) ($product->reserved_quantity ?? 0) + $requestedQty;

                    $maintenanceYear = isset($item['maintenance_year']) && $item['maintenance_year'] !== null
                        ? (int) $item['maintenance_year']
                        : null;
                    if ($maintenanceYear === 0) {
                        $maintenanceYear = null;
                    }

                    if ($maintenanceYear && (bool) ($product->requires_maintenance ?? false)) {
                        $reservedMap = $product->maintenance_reserved_quantities ?? [];
                        $current = (int) ($reservedMap[$maintenanceYear] ?? $reservedMap[(string) $maintenanceYear] ?? 0);
                        $reservedMap[$maintenanceYear] = $current + $requestedQty;
                        $product->maintenance_reserved_quantities = $reservedMap;
                    }
                    $product->save();
                }

                $shippingFee = $this->resolveShippingFee((string) $address->state_key, (string) $address->country);
                $taxRate = $this->resolveTaxRate();

                $couponCode = isset($data['coupon_code']) ? trim((string) $data['coupon_code']) : '';
                $coupon = null;
                $discountAmount = 0.0;
                $orderDiscountType = null;
                $orderDiscountValue = null;

                if ($couponCode !== '') {
                    $resolved = $this->resolveCouponDiscount($request->user()->getKey(), $couponCode, (float) $pricedLocked['subtotal'], true);
                    if (!$resolved['ok']) {
                        throw new \RuntimeException((string) ($resolved['message'] ?? 'Invalid coupon.'));
                    }

                    /** @var \App\Models\Coupon $coupon */
                    $coupon = $resolved['coupon'];
                    $discountAmount = (float) ($resolved['discount'] ?? 0);
                    $orderDiscountType = (string) $coupon->discount_type;
                    $orderDiscountValue = (float) $coupon->discount_value;
                }

                $taxableBase = max(0.0, ((float) $pricedLocked['subtotal']) - $discountAmount);
                $taxAmount = $this->roundMoney($taxableBase * $taxRate);
                $grandTotal = $taxableBase + $taxAmount + $shippingFee;

                $order = Order::create([
                    'user_id' => $request->user()->getKey(),
                    'status' => 'pending',
                    'shipment_status' => 'pending',
                    'subtotal_amount' => $pricedLocked['subtotal'],
                    'discount_amount' => $discountAmount,
                    'coupon_id' => $coupon?->id,
                    'coupon_code' => $coupon ? $coupon->code : null,
                    'order_discount_type' => $orderDiscountType,
                    'order_discount_value' => $orderDiscountValue,
                    'tax_amount' => $taxAmount,
                    'tax_rate' => $taxRate,
                    'total_amount' => $grandTotal,
                    'shipping_fee' => $shippingFee,
                    'payment_method' => $data['payment_method'],
                    'payment_status' => $isStripe ? 'pending' : 'unpaid',
                    'shipping_name' => $address->recipient_name,
                    'shipping_phone' => $address->phone,
                    'shipping_address' => $address->address_line,
                    'shipping_city' => $address->city,
                    'shipping_state' => $address->state_key,
                    'shipping_postcode' => $address->postcode,
                    'shipping_country' => $address->country,
                    'reserved_at' => $reservedAt,
                    'reservation_expires_at' => $reservationExpiresAt,
                ]);

                $lineSubtotalsCents = [];
                foreach ($pricedLocked['cart'] as $item) {
                    $lineSubtotal = (float) $item['price'] * (int) $item['quantity'];
                    $lineSubtotalCents = $this->toCents($lineSubtotal);
                    $lineSubtotalsCents[] = $lineSubtotalCents;
                }

                $discountCents = $this->toCents($discountAmount);
                $discountAllocCents = $this->allocateProRataCents($discountCents, $lineSubtotalsCents);

                $taxableLineBasesCents = [];
                foreach ($lineSubtotalsCents as $i => $lineSubtotalCents) {
                    $taxableLineBasesCents[$i] = max(0, $lineSubtotalCents - (int) ($discountAllocCents[$i] ?? 0));
                }
                $taxCents = $this->toCents($taxAmount);
                $taxAllocCents = $this->allocateProRataCents($taxCents, $taxableLineBasesCents);

                foreach ($pricedLocked['cart'] as $index => $item) {
                    $lineSubtotal = (float) $item['price'] * (int) $item['quantity'];
                    $lineDiscount = ((int) ($discountAllocCents[$index] ?? 0)) / 100;
                    $lineTax = ((int) ($taxAllocCents[$index] ?? 0)) / 100;
                    $lineTotal = $lineSubtotal - $lineDiscount + $lineTax;

                    OrderItem::create([
                        'order_id' => $order->getKey(),
                        'product_id' => $item['product_id'],
                        'product_name' => $item['name'],
                        'maintenance_year' => $item['maintenance_year'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'line_subtotal' => $lineSubtotal,
                        'line_discount' => $lineDiscount,
                        'line_tax' => $lineTax,
                        'line_total' => $lineTotal,
                        'total_price' => $lineSubtotal,
                    ]);
                }

                if ($coupon) {
                    CouponClaim::query()
                        ->where('coupon_id', $coupon->id)
                        ->where('user_id', $request->user()->getKey())
                        ->whereNull('redeemed_at')
                        ->limit(1)
                        ->update([
                            'order_id' => $order->getKey(),
                            'redeemed_at' => now(),
                        ]);
                }

                OrderStatusHistory::create([
                    'order_id' => $order->getKey(),
                    'status' => 'pending',
                    'note' => 'Order placed by customer.',
                    'changed_by' => $request->user()->getKey(),
                ]);

                return $order;
            });
        } catch (\Throwable $e) {
            if ($e instanceof InsufficientStockException) {
                return redirect()
                    ->route('customer.products.show', $e->productId)
                    ->with('warning', $e->getMessage());
            }

            return redirect()
                ->route('customer.checkout.index')
                ->withErrors(['checkout' => $e->getMessage() ?: 'Unable to place order. Please try again.'])
                ->withInput();
        }

        $request->session()->forget('cart');

        if (in_array($order->payment_method, ['stripe_card', 'stripe_fpx'], true)) {
            return redirect()->route('customer.checkout.stripe.start', $order);
        }

        $request->user()?->notify(new OrderPlacedNotification($order));

        return redirect()->route('customer.checkout.processing', $order)
            ->with('success', 'Order placed successfully.');
    }

    /**
     * Price the cart using authoritative data from already-loaded (and potentially locked) Product models.
     *
     * @param array<string, array<string, mixed>> $cart
     * @param \Illuminate\Support\Collection<string, \App\Models\Product> $products
     * @return array{ok: bool, message?: string, cart?: array<string, array<string, mixed>>, sanitizedCart?: array<string, array<string, mixed>>, removedInvalidCount?: int, normalizedCount?: int, subtotal?: float, totalQuantity?: int}
     */
    private function priceCartFromProductMap(array $cart, $products): array
    {
        $pricedCart = [];
        $sanitizedCart = [];
        $subtotal = 0.0;
        $totalQuantity = 0;
        $removedInvalidCount = 0;
        $normalizedCount = 0;

        foreach ($cart as $key => $item) {
            $productId = (string) ($item['product_id'] ?? '');
            if ($productId === '' || !$products->has($productId)) {
                $removedInvalidCount++;
                continue;
            }

            /** @var \App\Models\Product $product */
            $product = $products->get($productId);

            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity < 1) {
                $removedInvalidCount++;
                continue;
            }

            $maintenanceYear = isset($item['maintenance_year']) && $item['maintenance_year'] !== null
                ? (int) $item['maintenance_year']
                : null;
            if ($maintenanceYear === 0) {
                $maintenanceYear = null;
            }

            if ((bool) ($product->requires_maintenance ?? false)) {
                if (!$maintenanceYear) {
                    $removedInvalidCount++;
                    continue;
                }

                $prices = $product->maintenance_prices ?? [];
                if (!array_key_exists($maintenanceYear, $prices)) {
                    $removedInvalidCount++;
                    continue;
                }
            } else {
                $maintenanceYear = null;
            }

            $unitPrice = $this->resolveUnitPrice($product, $maintenanceYear);
            $resolvedKey = $this->buildKey((string) $product->getKey(), $maintenanceYear);
            if ($resolvedKey !== (string) $key) {
                $normalizedCount++;
            }

            $item = array_merge($item, [
                'key' => $resolvedKey,
                'product_id' => $product->getKey(),
                'name' => $product->name,
                'price' => $unitPrice,
                'quantity' => $quantity,
                'maintenance_year' => $maintenanceYear,
                'image' => $product->image,
            ]);

            $pricedCart[$resolvedKey] = $item;
            $sanitizedCart[$resolvedKey] = [
                'key' => $resolvedKey,
                'product_id' => (string) $product->getKey(),
                'quantity' => $quantity,
                'maintenance_year' => $maintenanceYear,
            ];
            $subtotal += $unitPrice * $quantity;
            $totalQuantity += $quantity;
        }

        return [
            'ok' => true,
            'cart' => $pricedCart,
            'sanitizedCart' => $sanitizedCart,
            'removedInvalidCount' => $removedInvalidCount,
            'normalizedCount' => $normalizedCount,
            'subtotal' => $subtotal,
            'totalQuantity' => $totalQuantity,
        ];
    }

    public function processing(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        return view('customer.checkout.processing', [
            'order' => $order,
            'paymentMethods' => self::PAYMENT_METHODS,
        ]);
    }

    /**
     * Price the cart using authoritative data from the database.
     *
     * @param array<string, array<string, mixed>> $cart
     * @return array{ok: bool, message?: string, cart?: array<string, array<string, mixed>>, sanitizedCart?: array<string, array<string, mixed>>, removedInvalidCount?: int, normalizedCount?: int, products?: \Illuminate\Support\Collection, subtotal?: float, totalQuantity?: int}
     */
    private function priceCartFromDatabase(array $cart): array
    {
        $productIds = collect($cart)->pluck('product_id')->filter()->unique()->values()->all();
        $products = Product::whereIn('product_id', $productIds)->get()->keyBy('product_id');

        $pricedCart = [];
        $sanitizedCart = [];
        $subtotal = 0.0;
        $totalQuantity = 0;
        $removedInvalidCount = 0;
        $normalizedCount = 0;

        foreach ($cart as $key => $item) {
            $productId = (string) ($item['product_id'] ?? '');
            if ($productId === '' || !$products->has($productId)) {
                $removedInvalidCount++;
                continue;
            }

            /** @var \App\Models\Product $product */
            $product = $products->get($productId);

            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity < 1) {
                $removedInvalidCount++;
                continue;
            }

            $maintenanceYear = isset($item['maintenance_year']) && $item['maintenance_year'] !== null
                ? (int) $item['maintenance_year']
                : null;
            if ($maintenanceYear === 0) {
                $maintenanceYear = null;
            }

            if ((bool) ($product->requires_maintenance ?? false)) {
                if (!$maintenanceYear) {
                    $removedInvalidCount++;
                    continue;
                }

                $prices = $product->maintenance_prices ?? [];
                if (!array_key_exists($maintenanceYear, $prices)) {
                    $removedInvalidCount++;
                    continue;
                }
            } else {
                $maintenanceYear = null;
            }

            $unitPrice = $this->resolveUnitPrice($product, $maintenanceYear);

            $resolvedKey = $this->buildKey((string) $product->getKey(), $maintenanceYear);
            if ($resolvedKey !== (string) $key) {
                $normalizedCount++;
            }

            $item = array_merge($item, [
                'key' => $resolvedKey,
                'product_id' => $product->getKey(),
                'name' => $product->name,
                'price' => $unitPrice,
                'quantity' => $quantity,
                'maintenance_year' => $maintenanceYear,
                'image' => $product->image,
            ]);

            $pricedCart[$resolvedKey] = $item;
            $sanitizedCart[$resolvedKey] = [
                'key' => $resolvedKey,
                'product_id' => (string) $product->getKey(),
                'quantity' => $quantity,
                'maintenance_year' => $maintenanceYear,
            ];
            $subtotal += $unitPrice * $quantity;
            $totalQuantity += $quantity;
        }

        return [
            'ok' => true,
            'cart' => $pricedCart,
            'sanitizedCart' => $sanitizedCart,
            'removedInvalidCount' => $removedInvalidCount,
            'normalizedCount' => $normalizedCount,
            'products' => $products,
            'subtotal' => $subtotal,
            'totalQuantity' => $totalQuantity,
        ];
    }

    private function buildKey(string $productId, ?int $maintenanceYear): string
    {
        return $productId . '-' . ($maintenanceYear ?? 0);
    }

    private function resolveShippingFee(string $state, string $country): float
    {
        $countryNorm = $this->normalizeLocation($country);
        if ($countryNorm !== '' && !str_contains($countryNorm, 'malaysia')) {
            return self::FALLBACK_SHIPPING_FEE;
        }

        $stateKey = MalaysiaStates::normalize($state) ?? $state;
        if (!in_array($stateKey, MalaysiaStates::keys(), true)) {
            return self::FALLBACK_SHIPPING_FEE;
        }

        /** @var \App\Models\ShippingRate|null $rate */
        $rate = ShippingRate::query()
            ->where('state_key', $stateKey)
            ->where('active', true)
            ->first();

        if (!$rate) {
            return self::FALLBACK_SHIPPING_FEE;
        }

        return (float) $rate->shipping_fee;
    }

    private function resolveTaxRate(): float
    {
        $rate = AppSetting::getDecimal('malaysia_tax_rate', self::FALLBACK_TAX_RATE);

        if (!is_finite($rate) || $rate < 0) {
            return self::FALLBACK_TAX_RATE;
        }

        return min(1.0, $rate);
    }

    private function resolveCheckoutAddress(Request $request): ?CustomerAddress
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        $queryId = $request->query('address_id');
        $sessionId = $request->session()->get('checkout_address_id');

        $candidateId = $queryId ?: $sessionId;

        if ($candidateId) {
            $found = CustomerAddress::query()
                ->where('id', $candidateId)
                ->where('user_id', $user->getKey())
                ->first();
            if ($found) {
                return $found;
            }
        }

        $default = CustomerAddress::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if ($default) {
            return $default;
        }

        // Backward-compat: create first address from legacy user shipping fields if available.
        if ($user->isCheckoutProfileComplete() && filled($user->shipping_address)) {
            $stateKey = MalaysiaStates::normalize($user->shipping_state) ?? null;
            if ($stateKey === null) {
                return null;
            }

            return CustomerAddress::create([
                'user_id' => $user->getKey(),
                'label' => 'Default',
                'recipient_name' => $user->name,
                'phone' => (string) $user->phone,
                'address_line' => (string) $user->shipping_address,
                'city' => (string) $user->shipping_city,
                'state_key' => $stateKey,
                'postcode' => (string) $user->shipping_postcode,
                'country' => (string) ($user->shipping_country ?: 'Malaysia'),
                'is_default' => true,
            ]);
        }

        return null;
    }

    /**
     * @return array{ok: bool, message?: string, coupon?: \App\Models\Coupon, discount?: float}
     */
    private function resolveCouponDiscount(string $userId, string $couponCode, float $subtotal, bool $requireClaimed = false): array
    {
        $couponCode = trim($couponCode);
        if ($couponCode === '') {
            return ['ok' => true, 'discount' => 0.0];
        }

        $coupon = Coupon::query()
            ->where('code', $couponCode)
            ->where('status', Coupon::STATUS_ACTIVE)
            ->first();

        if (!$coupon) {
            return ['ok' => false, 'message' => 'Coupon code is invalid.'];
        }

        $now = now();
        if ($coupon->starts_at && $coupon->starts_at->gt($now)) {
            return ['ok' => false, 'message' => 'Coupon is not available yet.'];
        }
        if ($coupon->ends_at && $coupon->ends_at->lt($now)) {
            return ['ok' => false, 'message' => 'Coupon has expired.'];
        }

        if (((float) $coupon->min_subtotal) > 0 && $subtotal < (float) $coupon->min_subtotal) {
            return ['ok' => false, 'message' => 'Order subtotal does not meet the coupon minimum.'];
        }

        if ($requireClaimed) {
            $claim = CouponClaim::query()
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->whereNull('redeemed_at')
                ->lockForUpdate()
                ->first();

            if (!$claim) {
                return ['ok' => false, 'message' => 'Please claim this coupon first before using it at checkout.'];
            }
        }

        if ($coupon->max_total_redemptions !== null) {
            $redeemedCount = CouponClaim::query()
                ->where('coupon_id', $coupon->id)
                ->whereNotNull('redeemed_at')
                ->count();

            if ($redeemedCount >= (int) $coupon->max_total_redemptions) {
                return ['ok' => false, 'message' => 'This coupon has reached its redemption limit.'];
            }
        }

        $discount = 0.0;
        if ((string) $coupon->discount_type === Coupon::TYPE_PERCENT) {
            $discount = $this->roundMoney($subtotal * (((float) $coupon->discount_value) / 100));
        } elseif ((string) $coupon->discount_type === Coupon::TYPE_AMOUNT) {
            $discount = $this->roundMoney((float) $coupon->discount_value);
        } else {
            return ['ok' => false, 'message' => 'Coupon configuration is invalid.'];
        }

        $discount = max(0.0, min($subtotal, $discount));

        return [
            'ok' => true,
            'coupon' => $coupon,
            'discount' => $discount,
        ];
    }

    private function normalizeLocation(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/[^a-z0-9\\s]/', ' ', $value) ?? $value;
        $value = preg_replace('/\\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function roundMoney(float $value): float
    {
        return (float) number_format($value, 2, '.', '');
    }

    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Distribute total cents across weighted lines, preserving sum(total)=sum(lines).
     *
     * @param int $totalCents
     * @param array<int, int> $weightsCents
     * @return array<int, int>
     */
    private function allocateProRataCents(int $totalCents, array $weightsCents): array
    {
        $count = count($weightsCents);
        if ($count === 0) {
            return [];
        }

        if ($totalCents <= 0) {
            return array_fill(0, $count, 0);
        }

        $totalWeight = array_sum(array_map(fn ($v) => max(0, (int) $v), $weightsCents));
        if ($totalWeight <= 0) {
            $alloc = array_fill(0, $count, 0);
            $alloc[$count - 1] = $totalCents;
            return $alloc;
        }

        $raw = [];
        $alloc = [];
        $fractional = [];
        $allocated = 0;

        foreach ($weightsCents as $i => $w) {
            $w = max(0, (int) $w);
            $share = ($totalCents * $w) / $totalWeight;
            $floor = (int) floor($share);
            $alloc[$i] = $floor;
            $allocated += $floor;
            $fractional[$i] = $share - $floor;
            $raw[$i] = $share;
        }

        $remaining = $totalCents - $allocated;
        if ($remaining > 0) {
            arsort($fractional);
            $keys = array_keys($fractional);
            $k = 0;
            while ($remaining > 0) {
                $idx = $keys[$k % count($keys)];
                $alloc[$idx]++;
                $remaining--;
                $k++;
            }
        }

        ksort($alloc);

        return array_values($alloc);
    }

    private function resolveUnitPrice(Product $product, ?int $maintenanceYear): float
    {
        if ((bool) ($product->requires_maintenance ?? false) && $maintenanceYear) {
            $prices = $product->maintenance_prices ?? [];
            $price = $prices[$maintenanceYear] ?? null;
            if ($price !== null) {
                return (float) $price;
            }
        }

        return (float) $product->price;
    }

    private function guardCheckoutProfile(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->isCheckoutProfileComplete()) {
            return null;
        }

        $request->session()->put('show_profile_completion_modal', true);

        if (Schema::hasTable('notifications')) {
            $exists = $user->unreadNotifications()
                ->where('type', CompleteProfileNotification::class)
                ->exists();

            if (!$exists) {
                $user->notify(new CompleteProfileNotification($user->missingCheckoutProfileFields()));
            }
        }

        return redirect()
            ->route('customer.addresses.create')
            ->withErrors([
                'profile' => 'Please add a delivery address before checkout.',
            ]);
    }
}
