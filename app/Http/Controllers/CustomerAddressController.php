<?php

namespace App\Http\Controllers;

use App\Models\CustomerAddress;
use App\Support\MalaysiaStates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerAddressController extends Controller
{
    private const MAX_ADDRESSES = 5;

    public function index(Request $request)
    {
        $user = $request->user();

        $addresses = CustomerAddress::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return view('customer.addresses.index', [
            'addresses' => $addresses,
            'stateOptions' => MalaysiaStates::options(),
            'maxAddresses' => self::MAX_ADDRESSES,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $count = CustomerAddress::query()->where('user_id', $user->getKey())->count();

        if ($count >= self::MAX_ADDRESSES) {
            return redirect()
                ->route('customer.addresses.index')
                ->withErrors(['address' => 'You have reached the maximum of ' . self::MAX_ADDRESSES . ' saved addresses.']);
        }

        return view('customer.addresses.form', [
            'address' => new CustomerAddress(),
            'stateOptions' => MalaysiaStates::options(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $count = CustomerAddress::query()->where('user_id', $user->getKey())->count();

        if ($count >= self::MAX_ADDRESSES) {
            return redirect()
                ->route('customer.addresses.index')
                ->withErrors(['address' => 'You have reached the maximum of ' . self::MAX_ADDRESSES . ' saved addresses.']);
        }

        $data = $this->validateAddress($request);

        DB::transaction(function () use ($user, $data, $count) {
            $makeDefault = (bool) ($data['is_default'] ?? false);
            if ($count === 0) {
                $makeDefault = true;
            }

            if ($makeDefault) {
                CustomerAddress::query()
                    ->where('user_id', $user->getKey())
                    ->update(['is_default' => false]);
            }

            CustomerAddress::create(array_merge($data, [
                'user_id' => $user->getKey(),
                'is_default' => $makeDefault,
            ]));
        });

        return redirect()
            ->route('customer.addresses.index')
            ->with('success', 'Address saved successfully.');
    }

    public function edit(Request $request, CustomerAddress $address)
    {
        $this->authorizeAddress($request, $address);

        return view('customer.addresses.form', [
            'address' => $address,
            'stateOptions' => MalaysiaStates::options(),
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, CustomerAddress $address)
    {
        $this->authorizeAddress($request, $address);

        $data = $this->validateAddress($request);

        DB::transaction(function () use ($address, $data) {
            $makeDefault = (bool) ($data['is_default'] ?? false);
            if ($makeDefault) {
                CustomerAddress::query()
                    ->where('user_id', $address->user_id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $address->update(array_merge($data, [
                'is_default' => $makeDefault ? true : $address->is_default,
            ]));

            // Ensure at least one default always exists.
            if (!CustomerAddress::query()->where('user_id', $address->user_id)->where('is_default', true)->exists()) {
                CustomerAddress::query()
                    ->where('user_id', $address->user_id)
                    ->orderByDesc('id')
                    ->limit(1)
                    ->update(['is_default' => true]);
            }
        });

        return redirect()
            ->route('customer.addresses.index')
            ->with('success', 'Address updated successfully.');
    }

    public function destroy(Request $request, CustomerAddress $address)
    {
        $this->authorizeAddress($request, $address);

        $count = CustomerAddress::query()->where('user_id', $address->user_id)->count();
        if ($count <= 1) {
            return redirect()
                ->route('customer.addresses.index')
                ->withErrors(['address' => 'You must keep at least one saved address.']);
        }

        DB::transaction(function () use ($address) {
            $wasDefault = (bool) $address->is_default;
            $userId = (string) $address->user_id;

            $address->delete();

            if ($wasDefault) {
                CustomerAddress::query()
                    ->where('user_id', $userId)
                    ->orderByDesc('id')
                    ->limit(1)
                    ->update(['is_default' => true]);
            }
        });

        return redirect()
            ->route('customer.addresses.index')
            ->with('success', 'Address deleted.');
    }

    public function setDefault(Request $request, CustomerAddress $address)
    {
        $this->authorizeAddress($request, $address);

        DB::transaction(function () use ($address) {
            CustomerAddress::query()
                ->where('user_id', $address->user_id)
                ->update(['is_default' => false]);

            $address->update(['is_default' => true]);
        });

        return redirect()
            ->route('customer.addresses.index')
            ->with('success', 'Default address updated.');
    }

    private function validateAddress(Request $request): array
    {
        $normalizedPhone = preg_replace('/[^\d\+]/', '', (string) $request->input('phone', ''));
        $request->merge(['phone' => $normalizedPhone]);

        return $request->validate([
            'label' => 'nullable|string|max:60',
            'recipient_name' => 'required|string|max:120',
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^(\\+?60|0)1[0-9]{8,9}$/',
            ],
            'address_line' => 'required|string|max:255',
            'city' => 'required|string|max:120',
            'state_key' => ['required', 'string', 'max:80', Rule::in(MalaysiaStates::keys())],
            'postcode' => 'required|string|max:30',
            'country' => 'required|string|max:120',
            'is_default' => 'nullable|boolean',
        ], [
            'phone.regex' => 'Please enter a valid Malaysian mobile number (e.g. 0123456789 or +60123456789).',
        ]);
    }

    private function authorizeAddress(Request $request, CustomerAddress $address): void
    {
        if ((string) $address->user_id !== (string) $request->user()->getKey()) {
            abort(403);
        }
    }
}

