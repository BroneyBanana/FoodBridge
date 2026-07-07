USE foodbridge;

-- ============================================================
-- 1. PLATFORM_SETTINGS
-- ============================================================
INSERT IGNORE INTO platform_settings (maintenance_mode) VALUES ('off');

-- ============================================================
-- 2. USERS
-- ============================================================
INSERT INTO users
  (user_id, role, full_name, email, password_hash, location,
   trust_score, total_food_donated, status, created_at)
VALUES
  -- Admin
  (1, 'admin',    'Daniel Ong',            'admin@foodbridge.com',         'ABC@123', 'Asia Pacific University, Kuala Lumpur', 100,   0, 'active', '2026-06-01 09:00:00'),
  -- Donors
  (2, 'donor',    'Sunrise Bakery',         'donor@food.com',               'ABC@123', 'Subang Jaya, Selangor',                100, 780, 'active', '2026-06-02 10:15:00'),
  (3, 'donor',    'Green Kitchen',          'green@foodbridge.com',          'ABC@123', 'Bukit Jalil, Kuala Lumpur',             92, 420, 'active', '2026-06-05 13:20:00'),
  -- Receivers
  (4, 'receiver', 'Daniel Receiver',        'receiver@food.com',            'ABC@123', 'Subang Jaya, Selangor',                 85,   0, 'active', '2026-06-03 11:00:00'),
  (5, 'receiver', 'Aisha Community Home',   'aisha.home@foodbridge.com',    'ABC@123', 'Puchong, Selangor',                     96,   0, 'active', '2026-06-07 08:45:00'),
  (6, 'receiver', 'Care Shelter KL',        'care.shelter@foodbridge.com',  'ABC@123', 'Kuala Lumpur City Centre',              65,   0, 'warned', '2026-06-10 15:25:00');

-- ============================================================
-- 3. OTP_VERIFICATIONS
-- ============================================================
INSERT INTO otp_verifications
  (otp_id, email, otp_hash, purpose, expires_at, status)
VALUES
  (1, 'donor@food.com',          '$2y$10$mockHashedOtp111111ForVerified000000000000000', 'registration',   '2026-06-02 10:25:00', 'used'),
  (2, 'receiver@food.com',       '$2y$10$mockHashedOtp222222ForVerified000000000000000', 'registration',   '2026-06-03 11:10:00', 'used'),
  (3, 'new.user@example.com',    '$2y$10$mockHashedOtp333333Pending0000000000000000000', 'registration',   '2026-07-07 16:10:00', 'pending'),
  (4, 'donor@food.com',          '$2y$10$mockHashedOtp444444ResetPending00000000000000', 'password_reset', '2026-07-07 21:30:00', 'pending'),
  (5, 'old.request@example.com', '$2y$10$mockHashedOtp555555Expired000000000000000000', 'registration',   '2026-06-15 12:00:00', 'expired');

-- ============================================================
-- 4. DONATIONS
-- ============================================================
INSERT INTO donations
  (donation_id, donor_id, food_name, category, quantity, unit, image_url,
   pickup_address, expiry_at, status, qr_token_hash)
VALUES
  (1, 2, 'Bread and Pastries',   'bakery',     40, 'pieces',   'uploads/donations/ape.jpg', 'Sunrise Bakery, SS15 Subang Jaya', '2026-06-12 20:00:00', 'completed', '$2y$10$mockQrHashDon1000000000000000000000000000000000'),
  (2, 2, 'Nasi Lemak Packs',     'cookedMeal', 25, 'portions', 'uploads/donations/ape.jpg', 'Sunrise Bakery, SS15 Subang Jaya', '2026-07-07 21:00:00', 'active',    '$2y$10$mockQrHashDon2000000000000000000000000000000000'),
  (3, 3, 'Vegetarian Rice Box',  'cookedMeal', 18, 'portions', 'uploads/donations/ape.jpg', 'Green Kitchen, Bukit Jalil',       '2026-07-07 19:30:00', 'active',    '$2y$10$mockQrHashDon3000000000000000000000000000000000'),
  (4, 3, 'Mixed Lunch Packs',    'cookedMeal', 12, 'portions', 'uploads/donations/ape.jpg', 'Green Kitchen, Bukit Jalil',       '2026-06-25 15:00:00', 'expired',   '$2y$10$mockQrHashDon4000000000000000000000000000000000');

-- ============================================================
-- 5. DONATION_ALLERGY_TAGS
-- ============================================================
INSERT INTO donation_allergy_tags (donation_id, allergy_name) VALUES
  (1, 'gluten'),
  (1, 'eggs'),
  (2, 'nuts'),
  (2, 'eggs'),
  (3, 'vegan-safe'),
  (4, 'none');

-- ============================================================
-- 6. PICKUP_SLOTS
-- ============================================================
INSERT INTO pickup_slots
  (pickup_slot_id, donation_id, timeslot)
VALUES
  (1, 1, '2026-06-12 18:00:00'),
  (2, 2, '2026-07-07 17:00:00'),
  (3, 2, '2026-07-07 18:00:00'),
  (4, 2, '2026-07-07 19:00:00'),
  (5, 3, '2026-07-07 17:30:00'),
  (6, 4, '2026-06-25 14:00:00');

-- ============================================================
-- 7. BOOKINGS
-- ============================================================
INSERT INTO bookings
  (booking_id, donation_id, pickup_slot_id, receiver_id, booking_time, quantity, status)
VALUES
  (1, 1, 1, 4, '2026-06-11 10:00:00', 1, 'collected'),
  (2, 3, 5, 5, '2026-07-06 09:30:00', 1, 'reserved'),
  (3, 4, 6, 4, '2026-06-24 11:15:00', 1, 'missed');

-- ============================================================
-- 8. NOTIFICATIONS
-- ============================================================
INSERT INTO notifications
  (user_id, title, description, created_at)
VALUES
  (2, 'Action Required: Food expiring soon',   'Your listed Nasi Lemak Packs will expire in 3 hours.', '2026-07-07 18:00:00'),
  (2, 'Donation Completed',                    'Fresh Bakery KL successfully collected your 20kg vegetables.',   '2026-06-12 18:12:00'),
  (2, 'New Voucher Unlocked',                  'You reached Trust Score 98! A new GrabFood voucher is waiting for you.', '2026-06-30 16:00:00'),
  (2, 'Community Update',                      'We rescued over 1,500kg of food this week thanks to heroes like you!', '2026-06-28 09:00:00'),
  (5, 'Pickup Slot Reserved',                  'Your Vegetarian Rice Box pickup is reserved for 5:30 PM.',                     '2026-07-07 10:05:00'),
  (4, 'Trust Score Deducted',                  '10 points were deducted because a pickup slot was missed.',                    '2026-06-25 15:05:00');

-- ============================================================
-- 9. TRUST_SCORE_LOG
-- ============================================================
INSERT INTO trust_score_log
  (trust_score_log_id, user_id, description, points_change, created_at)
VALUES
  (1,  2, 'Donation collected successfully',                        +5,  '2026-06-12 18:12:00'),
  (2,  2, 'High-quality review received',                           +3,  '2026-06-13 09:30:00'),
  (3,  4, 'Helpful review submitted',                               +2,  '2026-06-12 19:00:00'),
  (4,  4, 'Late cancellation (booking cancelled less than 30 min)', -5,  '2026-06-20 17:40:00'),
  (5,  4, 'Missed pickup warning (donor reported no-show)',         -10, '2026-06-25 15:05:00'),
  (6,  4, 'Admin adjustment',                                       -2,  '2026-06-26 10:00:00'),
  (7,  3, 'Admin adjustment',                                       -8,  '2026-06-28 11:20:00'),
  (8,  5, 'Admin adjustment',                                       -4,  '2026-06-29 14:10:00'),
  (9,  6, 'Missed pickup warning (donor reported no-show)',         -10, '2026-06-20 12:15:00'),
  (10, 6, 'Missed pickup warning (donor reported no-show)',         -10, '2026-06-22 13:25:00'),
  (11, 6, 'Missed pickup warning (donor reported no-show)',         -10, '2026-06-24 16:45:00'),
  (12, 6, 'Late cancellation (booking cancelled less than 30 min)', -5,  '2026-06-26 18:30:00');

-- ============================================================
-- 10. VOUCHERS
-- ============================================================
INSERT INTO vouchers
  (voucher_id, brand_name, reward_title, voucher_code, required_donations, expiration_date)
VALUES
  (1, 'GrabFood',   'RM10 Off Delivery', 'GRAB10FOOD', 250, '2026-12-31 23:59:59'),
  (2, 'Jaya Grocer','5% Off Bill',       'JAYAGR5OFF', 300, '2026-10-31 23:59:59'),
  (3, 'Tealive',    'Free Upsize',       'TEALIVEUP',  150, '2026-08-31 23:59:59'),
  (4, 'Foodpanda',  'RM5 Off',           'PANDA5RM',   200, '2026-11-30 23:59:59');

-- ============================================================
-- 11. VOUCHER_REDEMPTIONS
-- ============================================================
INSERT INTO voucher_redemptions
  (redemption_id, voucher_id, donor_id, status)
VALUES
  (1, 3, 2, 'redeemed'),
  (2, 1, 2, 'unlocked');

-- ============================================================
-- 12. CERTIFICATES
-- ============================================================
INSERT INTO certificates
  (certificate_id, donor_id, certificate_name, issued_by, period_start, period_end, food_donated_count, receiver_satisfaction_rate, file_url)
VALUES
  (1, 2, 'June Carbon Offset Certificate', 'FoodBridge Admin', '2026-06-01 00:00:00', '2026-06-30 23:59:59', 220, 'Excellent', 'uploads/certificates/ape.jpg'),
  (2, 2, 'CSR Food Rescue Certificate',    'FoodBridge Admin', '2026-06-01 00:00:00', '2026-06-30 23:59:59', 220, 'Excellent', 'uploads/certificates/ape.jpg'),
  (3, 3, 'Q2 Donation Impact Certificate', 'FoodBridge Admin', '2026-04-01 00:00:00', '2026-06-30 23:59:59', 95, 'Good', 'uploads/certificates/ape.jpg');

-- ============================================================
-- 13. REVIEWS
-- ============================================================
INSERT INTO reviews
  (review_id, booking_id, rating, comment, review_image_url, created_at)
VALUES
  (1, 1, 5, 'Food was packed well and collection was smooth. Highly recommend this donor.', 'uploads/reviews/ape.jpg', '2026-06-12 19:00:00');

-- ============================================================
-- 14. REPORTS
-- ============================================================
INSERT INTO reports
  (report_id, booking_id, issue_type, comment, evidence_image_url, status, created_at)
VALUES
  (1, 3, 'missed_pickup', 'The receiver never showed up to pick up the donation.', NULL, 'resolved', '2026-06-25 15:00:00'),
  (2, 1, 'food_quality',  'The food smelled stale when opened later.', 'uploads/reports/ape.jpg', 'active', '2026-06-26 12:20:00');

-- ============================================================
-- DEMO QUERIES (uncomment to run)
-- ============================================================
-- 1. View all users with their current trust score and status:
-- SELECT user_id, role, full_name, trust_score, total_food_donated, status FROM users ORDER BY role;

-- 2. View a receiver's full trust score history as displayed in the UI:
-- SELECT description, points_change, created_at
-- FROM trust_score_log WHERE user_id = 4 ORDER BY created_at DESC;

-- 3. View active donations and their available pickup slots:
-- SELECT d.food_name, d.status AS donation_status, s.timeslot
-- FROM donations d JOIN pickup_slots s ON s.donation_id = d.donation_id ORDER BY d.donation_id, s.timeslot;

-- 4. View bookings:
-- SELECT b.booking_id, d.food_name, u.full_name AS receiver,
--        b.quantity, b.status
-- FROM bookings b JOIN donations d ON d.donation_id = b.donation_id JOIN users u ON u.user_id = b.receiver_id;

-- 5. View allergy tags per donation:
-- SELECT d.food_name, GROUP_CONCAT(t.allergy_name ORDER BY t.allergy_name SEPARATOR ', ') AS tags
-- FROM donations d JOIN donation_allergy_tags t ON d.donation_id = t.donation_id GROUP BY d.donation_id;

-- 6. View voucher redemption history per donor:
-- SELECT u.full_name, v.reward_title, v.brand_name, r.status
-- FROM voucher_redemptions r JOIN users u ON u.user_id = r.donor_id JOIN vouchers v ON v.voucher_id = r.voucher_id;

-- 7. View certificates issued per donor:
-- SELECT u.full_name, c.certificate_name, c.issued_by, c.food_donated_count, c.receiver_satisfaction_rate
-- FROM certificates c JOIN users u ON u.user_id = c.donor_id ORDER BY c.period_start;

-- 8. View all active reports:
-- SELECT r.report_id, r.issue_type, r.status
-- FROM reports r
-- WHERE r.status = 'active';
