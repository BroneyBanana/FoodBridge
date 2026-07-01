USE foodbridge;

-- ============================================================
-- 1. PLATFORM_SETTINGS
-- Single-row table. 'off' = platform is live, 'on' = maintenance.
-- ============================================================
INSERT IGNORE INTO platform_settings (maintenance_mode) VALUES ('off');

-- ============================================================
-- 2. USERS
-- role    : donor, receiver, admin
-- status  : pending_verification → active → warned → suspended → banned
-- trust_score starts at 100 and changes based on behaviour.
-- reward_points are earned by donors and spent on vouchers.
-- ============================================================
INSERT INTO users
  (user_id, role, full_name, email, password_hash, location,
   trust_score, reward_points, status, email_verified_at)
VALUES
  -- Admin
  (1, 'admin',    'Daniel Ong',            'admin@foodbridge.com',         '$2y$10$mockAdminHash0000000000000000000000000000000000000000', 'Asia Pacific University, Kuala Lumpur', 100,   0, 'active', '2026-06-01 09:00:00'),
  -- Donors
  (2, 'donor',    'Sunrise Bakery',         'donor@food.com',               '$2y$10$mockDonorHash0000000000000000000000000000000000000000', 'Subang Jaya, Selangor',                100, 780, 'active', '2026-06-02 10:15:00'),
  (3, 'donor',    'Green Kitchen',          'green@foodbridge.com',          '$2y$10$mockGreenHash0000000000000000000000000000000000000000', 'Bukit Jalil, Kuala Lumpur',             92, 420, 'active', '2026-06-05 13:20:00'),
  -- Receivers
  (4, 'receiver', 'Daniel Receiver',        'receiver@food.com',            '$2y$10$mockReceiverHash000000000000000000000000000000000000', 'Subang Jaya, Selangor',                 85,   0, 'active', '2026-06-03 11:00:00'),
  (5, 'receiver', 'Aisha Community Home',   'aisha.home@foodbridge.com',    '$2y$10$mockAishaHash00000000000000000000000000000000000000', 'Puchong, Selangor',                     96,   0, 'active', '2026-06-07 08:45:00'),
  (6, 'receiver', 'Care Shelter KL',        'care.shelter@foodbridge.com',  '$2y$10$mockShelterHash0000000000000000000000000000000000000', 'Kuala Lumpur City Centre',              65,   0, 'warned', '2026-06-10 15:25:00');

-- ============================================================
-- 3. OTP_VERIFICATIONS
-- purpose : registration | password_reset | email_change
-- status  : pending = not yet used  |  used = verified  |  expired = timed out
-- ============================================================
INSERT INTO otp_verifications
  (otp_id, email, otp_hash, purpose, expires_at, status)
VALUES
  -- Donor registered and verified successfully
  (1, 'donor@food.com',       '$2y$10$mockHashedOtp111111ForVerified000000000000000', 'registration',    '2026-06-02 10:25:00', 'used'),
  -- Receiver registered and verified successfully
  (2, 'receiver@food.com',    '$2y$10$mockHashedOtp222222ForVerified000000000000000', 'registration',    '2026-06-03 11:10:00', 'used'),
  -- A new user who requested registration OTP but has not verified yet
  (3, 'new.user@example.com', '$2y$10$mockHashedOtp333333Pending0000000000000000000', 'registration',    '2026-07-01 16:10:00', 'pending'),
  -- Donor requested a password reset (OTP is pending use)
  (4, 'donor@food.com',       '$2y$10$mockHashedOtp444444ResetPending00000000000000', 'password_reset',  '2026-07-01 21:30:00', 'pending'),
  -- An expired OTP that was never used
  (5, 'old.request@example.com', '$2y$10$mockHashedOtp555555Expired000000000000000', 'registration',    '2026-06-15 12:00:00', 'expired');

-- ============================================================
-- 4. DONATIONS
-- category : cookedMeal | rawProduce | bakery | beverages | cannedGoods | others
-- unit     : portions | kg | pieces
-- status   : active → booked → completed | expired | cancelled | flagged
-- qr_token_hash is generated when a booking is confirmed; scanned at pickup.
-- ============================================================
INSERT INTO donations
  (donation_id, donor_id, food_name, category, quantity, unit, image_url,
   pickup_address, expiry_at, status, qr_token_hash, created_at)
VALUES
  -- Completed: collected by Daniel Receiver
  (1, 2, 'Bread and Pastries',   'bakery',     40, 'pieces',   '../../assets/images/food2.jpg',       'Sunrise Bakery, SS15 Subang Jaya', '2026-06-12 20:00:00', 'completed', '$2y$10$mockQrHashDon1000000000000000000000000000000000', '2026-06-12 08:00:00'),
  -- Active: still open for booking
  (2, 2, 'Nasi Lemak Packs',     'cookedMeal', 25, 'portions', '../../assets/images/food1.jpg',       'Sunrise Bakery, SS15 Subang Jaya', '2026-07-01 21:00:00', 'active',    '$2y$10$mockQrHashDon2000000000000000000000000000000000', '2026-07-01 09:00:00'),
  -- Booked: Aisha Community Home has reserved a slot
  (3, 3, 'Vegetarian Rice Box',  'cookedMeal', 18, 'portions', '../../assets/images/vegetarian.png',  'Green Kitchen, Bukit Jalil',       '2026-07-01 19:30:00', 'booked',    '$2y$10$mockQrHashDon3000000000000000000000000000000000', '2026-07-01 08:30:00'),
  -- Expired: pickup slot was missed by receiver
  (4, 3, 'Mixed Lunch Packs',    'cookedMeal', 12, 'portions', '../../assets/images/food3.jpeg',      'Green Kitchen, Bukit Jalil',       '2026-06-25 15:00:00', 'expired',   '$2y$10$mockQrHashDon4000000000000000000000000000000000', '2026-06-25 09:00:00');

-- ============================================================
-- 5. DONATION_ALLERGY_TAGS
-- Composite PK (donation_id, allergy_name) prevents duplicates.
-- allergy_name : Nuts | Dairy | Gluten | Shellfish | Eggs | Soy | Vegan Safe | None
-- ============================================================
INSERT INTO donation_allergy_tags (donation_id, allergy_name) VALUES
  (1, 'Gluten'),
  (1, 'Eggs'),
  (2, 'Nuts'),
  (2, 'Eggs'),
  (3, 'Vegan Safe'),
  (4, 'None');

-- ============================================================
-- 6. PICKUP_SLOTS
-- status : available = open for booking | reserved = slot already taken
-- Each donation can offer multiple time windows to suit receivers.
-- ============================================================
INSERT INTO pickup_slots
  (pickup_slot_id, donation_id, slot_start_at, slot_end_at, status)
VALUES
  -- Donation 1 (completed): slot was reserved then collected
  (1, 1, '2026-06-12 18:00:00', '2026-06-12 18:30:00', 'reserved'),
  -- Donation 2 (active): three open time windows
  (2, 2, '2026-07-01 17:00:00', '2026-07-01 17:30:00', 'available'),
  (3, 2, '2026-07-01 18:00:00', '2026-07-01 18:30:00', 'available'),
  (4, 2, '2026-07-01 19:00:00', '2026-07-01 19:30:00', 'available'),
  -- Donation 3 (booked): Aisha reserved this slot
  (5, 3, '2026-07-01 17:30:00', '2026-07-01 18:00:00', 'reserved'),
  -- Donation 4 (expired): slot was reserved but receiver missed it
  (6, 4, '2026-06-25 14:00:00', '2026-06-25 14:30:00', 'reserved');

-- ============================================================
-- 7. BOOKINGS
-- status : reserved = waiting for pickup
--          collected = receiver arrived and QR was scanned
--          missed    = receiver did not show up in time
--          cancelled = receiver cancelled before pickup
--          expired   = booking window closed without action
-- qr_verified_at is filled in only when the donor scans the QR at collection.
-- ============================================================
INSERT INTO bookings
  (booking_id, donation_id, pickup_slot_id, receiver_id, status,
   booked_at, cancelled_at, collected_at, qr_verified_at)
VALUES
  -- Booking 1: Daniel Receiver collected Bread and Pastries (QR scanned)
  (1, 1, 1, 4, 'collected', '2026-06-12 12:10:00', NULL,                  '2026-06-12 18:12:00', '2026-06-12 18:12:00'),
  -- Booking 2: Aisha reserved Vegetarian Rice Box, pending pickup
  (2, 3, 5, 5, 'reserved',  '2026-07-01 10:05:00', NULL,                  NULL,                  NULL),
  -- Booking 3: Daniel Receiver missed Mixed Lunch Packs
  (3, 4, 6, 4, 'missed',    '2026-06-25 10:15:00', NULL,                  NULL,                  NULL);

-- ============================================================
-- 8. NOTIFICATIONS
-- Delivered to individual users for events like expiry warnings,
-- collection confirmations, trust score changes, and announcements.
-- ============================================================
INSERT INTO notifications
  (user_id, title, description, created_at)
VALUES
  (2, 'Action Required: Food expiring soon',   'Your listed Nasi Lemak Packs will expire in 6 hours.',                         '2026-07-01 15:00:00'),
  (2, 'Donation Completed',                    'Daniel Receiver successfully collected your Bread and Pastries.',              '2026-06-12 18:12:00'),
  (2, 'New Voucher Unlocked',                  'You reached Trust Score 100. A new GrabFood voucher is waiting for you.',     '2026-06-30 16:00:00'),
  (2, 'Community Update',                      'FoodBridge rescued over 1,500 kg of food this week thanks to donors like you.','2026-06-28 09:00:00'),
  (5, 'Pickup Slot Reserved',                  'Your Vegetarian Rice Box pickup is reserved for 5:30 PM.',                    '2026-07-01 10:05:00'),
  (4, 'Trust Score Deducted',                  '10 points were deducted because a pickup slot was missed.',                   '2026-06-25 15:05:00');

-- ============================================================
-- 9. TRUST_SCORE_LOG
-- Records every trust score change event per user.
-- description = the event type (ENUM) | points_change = signed integer
-- Current trust_score in users table is the running total.
--
-- Score breakdown:
--   Sunrise Bakery (2):      100 + 5 (donation) + 3 (review) = 100 (capped)
--   Green Kitchen (3):       100 - 8 (admin)                  = 92
--   Daniel Receiver (4):     100 + 2 (review) - 5 (cancel) - 10 (missed) - 2 (admin) = 85
--   Aisha Community Home (5):100 - 4 (admin)                  = 96
--   Care Shelter KL (6):     100 - 10 - 10 - 10 - 5          = 65
-- ============================================================
INSERT INTO trust_score_log
  (trust_score_log_id, user_id, description, points_change, reason)
VALUES
  (1,  2, 'successful_donation', +5,  'Donation was collected successfully.'),
  (2,  2, 'high_quality_review', +3,  'Receiver gave a 5-star food quality review.'),
  (3,  4, 'helpful_review',      +2,  'Receiver submitted a helpful review after collection.'),
  (4,  4, 'late_cancellation',   -5,  'Receiver cancelled a booking less than 30 minutes before pickup.'),
  (5,  4, 'missed_pickup',       -10, 'Receiver missed the reserved pickup slot for Mixed Lunch Packs.'),
  (6,  4, 'admin_adjustment',    -2,  'Minor warning issued by admin after repeated late behaviour.'),
  (7,  3, 'admin_adjustment',    -8,  'Admin adjustment after food quality report review.'),
  (8,  5, 'admin_adjustment',    -4,  'Minor warning after a late arrival report.'),
  (9,  6, 'missed_pickup',       -10, 'First missed pickup warning.'),
  (10, 6, 'missed_pickup',       -10, 'Second missed pickup warning.'),
  (11, 6, 'missed_pickup',       -10, 'Third missed pickup warning.'),
  (12, 6, 'late_cancellation',   -5,  'Late cancellation after repeated missed pickups.');

-- ============================================================
-- 10. VOUCHERS
-- Created by admin; donors redeem them using reward_points.
-- points_required = the cost in reward points.
-- valid_until = expiry date of the voucher offer.
-- ============================================================
INSERT INTO vouchers
  (voucher_id, partner_name, reward_title, voucher_code, points_required, valid_until)
VALUES
  (1, 'GrabFood',  'RM10 Off Delivery', 'GRAB10FOOD', 250, '2026-12-31'),
  (2, 'Jaya Grocer','5% Off Bill',      'JAYAGR5OFF', 300, '2026-10-31'),
  (3, 'Tealive',   'Free Upsize',       'TEALIVEUP',  150, '2026-08-31'),
  (4, 'Foodpanda', 'RM5 Off',           'PANDA5RM',   200, '2026-11-30');

-- ============================================================
-- 11. VOUCHER_REDEMPTIONS
-- Records when a donor redeemed a voucher using reward_points.
-- redeemed_at = exact timestamp the donor submitted the redemption.
-- ============================================================
INSERT INTO voucher_redemptions
  (redemption_id, voucher_id, donor_id, redeemed_at)
VALUES
  (1, 3, 2, '2026-06-20 13:30:00'),
  (2, 1, 2, '2026-06-30 16:00:00');

-- ============================================================
-- 12. CERTIFICATES
-- Digital recognition issued to donors.
-- certificate_type : carbon_offset | csr | donation_impact
-- meals_saved = estimated meals from the donor's completed donations.
-- file_url = path to the generated certificate PDF.
-- ============================================================
INSERT INTO certificates
  (certificate_id, donor_id, certificate_type, title, meals_saved, file_url, issued_at)
VALUES
  (1, 2, 'carbon_offset',   'June Carbon Offset Certificate', 220, 'certificates/sunrise-june-carbon.pdf', '2026-06-30 18:00:00'),
  (2, 2, 'csr',             'CSR Food Rescue Certificate',    220, 'certificates/sunrise-june-csr.pdf',    '2026-06-30 18:05:00'),
  (3, 3, 'donation_impact', 'Q2 Donation Impact Certificate',  95, 'certificates/greenkitchen-q2.pdf',     '2026-06-30 18:10:00');

-- ============================================================
-- 13. REVIEWS
-- Written by the receiver after a successful (collected) booking.
-- One review per booking (UNIQUE constraint on booking_id).
-- rating : 1 (poor) to 5 (excellent).
-- ============================================================
INSERT INTO reviews
  (review_id, booking_id, receiver_id, rating, comment, created_at)
VALUES
  (1, 1, 4, 5, 'Food was packed well and collection was smooth. Highly recommend this donor.', '2026-06-12 19:00:00');

-- ============================================================
-- 14. REPORTS
-- Filed by any user about an incident related to a donation or booking.
-- reporter_id = who submitted the report.
-- reported_user_id = the user being accused (can be NULL if reporting a donation only).
-- status : open → reviewing → resolved | dismissed
-- ============================================================
INSERT INTO reports
  (report_id, booking_id, donation_id, reporter_id, reported_user_id,
   issue_type, evidence_image_url, status, created_at)
VALUES
  -- Donor reported Daniel Receiver for missing the pickup slot
  (1, 3, 4, 3, 4, 'missed_pickup',   NULL,                              'resolved',  '2026-06-25 15:00:00'),
  -- Daniel Receiver reported Green Kitchen for suspected stale food
  (2, NULL, 4, 4, 3, 'food_quality', 'reports/stale-pack-photo-1.jpg',  'reviewing', '2026-06-26 12:20:00');

-- ============================================================
-- DEMO QUERIES (uncomment to run)
-- ============================================================
-- 1. View all users with their current trust score and status:
-- SELECT user_id, role, full_name, trust_score, reward_points, status FROM users ORDER BY role;

-- 2. View Daniel Receiver's full trust score timeline:
-- SELECT u.full_name, u.trust_score AS current_score, l.description, l.points_change, l.reason
-- FROM trust_score_log l JOIN users u ON u.user_id = l.user_id WHERE l.user_id = 4;

-- 3. View active donations and their available pickup slots:
-- SELECT d.food_name, d.status AS donation_status, s.slot_start_at, s.slot_end_at, s.status AS slot_status
-- FROM donations d JOIN pickup_slots s ON s.donation_id = d.donation_id ORDER BY d.donation_id, s.slot_start_at;

-- 4. View bookings with QR scan state:
-- SELECT b.booking_id, d.food_name, u.full_name AS receiver,
--        b.status, IF(b.qr_verified_at IS NULL, 'Not scanned', 'QR verified') AS qr_state
-- FROM bookings b JOIN donations d ON d.donation_id = b.donation_id JOIN users u ON u.user_id = b.receiver_id;

-- 5. View allergy tags per donation:
-- SELECT d.food_name, GROUP_CONCAT(t.allergy_name ORDER BY t.allergy_name SEPARATOR ', ') AS tags
-- FROM donations d JOIN donation_allergy_tags t ON t.donation_id = d.donation_id GROUP BY d.donation_id;

-- 6. View voucher redemption history per donor:
-- SELECT u.full_name, v.reward_title, v.partner_name, r.redeemed_at
-- FROM voucher_redemptions r JOIN users u ON u.user_id = r.donor_id JOIN vouchers v ON v.voucher_id = r.voucher_id;

-- 7. View certificates issued per donor:
-- SELECT u.full_name, c.certificate_type, c.title, c.meals_saved, c.issued_at
-- FROM certificates c JOIN users u ON u.user_id = c.donor_id ORDER BY c.issued_at;

-- 8. View all open/reviewing reports:
-- SELECT r.report_id, u_reporter.full_name AS reporter, u_reported.full_name AS accused,
--        r.issue_type, r.status
-- FROM reports r
-- JOIN users u_reporter ON u_reporter.user_id = r.reporter_id
-- LEFT JOIN users u_reported ON u_reported.user_id = r.reported_user_id
-- WHERE r.status IN ('open','reviewing');
