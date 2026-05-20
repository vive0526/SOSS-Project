# SOSS — End-to-End (E2E) Manual Test Script (Real End-User Flow)

Date: 2026-05-20

This document is written as if **I am a real end user**, and I’m walking through the system “start to end” across all roles/modules.

---

## 0) Scope (what “every module” means here)

This script covers these functional modules observed in `routes/web.php` and `routes/auth.php`:

- Public landing (`/`)
- Authentication: register/login/logout + password reset + email verification
- Role dashboards: admin/staff/customer
- Profile: update profile + change password
- Admin-only settings:
  - User management (`/users/*`)
  - Regions (`/regions/*`)
  - Operating Units (`/operating-units/*`)
  - Companies (`/companies/*`)
  - Codes (`/codes/*`)
  - Admin notifications (create/send)
- Admin & Staff:
  - Products (CRUD via `Route::resource('products', ...)`)
  - Categories (CRUD)
  - Product inventory listing (`/products/inventory`)
  - Orders: view + status/shipment/payment actions
  - Cattle Requests: approve/reject/complete
- Inventory module:
  - Admin: edit stock + view history
  - Admin/Staff: adjust stock + inventory reports
- Customer module:
  - Updates feed
  - Notifications: view/read/read-all + dismiss profile prompt
  - Catalog: browse products + view stock + product details
  - Cart: add/update/remove
  - Checkout: place order + processing screen
  - Stripe checkout: start/cancel/success callbacks
  - Discounts: list + claim coupon
  - Customer orders: view + cancel (subject to rules)
  - Customer cattle requests: create/view/list
- Stripe backend:
  - Webhook endpoint (`POST /stripe/webhook`)
  - Refund action (admin-only, triggered from order)
- Reports:
  - Order summary report (admin-only)
- Shipment & cancellation rules (documented separately in `docs/deploy_shipment_cancellation_rules.md`)

---

## 1) Test environment & accounts

### 1.1 Prerequisites

- App is running locally or on staging.
- Database is migrated.
- Queue worker is running if your notifications/async jobs depend on it.
- Mail settings are configured if you want to test email verification flows using actual email.
- For Stripe flows:
  - Stripe keys are set in `.env` (test mode recommended).
  - You have a Stripe test card ready (e.g., `4242 4242 4242 4242`) for a happy-path payment test.

### 1.2 Known seeded user (from `database/seeders/DatabaseSeeder.php`)

- Admin:
  - Email: `vivethan@soss.com`
  - Password: `vivi1234`
  - Role: `admin`
  - Status: `active`

### 1.3 Test accounts you must create (recommended)

Create these via the UI (so we exercise the User module) unless you already have them:

- Staff user:
  - Role: `staff`
  - Status: `active`
  - Email verification: not required (by design)
- Customer user:
  - Role: `customer`
  - Status: `active`
  - Email verification: required (by design)

If your system already has a “staff create user” or “admin create user” screen, use that instead of DB inserts.

### 1.4 Optional: one-command reset for repeatable E2E runs (local only)

If you are running locally and it’s OK to wipe data:

- Reset DB + re-seed the admin user:
  - `php artisan migrate:fresh --seed`

Then login with:

- `vivethan@soss.com` / `vivi1234`

---

## 2) Test data setup (minimal data to enable end-to-end flows)

You need these objects before a customer can do a full purchase flow:

- At least 1 Category
- At least 2 Products:
  - Product P1: in stock (stock - reserved > 0) and purchasable
  - Product P2: out of stock (stock - reserved <= 0) to validate stock rules
- Optional but recommended:
  - At least 1 Coupon (to test customer discount claiming)

Admin-only settings you may need depending on your validations:

- Regions
- Operating Units
- Companies
- Codes

---

## 3) Global “as an end user” rules for executing this script

For every test below, capture:

1) URL visited  
2) Action taken (click/type/submit)  
3) What you expected  
4) What actually happened (including error messages)  

If anything fails, also capture:

- Screenshot of the error
- Exact time
- Which user/role was logged in

---

## 4) E2E journey A — Public → Register → Verify → Customer dashboard

### A1 — Landing page works (Public)

1. Visit `/` (not logged in).
2. Expected:
   - Page loads without errors.
   - Featured products appear (if any exist and have available stock).
   - No “auth required” redirects.

### A2 — Register as a new customer (Guest)

1. Visit `/register`.
2. Register a new user with:
   - name: `E2E Customer`
   - email: `e2e.customer+<date>@example.com` (unique)
   - password: strong password
3. Expected:
   - Registration succeeds.
   - You are logged in (or prompted to verify email, depending on implementation).

### A3 — Email verification (Customer)

1. Attempt to open `/customer/dashboard`.
2. Expected:
   - If email is not verified: you are redirected to the verification notice flow (`/verify-email`).
3. Complete verification:
   - If using real email: click the verification link.
   - If testing locally without email: you can verify in DB, but note this bypasses a true end-user path.
4. Re-visit `/customer/dashboard`.
5. Expected:
   - Customer dashboard loads.
   - Featured products, collections, counts show (may be 0 on fresh DB).

---

## 5) E2E journey B — Admin “system setup” (settings + master data)

Login as admin:

1. Visit `/login`
2. Login using `vivethan@soss.com` / `vivi1234`
3. Expected redirect: `/admin/dashboard`

### B1 — Admin profile page

1. Visit `/admin/profile`
2. Expected: page loads, profile UI visible.

### B2 — Regions (admin-only)

1. Visit `/regions`
2. Create a Region (example: `Central`).
3. Edit it (change name).
4. Deactivate it.
5. Reactivate it.
6. Expected:
   - All actions succeed.
   - Deactivated items behave consistently in dropdowns (if used elsewhere).

### B3 — Operating Units (admin-only)

Repeat the same lifecycle test as Regions:

1. `/operating-units` → create → edit → deactivate → activate

### B4 — Companies (admin-only)

Repeat:

1. `/companies` → create → edit → deactivate → activate

### B5 — Codes (admin-only)

Repeat:

1. `/codes` → create → edit → deactivate → activate

### B6 — User management (admin-only)

1. Visit `/users`
2. Create:
   - 1 staff user (`E2E Staff`)
   - 1 customer user (`E2E Customer 2`) (optional)
3. Edit user details.
4. Deactivate a user, confirm they can’t access role routes.
5. Reactivate the user, confirm access returns.
6. Expected:
   - Staff can access `/staff/dashboard`
   - Customer can access `/customer/dashboard` (after verification if required)

### B7 — Admin creates a notification (admin-only)

1. Visit `/admin/notifications/create`
2. Create a notification intended for customers.
3. Expected:
   - Notification is stored/sent (depending on your implementation).

---

## 6) E2E journey C — Products & categories (admin/staff)

### C1 — Categories CRUD (admin or staff)

Login as staff (preferred) to validate staff permissions; fallback to admin if you don’t have staff yet.

1. Visit `/categories`
2. Create Category: `Fresh Meat`
3. Edit it (rename).
4. Delete it (only if safe; otherwise keep it).
5. Expected:
   - List updates correctly.
   - Deletion is blocked if it would break product relationships (if enforced).

### C2 — Products CRUD (admin or staff)

1. Visit `/products`
2. Create Product P1:
   - Name: `E2E Product In Stock`
   - Category: `Fresh Meat`
   - Price: set a value
   - Stock: set > 0 (or via Inventory module if product form doesn’t include stock)
3. Create Product P2:
   - Name: `E2E Product Out Of Stock`
   - Stock: set to 0
4. Edit P1 (change price/name).
5. Expected:
   - P1 appears on customer catalog.
   - P2 appears but shows out-of-stock behavior (depending on UI).

### C3 — Product inventory listing (admin/staff)

1. Visit `/products/inventory`
2. Expected:
   - Stock levels visible.
   - P1 shows available stock > 0
   - P2 shows available stock <= 0

---

## 7) E2E journey D — Inventory management (admin + staff)

### D1 — Admin edits stock directly (admin-only)

Login as admin.

1. Visit `/inventory/products/{product}/edit` for P1
2. Increase stock by a known amount (e.g., +10)
3. Save.
4. Expected:
   - Stock updates.
   - Customer stock endpoint `/customer/products/{product}/stock` reflects change.

### D2 — Inventory adjustment (admin/staff)

Login as staff.

1. Visit `/inventory/products/{product}/adjust` for P1
2. Create an adjustment:
   - Add stock (+5) or reduce stock (-2) with reason (if required)
3. Expected:
   - Stock changes correctly.
   - Adjustment appears in history (admin sees in `/inventory/history`).

### D3 — Inventory reports (admin/staff)

1. Visit:
   - `/inventory/reports/levels`
   - `/inventory/reports/low-stock`
   - `/inventory/reports/movements`
2. Expected:
   - Pages load.
   - Report data includes P1/P2 in appropriate sections.

### D4 — Inventory history (admin-only)

Login as admin.

1. Visit `/inventory/history`
2. Expected:
   - Recent adjustments visible with timestamps and product references.

---

## 8) E2E journey E — Customer shopping: catalog → cart → checkout → order

Login as customer (verified + active).

### E1 — Customer updates & notifications

1. Visit `/customer/updates`
2. Visit `/customer/notifications`
3. If admin created a notification, confirm it appears.
4. Click “read” on one notification (if UI exists) or trigger:
   - `POST /customer/notifications/{notificationId}/read`
5. Click “read all”:
   - `POST /customer/notifications/read-all`
6. Expected:
   - Unread → read status updates correctly.

### E2 — Customer profile completeness gate (active_user / checkout profile prompt)

1. Go to `/customer/cart` and `/customer/checkout`
2. Expected:
   - If shipping/phone fields are missing, the system prompts to complete profile.
3. Go to `/profile` and fill:
   - phone
   - shipping address + city/state/postcode/country
4. Return to `/customer/checkout`
5. Expected:
   - Checkout is accessible without “complete profile” blocking.

### E3 — Browse products & stock checks

1. Visit `/customer/products`
2. Open P1 details: `/customer/products/{product}`
3. Open stock endpoint (or UI stock view): `/customer/products/{product}/stock`
4. Expected:
   - P1 shows in-stock.
5. Open P2 details and stock:
6. Expected:
   - P2 shows out-of-stock and cannot be added to cart (or is prevented at checkout).

### E4 — Cart operations

1. Add P1 to cart:
   - Use UI or `POST /customer/cart/add`
2. Visit `/customer/cart`
3. Update quantity:
   - `POST /customer/cart/{itemKey}/update`
4. Remove item:
   - `POST /customer/cart/{itemKey}/remove`
5. Expected:
   - Totals update correctly.
   - Quantity cannot exceed available stock (if enforced).

### E5 — Checkout place order

1. Add P1 to cart again with a valid quantity.
2. Visit `/customer/checkout`
3. Place order:
   - `POST /customer/checkout`
4. Expected:
   - Order created.
   - Redirect to processing page: `/customer/checkout/processing/{order}`
   - Order appears in `/customer/orders`

### E6 — Stripe checkout (if enabled)

This validates the Stripe UI flow wiring.

1. From the order/processing screen, start Stripe:
   - `GET /customer/checkout/stripe/{order}`
2. Complete a Stripe test payment.
3. Expected:
   - Redirect to `/customer/checkout/stripe/success`
   - Order payment status updates (as designed).

Also validate cancel path:

1. Start Stripe again and cancel.
2. Expected:
   - Redirect to `/customer/checkout/stripe/cancel/{order}`
   - Order remains unpaid/pending (as designed).

---

## 9) E2E journey F — Customer order lifecycle + cancellation rules

### F1 — Customer views orders

1. Visit `/customer/orders`
2. Open the order: `/customer/orders/{order}`
3. Expected: order details match cart/checkout selections.

### F2 — Customer cancels order (allowed window)

1. Attempt: `PATCH /customer/orders/{order}/cancel` (via UI)
2. Expected:
   - Cancel allowed only if shipment has not started (implementation rule).

If cancellation is rejected:

- Verify the error message is user-friendly and the order state remains unchanged.

---

## 10) E2E journey G — Staff/admin order operations (processing the customer’s order)

Login as staff (or admin where specified).

### G1 — Staff views order list + details

1. Visit `/orders`
2. Open the customer order: `/orders/{order}`
3. Expected:
   - Staff can view order.
   - Staff sees shipment/payment action controls (if provided).

### G2 — Payment verification (staff/admin)

1. Trigger verify payment:
   - `PATCH /orders/{order}/verify-payment`
2. Expected:
   - Payment becomes verified (or status changes).

### G3 — Shipment status & details (staff/admin) — MUST follow documented rules

Use the checklist in `docs/deploy_shipment_cancellation_rules.md` and ensure results match.

At minimum verify:

1. Invalid shipment status transitions are rejected.
2. Shipping details are locked for staff after shipped (admin may override if allowed).
3. Cancellation after shipment starts is rejected for both staff and admin.

### G4 — Assign order (admin-only)

Login as admin.

1. Assign order:
   - `PATCH /orders/{order}/assign`
2. Expected:
   - Assigned staff field updates and is visible on the order.

### G5 — Order reports summary (admin-only)

Login as admin.

1. Visit `/reports/orders/summary`
2. Expected:
   - Page loads and includes the test order.

---

## 11) E2E journey H — Discounts & coupons (customer + admin/staff)

### H1 — Customer views discounts

Login as customer.

1. Visit `/customer/discounts`
2. Expected:
   - Coupon list visible (if you created any coupons).

### H2 — Customer claims a coupon

1. Claim:
   - `POST /customer/discounts/claim/{coupon}`
2. Expected:
   - Coupon marked as claimed for the customer.
   - Coupon cannot be claimed twice (if enforced).

### H3 — Coupon applied to a new checkout (if implemented)

1. Put items in cart and go through checkout again.
2. Expected:
   - Discount affects order totals (if implemented).

---

## 12) E2E journey I — Cattle request workflow (customer → staff/admin)

### I1 — Customer creates a cattle request

Login as customer.

1. Visit `/customer/cattle-requests`
2. Start request for a product:
   - `GET /customer/cattle-requests/{product}/create`
3. Submit:
   - `POST /customer/cattle-requests`
4. Expected:
   - Request appears in `/customer/cattle-requests`
   - Customer can open request details:
     - `/customer/cattle-requests/{cattleRequest}`

### I2 — Staff/admin processes the cattle request

Login as staff.

1. Visit `/cattle-requests`
2. Open request: `/cattle-requests/{cattleRequest}`
3. Approve:
   - `PATCH /cattle-requests/{cattleRequest}/approve`
4. Or reject:
   - `PATCH /cattle-requests/{cattleRequest}/reject`
5. If approved, complete it later:
   - `PATCH /cattle-requests/{cattleRequest}/complete`
6. Expected:
   - Status changes are reflected in staff list and customer detail page.

---

## 13) E2E journey J — Refunds + Stripe webhook (admin)

This depends on your Stripe integration setup.

### J1 — Refund from admin order screen (admin-only)

Login as admin.

1. From `/orders/{order}`, trigger refund:
   - `POST /orders/{order}/refund/stripe`
2. Expected:
   - Refund record created (if tracked).
   - Order state updates accordingly.

### J2 — Stripe webhook endpoint receives events (backend)

This is typically validated by Stripe sending events or by using Stripe CLI.

1. Confirm `POST /stripe/webhook` returns success on valid Stripe events.
2. Expected:
   - No 500 errors.
   - Event updates order/payment records as designed.

---

## 14) Security/permissions regression (quick but important)

For each route group below, verify a user without permission is blocked (403 or redirected):

- Customer trying to access:
  - `/users`, `/regions`, `/companies`, `/codes`, `/orders`
- Staff trying to access admin-only:
  - `/admin/profile`, `/reports/orders/summary`, `/orders/{order}/assign`
- Guest trying to access any authenticated route:
  - `/profile`, `/customer/*`, `/orders`, `/inventory/*`

Expected:

- No sensitive data leaks (no partial page with data).
- Correct redirect to `/login` or 403, consistent across screens.

---

## 15) Completion criteria (definition of “pass”)

You can consider the system “E2E tested” when:

- All journeys A → J execute without unhandled errors.
- Role permissions behave as expected (no unauthorized access).
- At least one real order is created by a customer and processed by staff/admin.
- Shipment/cancellation rules match `docs/deploy_shipment_cancellation_rules.md`.

---

## Appendix A — Optional automated checks (sanity)

These are not a replacement for the end-user E2E script, but they help catch regressions quickly.

- Run the test suite:
  - `composer run test`
- Run the shipment/cancellation rule test directly (if present):
  - `php artisan test tests/Feature/OrderShipmentAndCancellationRulesTest.php`
