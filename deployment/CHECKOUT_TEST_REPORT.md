# Checkout test report

## Fix in this branch

Checkout display and order submission now calculate the total and shipping from the server-side session cart instead of trusting posted hidden totals or the client-written `cart_totals` session value. A coupon is invalidated when the cart changes, including changes that leave the subtotal unchanged. Coupon values stored in the session use ungrouped decimal strings; legacy grouped values are handled by the calculator.

## Local results

Using PHP 8.4 and an isolated MariaDB staging test database with catalog data and synthetic customers:

- Cart add and checkout page: passed.
- Submitting a forged total of 0.01 for a cart totaling 15,000 stored 15,000, not the forged value; the synthetic order and order item were removed afterward.
- Total calculation checks passed for paid/free shipping, coupon values over 1,000, and coupon invalidation when cart contents change at the same subtotal. Run `php deployment/test-checkout-totals.php`.
- Order and item inserts now use a transaction. With both tables using InnoDB, an intentionally rejected item insert rolled back the order; retrying after removing the test trigger created one order and one item. Synthetic records were removed.
- Repeated adds beyond available stock were rejected; a product-specific coupon matched the product in a hashed cart key.
- A coupon disabled after application and a product whose catalog price changed were both rejected at checkout. An active product coupon produced a correct synthetic order; test records and catalog changes were reverted.
- PHP syntax checks passed for changed files.

## Limitations

This is not a full security or commerce audit. Price, quantity and coupon checks at submission do not reserve inventory or prevent concurrent sales; inventory and concurrency need a dedicated transactional design. Transaction rollback requires transactional tables such as InnoDB; verify the host's schema. Tax, payment integrations, CSRF, admin authorization, upload security and email delivery need further tests. Hostinger deployment, rewrite behavior and live staging have not been validated.
