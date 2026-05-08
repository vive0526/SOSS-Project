# Shipment & cancellation rules: deploy + manual test

## Deploy sequence (Step 7)

1. Deploy backend changes first.
   - This prevents bypassing rules even if someone submits requests manually.
2. Run the manual tests below (at least on staging).
3. Deploy UI changes next.
   - This reduces staff confusion by hiding/locking fields that will be rejected anyway.

## Manual test checklist (Step 6)

### Setup (recommended)

- Use one `staff` user and one `admin` user.
- Prepare an order in each state:
  - Order A: `shipment_status=pending`
  - Order B: `shipment_status=shipped`
  - Use `payment_method=cash_on_delivery` or ensure payment is verified, so shipment transitions are allowed.

### Shipment status transitions (staff)

1. On Order A (`pending`), try to set Shipment Status to `delivered`.
   - Expected: rejected, shipment_status stays `pending`.
2. On Order B (`shipped`), try to set Shipment Status back to `pending`.
   - Expected: rejected, shipment_status stays `shipped`.
3. On Order B (`shipped`), set Shipment Status to `delivered`.
   - Expected: allowed, shipment_status becomes `delivered`.

### Shipping details locked after shipped (staff vs admin)

4. On Order B (`shipped`), login as `staff` and try to change:
   - tracking number, shipping address, shipping phone, etc.
   - Expected: rejected; values remain unchanged.
5. On Order B (`shipped`), login as `admin` and change:
   - tracking number and/or shipping address.
   - Expected: allowed; values are saved.

### Cancellation after shipping starts (staff + admin)

6. On Order B (`shipped`), try to cancel as `staff`.
   - Expected: rejected (cannot cancel after shipment starts).
7. On Order B (`shipped`), try to cancel as `admin`.
   - Expected: rejected (cannot cancel after shipment starts).

## Automated tests (optional)

Run:

`php artisan test tests/Feature/OrderShipmentAndCancellationRulesTest.php`

Note:
- This test file is designed for the real MySQL prefixed-id schema.
- If your `phpunit.xml` uses SQLite `:memory:`, the tests will be skipped because the prefixed-key migrations are intentionally not applied in SQLite.
