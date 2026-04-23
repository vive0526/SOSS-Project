@extends('layouts.customer')

@section('title', 'Request Purchase')
@section('page_title', 'Request Purchase')
@section('page_subtitle', 'Submit a purchase request for cattle products')

@section('content')
    @php
        $user = auth()->user();
        $minDate = now()->toDateString();
    @endphp

    @if(session('success'))
        <div class="customer-alert">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="customer-alert customer-alert--error">
            <div class="customer-alert__title">Please check the highlighted fields.</div>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="cr-shell">
        <aside class="cr-summary">
            <div class="cr-summary__kicker">Request summary</div>
            <div class="cr-summary__title">{{ $product->name }}</div>
            <div class="cr-summary__meta">
                <div><strong>Customer:</strong> {{ $user?->name ?? '-' }}</div>
                <div><strong>Email:</strong> {{ $user?->email ?? '-' }}</div>
                <div>
                    <strong>Stock available:</strong>
                    {{ $product->stock_quantity > 0 ? $product->stock_quantity . ' unit(s)' : 'Out of stock' }}
                </div>
            </div>

            <div class="cr-summary__note">
                Submit a purchase request and our staff will review it. We’ll contact you to confirm availability and next steps.
            </div>

            <div class="cr-summary__actions">
                <a class="btn btn-outline" href="{{ route('customer.products.show', $product) }}">Back to product</a>
            </div>
        </aside>

        <section class="customer-card cr-form-card">
            <div class="cr-form-head">
                <div>
                    <h3 style="margin:0;">Cattle Purchase Request</h3>
                    <div class="cr-form-sub">Complete the form below. Required fields are marked.</div>
                </div>
                <div class="cr-steps" aria-label="Steps">
                    <div class="cr-step is-active">Details</div>
                    <div class="cr-step">Review</div>
                    <div class="cr-step">Submit</div>
                </div>
            </div>

            <form method="POST" action="{{ route('customer.cattle-requests.store') }}" class="customer-form cr-form" data-validate>
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->product_id }}">

                <div class="customer-form__row">
                    <div class="cr-field {{ $errors->has('phone') ? 'is-invalid' : '' }}">
                        <label for="phone">Phone Number <span class="cr-required">*</span></label>
                        <input type="text"
                               id="phone"
                               name="phone"
                               value="{{ old('phone', $user?->phone ?? '') }}"
                               autocomplete="tel"
                               placeholder="e.g. 0123456789"
                               required>
                        @error('phone')
                            <div class="cr-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="cr-field {{ $errors->has('quantity') ? 'is-invalid' : '' }}">
                        <label for="quantity">Quantity Requested <span class="cr-required">*</span></label>
                        <input type="number"
                               id="quantity"
                               name="quantity"
                               min="1"
                               max="{{ $product->stock_quantity }}"
                               value="{{ old('quantity', 1) }}"
                               required>
                        <div class="cr-help">Max: {{ max(0, (int) $product->stock_quantity) }} unit(s)</div>
                        @error('quantity')
                            <div class="cr-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="customer-form__row">
                    <div class="cr-field {{ $errors->has('purpose') ? 'is-invalid' : '' }}">
                        <label for="purpose">Purpose <span class="cr-required">*</span></label>
                        <select id="purpose" name="purpose" required>
                            <option value="">Select purpose</option>
                            <option value="breeding" {{ old('purpose') === 'breeding' ? 'selected' : '' }}>Breeding</option>
                            <option value="slaughter" {{ old('purpose') === 'slaughter' ? 'selected' : '' }}>Slaughter</option>
                            <option value="others" {{ old('purpose') === 'others' ? 'selected' : '' }}>Others</option>
                        </select>
                        @error('purpose')
                            <div class="cr-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="cr-field {{ $errors->has('preferred_date') ? 'is-invalid' : '' }}">
                        <label for="preferred_date">Preferred Date <span class="cr-required">*</span></label>
                        <input type="date"
                               id="preferred_date"
                               name="preferred_date"
                               min="{{ $minDate }}"
                               value="{{ old('preferred_date') }}"
                               required>
                        <div class="cr-help">Choose a date from today onward.</div>
                        @error('preferred_date')
                            <div class="cr-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="cr-field {{ $errors->has('notes') ? 'is-invalid' : '' }}">
                    <label for="notes">Notes <span class="cr-optional">Optional</span></label>
                    <textarea id="notes" name="notes" rows="4" placeholder="Any special requests or details…">{{ old('notes') }}</textarea>
                    <div class="cr-help">Max 2000 characters.</div>
                    @error('notes')
                        <div class="cr-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="cr-actions">
                    <button type="submit" class="btn btn-primary" {{ $product->stock_quantity > 0 ? '' : 'disabled' }}>
                        Submit request
                    </button>
                    <a class="btn btn-outline" href="{{ route('customer.products.show', $product) }}">Cancel</a>
                </div>
            </form>
        </section>
    </div>

    <script>
        (function () {
            const form = document.querySelector('[data-validate]');
            if (!form) return;

            const fieldSelector = '.cr-field input, .cr-field select, .cr-field textarea';
            const inputs = Array.from(form.querySelectorAll(fieldSelector));

            const fieldRoot = (el) => el.closest('.cr-field');
            const setInvalid = (el, invalid) => {
                const root = fieldRoot(el);
                if (!root) return;
                root.classList.toggle('is-invalid', invalid);
                if (invalid) {
                    root.classList.remove('cr-shake');
                    // restart animation
                    void root.offsetWidth;
                    root.classList.add('cr-shake');
                }
            };

            const validateInput = (el) => {
                if (!el.willValidate) return true;
                const ok = el.checkValidity();
                setInvalid(el, !ok);
                return ok;
            };

            for (const el of inputs) {
                el.addEventListener('input', () => validateInput(el));
                el.addEventListener('blur', () => validateInput(el));
            }

            form.addEventListener('submit', (e) => {
                let firstInvalid = null;
                for (const el of inputs) {
                    const ok = validateInput(el);
                    if (!ok && !firstInvalid) firstInvalid = el;
                }

                if (firstInvalid) {
                    e.preventDefault();
                    firstInvalid.focus({ preventScroll: true });
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        })();
    </script>
@endsection
