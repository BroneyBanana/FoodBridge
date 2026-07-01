# FoodBridge Database Design Notes

This database design is based on the current FoodBridge pages and the capstone proposal. The frontend currently stores mock data in `localStorage`, so the schema converts those browser-side objects into backend MySQL tables.

The schema is intentionally simplified so it is easier to understand and manage for the capstone project.

## Files

- `schema.sql`: creates the simplified FoodBridge MySQL database structure.
- `mock_data.sql`: inserts sample users, OTP records, donations, bookings, trust score logs, vouchers, reports, reviews, and certificates.
- `mock-data-coverage.md`: checklist showing that every table has mock data.
- `erd.md`: Mermaid ERD diagram for visualisation.
- `booking-status-explanation.md`: explains pickup slot status, booking status, and QR verification.
- `trust-rules-explanation.md`: deeper explanation of the trust score system.

## Main Design Choices

- `users` stores donor, receiver, and admin accounts in one table with role-based access. Passwords should be stored only as hashes in `password_hash`.
- `otp_verifications` supports the registration requirement where the system sends an OTP before activating the account. Store a hashed OTP, not the plain OTP.
- `donations`, `pickup_slots`, and `bookings` cover the core workflow: donor publishes food, receiver books a slot, then QR scan confirms pickup.
- `notifications` stores simple notification cards for a user, with title, description, and creation date.
- `donations` stores the food listing, category, image URL, pickup address, expiry, and QR token hash.
- `donation_allergy_tags` stores the fixed allergy checkbox values for each donation.
- `pickup_slots.status` only tracks whether a slot is `available` or `reserved`.
- `bookings.status` tracks the receiver booking result, such as `reserved`, `collected`, `cancelled`, or `missed`.
- QR verification is simplified: if `bookings.qr_verified_at` has a value, the QR scan succeeded.
- `reviews` stores the receiver review for a booking. The donor does not need to be repeated because it can be found through `booking -> donation -> donor_id`.
- `trust_score_log` records trust score changes in one simple log table. The current trust score is stored only in `users.trust_score`.
- `vouchers`, `voucher_redemptions`, and `certificates` support donor rewards, voucher redemption, and CSR/carbon impact certificates.
- `reviews` and `reports` support receiver feedback, issue reporting, and admin moderation.
- `platform_settings` stores system-level values such as maintenance mode and the automatic suspension trust threshold.

## Suggested Registration Flow

1. Insert a new `users` row with `status = 'pending_verification'`.
2. Generate a 6-digit OTP in PHP.
3. Hash the OTP and store it in `otp_verifications` with `purpose = 'registration'` and a short `expires_at` time.
4. Send the OTP to the user's email.
5. When the user submits the OTP, compare it with the stored hash.
6. If valid, set `users.email_verified_at = NOW()` and `users.status = 'active'`.

## Suggested Donation Flow

1. Insert the donation into `donations`, including image URL and core food details.
2. Insert selected fixed allergy tag names into `donation_allergy_tags`.
3. Insert one to three rows into `pickup_slots`.
4. Create expiry notification cards in `notifications` when needed.
5. When a receiver reserves a slot, insert `bookings` and update the slot status to `reserved`.
6. When QR pickup is scanned, set `bookings.qr_verified_at`, set `bookings.status = 'collected'`, update `users.trust_score`, and insert one `trust_score_log` row.

## How to Load the Mock Data

Run the schema first, then the mock data:

```sql
SOURCE database/schema.sql;
SOURCE database/mock_data.sql;
```

If your MySQL client is already inside `C:\Users\Acer\FoodBridge`, those paths should work. Otherwise, use the absolute file paths.
