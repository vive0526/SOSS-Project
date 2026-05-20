# SOSS — Smoke Test Checklist (10–20 minutes)

Use this when you need a quick “is the system basically working?” check after deploy.

## Accounts

- Admin: `vivethan@soss.com` / `vivi1234`
- Staff: create one active staff user (once)
- Customer: create one active + verified customer user (once)

## Smoke flow

1) Public landing
- Visit `/` → page loads, no errors.

2) Login + dashboards
- Admin login → `/admin/dashboard` loads.
- Staff login → `/staff/dashboard` loads.
- Customer login (verified) → `/customer/dashboard` loads.

3) Catalog + cart
- Customer: `/customer/products` loads.
- Open 1 product: `/customer/products/{product}` loads.
- Add to cart: `/customer/cart/add` works and `/customer/cart` shows item.

4) Checkout
- Customer: `/customer/checkout` loads.
- If profile incomplete: `/profile` update shipping fields; return to checkout.
- Place order: `POST /customer/checkout` creates order and redirects to processing.

5) Staff order view
- Staff: `/orders` shows the new order.
- Open `/orders/{order}` loads.

6) Shipment/cancellation rules (minimum)
- Follow `docs/deploy_shipment_cancellation_rules.md` and confirm the 3 core expectations:
  - Invalid shipment transitions rejected
  - Staff cannot edit shipping details after shipped (admin can if designed)
  - Cannot cancel after shipment starts

## Pass criteria

- No 500 errors during steps 1–6
- Order can be created by customer and viewed by staff
- Shipment rules behave as documented

