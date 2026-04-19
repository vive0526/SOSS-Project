@extends('layouts.customer')

@section('title', 'Shopping Cart')
@section('page_title', 'Shopping Cart')
@section('page_subtitle', 'Review your items before checkout')

@section('content')
    <div id="cartAjaxError" class="customer-card" style="display:none;">
        <p id="cartAjaxErrorText" style="margin:0;"></p>
    </div>

    @if(session('success'))
        <div class="customer-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="customer-card">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    @if(empty($cart))
        <div class="customer-empty">
            Your cart is empty. Browse products to get started.
        </div>
        <div style="margin-top:16px;">
            <a class="btn btn-primary" href="{{ route('customer.products.index') }}">Browse Products</a>
        </div>
    @else
        <div class="customer-card">
            <table class="customer-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Maintenance Year</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cart as $item)
                        <tr>
                            <td>{{ $item['name'] }}</td>
                            <td>{{ $item['maintenance_year'] ?? '-' }}</td>
                            <td>RM {{ number_format((float) $item['price'], 2) }}</td>
                            <td>
                                <form method="POST" action="{{ route('customer.cart.update', $item['key']) }}">
                                    @csrf
                                    <input type="number"
                                           name="quantity"
                                           min="1"
                                           step="1"
                                           value="{{ $item['quantity'] }}"
                                           data-cart-qty
                                           data-item-key="{{ $item['key'] }}"
                                           data-unit-price="{{ number_format((float) $item['price'], 2, '.', '') }}"
                                           data-update-url="{{ route('customer.cart.update', $item['key']) }}"
                                           style="width:80px;">
                                    <button type="submit" class="btn btn-outline cart-update-btn">Update</button>
                                </form>
                            </td>
                            <td data-cart-row-subtotal="{{ $item['key'] }}">
                                RM {{ number_format((float) $item['price'] * (int) $item['quantity'], 2) }}
                            </td>
                            <td>
                                <form method="POST" action="{{ route('customer.cart.remove', $item['key']) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline"
                                            onclick="return confirm('Remove this item?')">
                                        Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="customer-card" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px;">
                 <div>
                 <div class="customer-kpi__label">Items</div>
                <div class="customer-kpi__value" data-cart-total-quantity>{{ $totalQuantity }}</div>
                <div class="customer-kpi__note">Total quantity</div>
            </div>
            <div>
                <div class="customer-kpi__label">Subtotal</div>
                <div class="customer-kpi__value" data-cart-subtotal>RM {{ number_format((float) $subtotal, 2) }}</div>
                <div class="customer-kpi__note">Shipping added at checkout</div>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <a class="btn btn-outline" href="{{ route('customer.products.index') }}">Continue Shopping</a>
                <a class="btn btn-primary" href="{{ route('customer.checkout.index') }}">Proceed to Checkout</a>
            </div>
        </div>
    @endif

    <script>
        (function () {
            const qtyInputs = Array.from(document.querySelectorAll('[data-cart-qty]'));
            if (qtyInputs.length === 0) return;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const errorWrap = document.getElementById('cartAjaxError');
            const errorText = document.getElementById('cartAjaxErrorText');
            const subtotalEl = document.querySelector('[data-cart-subtotal]');
            const totalQtyEl = document.querySelector('[data-cart-total-quantity]');
            const rowSubtotalCells = new Map();
            document.querySelectorAll('[data-cart-row-subtotal]').forEach((el) => {
                const key = el.getAttribute('data-cart-row-subtotal');
                if (key) rowSubtotalCells.set(key, el);
            });

            const formatMoney = (amount) => {
                const num = Number(amount);
                if (!Number.isFinite(num)) return 'RM 0.00';
                return 'RM ' + num.toFixed(2);
            };

            const showError = (message) => {
                if (!errorWrap || !errorText) {
                    alert(message);
                    return;
                }
                errorText.textContent = message;
                errorWrap.style.display = '';
                errorWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            };

            const clearError = () => {
                if (!errorWrap || !errorText) return;
                errorText.textContent = '';
                errorWrap.style.display = 'none';
            };

            const recalcTotalsFromInputs = () => {
                let subtotal = 0;
                let totalQty = 0;

                for (const input of qtyInputs) {
                    const unitPrice = Number(input.dataset.unitPrice || 0);
                    const qty = parseInt(input.value, 10);
                    if (!Number.isFinite(unitPrice) || !Number.isFinite(qty)) continue;
                    subtotal += unitPrice * qty;
                    totalQty += qty;
                }

                if (subtotalEl) subtotalEl.textContent = formatMoney(subtotal);
                if (totalQtyEl) totalQtyEl.textContent = String(totalQty);
            };

            const setRowSubtotal = (itemKey, unitPrice, qty) => {
                const cell = rowSubtotalCells.get(itemKey);
                if (!cell) return;
                cell.textContent = formatMoney(Number(unitPrice) * Number(qty));
            };

            const setRowSubtotalAmount = (itemKey, amount) => {
                const cell = rowSubtotalCells.get(itemKey);
                if (!cell) return;
                cell.textContent = formatMoney(amount);
            };

            const persistQty = async (input, qty, previousQty, signal) => {
                const updateUrl = input.dataset.updateUrl;
                if (!updateUrl) return;

                const resp = await fetch(updateUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                    body: new URLSearchParams({ quantity: String(qty) }),
                    signal,
                });

                if (resp.ok) {
                    const data = await resp.json();
                    if (data && data.ok) {
                        clearError();
                        if (typeof data.quantity === 'number') input.value = String(data.quantity);
                        if (typeof data.itemSubtotal === 'number') setRowSubtotalAmount(input.dataset.itemKey, data.itemSubtotal);
                        if (typeof data.subtotal === 'number' && subtotalEl) subtotalEl.textContent = formatMoney(data.subtotal);
                        if (typeof data.totalQuantity === 'number' && totalQtyEl) totalQtyEl.textContent = String(data.totalQuantity);
                        return;
                    }
                }

                let message = 'Unable to update cart.';
                try {
                    const err = await resp.json();
                    if (err && (err.message || err.error)) message = err.message || err.error;
                    if (err && err.errors && err.errors.quantity && err.errors.quantity[0]) {
                        message = err.errors.quantity[0];
                    }
                } catch (_) {
                    // ignore parse errors
                }

                input.value = String(previousQty);
                setRowSubtotal(input.dataset.itemKey, input.dataset.unitPrice, previousQty);
                recalcTotalsFromInputs();
                showError(message);
            };

            for (const input of qtyInputs) {
                input.dataset.previousQty = String(parseInt(input.value, 10) || 1);

                let debounceTimer = null;
                let abortController = null;
                const queuePersist = () => {
                    const itemKey = input.dataset.itemKey;
                    const unitPrice = input.dataset.unitPrice;
                    const previousQty = parseInt(input.dataset.previousQty, 10) || 1;

                    let qty = parseInt(input.value, 10);
                    if (!Number.isFinite(qty) || qty < 1) qty = 1;
                    input.value = String(qty);

                    setRowSubtotal(itemKey, unitPrice, qty);
                    recalcTotalsFromInputs();

                    if (debounceTimer) clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        if (abortController) abortController.abort();
                        abortController = new AbortController();

                        persistQty(input, qty, previousQty, abortController.signal)
                            .then(() => {
                                input.dataset.previousQty = String(parseInt(input.value, 10) || 1);
                            })
                            .catch((e) => {
                                if (e && e.name === 'AbortError') return;
                                showError('Unable to update cart.');
                            });
                    }, 450);
                };

                input.addEventListener('input', queuePersist);
                input.addEventListener('change', queuePersist);
                input.addEventListener('blur', () => {
                    const qty = parseInt(input.value, 10);
                    if (!Number.isFinite(qty) || qty < 1) {
                        input.value = input.dataset.previousQty || '1';
                        setRowSubtotal(input.dataset.itemKey, input.dataset.unitPrice, input.value);
                        recalcTotalsFromInputs();
                    }
                });
            }
        })();
    </script>
@endsection
