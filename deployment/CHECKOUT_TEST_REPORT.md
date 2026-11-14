# Checkout test report

## Fix in this branch

Checkout display and order submission now calculate the total and shipping from the server-side session cart instead of trusting posted hidden totals or the client-written `cart_totals` session value. A coupon is invalidated when the cart changes, including changes that leave the subtotal unchanged. Coupon values stored in the session use ungrouped decimal strings; legacy grouped values are handled by the calculator.

## Local results

Using PHP 8.4 and an isolated MariaDB staging test database with catalog data and synthetic customers:

- Cart add and checkout page: passed.
- Submitting a forged total of 0.01 for a cart totaling 15,000 stored 15,000, not the forged value; the synthetic order and order item were removed afterward.
- Total calculation checks passed for paid/free shipping, coupon values over 1,000, and coupon invalidation when cart contents change at the same subtotal. Run `php deployment/test-checkout-totals.php`.
- PHP syntax checks passed for changed files.

## Limitations

This is not a full security or commerce audit. Session cart prices can become stale relative to the catalog, coupons are not revalidated against the database at order submission, and order/item creation is not fully transactional. Inventory, concurrent orders, tax, payment integrations, CSRF, admin authorization, upload security and email delivery need further tests. Hostinger deployment, rewrite behavior and live staging have not been validated.
