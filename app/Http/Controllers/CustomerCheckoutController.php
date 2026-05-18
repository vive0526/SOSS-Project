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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerCheckoutController extends Controller
{
    private const SHIPPING_FEE_PENINSULAR = 5.00;

    private const TAX_RATE = 0.06;

    private const PAYMENT_METHODS = [
        'cash_on_delivery' => 'Cash on Delivery',
        'stripe_card' => 'Card (Stripe)',
        'stripe_fpx' => 'FPX (Stripe)',
    ];

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

        $state = (string) old('shipping_state', $request->user()->shipping_state);
        $country = (string) old('shipping_country', $request->user()->shipping_country ?? 'Malaysia');
        $shippingFee = $this->resolveShippingFee($state, $country);

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
        $taxPreview = $this->roundMoney($taxable * self::TAX_RATE);

        return view('customer.checkout.index', [
            'cart' => $priced['cart'],
            'subtotal' => $priced['subtotal'],
            'shippingFee' => $shippingFee,
            'discount' => $discountPreview,
            'tax' => $taxPreview,
            'total' => $taxable + $taxPreview + $shippingFee,
            'paymentMethods' => self::PAYMENT_METHODS,
            'customer' => $request->user(),
            'claimedCoupons' => $claimedCoupons,
            'selectedCouponCode' => $selectedCouponCode,
        ]);
    }

    public function place(Request $request)
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

        $data = $request->validate([
            'shipping_name' => 'required|string|max:120',
            'shipping_phone' => 'required|string|max:60',
            'shipping_address' => 'required|string|max:255',
            'shipping_city' => 'required|string|max:120',
            'shipping_state' => 'required|string|max:120',
            'shipping_postcode' => 'required|string|max:30',
            'shipping_country' => 'required|string|max:120',
            'payment_method' => 'required|in:' . implode(',', array_keys(self::PAYMENT_METHODS)),
            'coupon_code' => 'nullable|string|max:32',
        ]);

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
            $order = DB::transaction(function () use ($request, $data, $priced, $cart, $reservedAt, $reservationExpiresAt, $isStripe) {
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
                    $product->save();
                }

                $shippingFee = $this->resolveShippingFee((string) $data['shipping_state'], (string) $data['shipping_country']);

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
                $taxAmount = $this->roundMoney($taxableBase * self::TAX_RATE);
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
                    'tax_rate' => self::TAX_RATE,
                    'total_amount' => $grandTotal,
                    'shipping_fee' => $shippingFee,
                    'payment_method' => $data['payment_method'],
                    'payment_status' => $isStripe ? 'pending' : 'unpaid',
                    'shipping_name' => $data['shipping_name'],
                    'shipping_phone' => $data['shipping_phone'],
                    'shipping_address' => $data['shipping_address'],
                    'shipping_city' => $data['shipping_city'],
                    'shipping_state' => $data['shipping_state'],
                    'shipping_postcode' => $data['shipping_postcode'],
                    'shipping_country' => $data['shipping_country'],
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
        $stateNorm = $this->normalizeLocation($state);

        if ($countryNorm !== '' && !str_contains($countryNorm, 'malaysia')) {
            return self::SHIPPING_FEE_PENINSULAR;
        }

        foreach (['sabah', 'sarawak', 'labuan'] as $freeState) {
            if ($stateNorm === $freeState || str_contains($stateNorm, $freeState)) {
                return 0.0;
            }
        }

        return self::SHIPPING_FEE_PENINSULAR;
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
            ->route('profile.edit')
            ->withErrors([
                'profile' => 'Please update your phone number and shipping address before checkout.',
            ]);
    }
}
