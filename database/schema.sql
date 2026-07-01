CREATE DATABASE IF NOT EXISTS foodbridge
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE foodbridge;

-- ============================================================
-- USERS
-- Stores every account: donor, receiver, and admin.
-- trust_score  = behavioural score (0-100), starts at 100.
-- reward_points = points donors accumulate to redeem vouchers.
-- status       = lifecycle state of the account.
-- email_verified_at = NULL until the user verifies their email via OTP.
-- ============================================================
CREATE TABLE users (
  user_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role           ENUM('donor', 'receiver', 'admin') NOT NULL,
  full_name      VARCHAR(120) NOT NULL,
  email          VARCHAR(190) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  location       VARCHAR(255) NULL,
  trust_score    INT UNSIGNED NOT NULL DEFAULT 100,
  reward_points  INT UNSIGNED NOT NULL DEFAULT 0,
  status         ENUM('pending_verification', 'active', 'warned', 'suspended', 'banned') NOT NULL DEFAULT 'pending_verification',
  email_verified_at DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHECK (trust_score <= 100)
);

-- ============================================================
-- OTP_VERIFICATIONS
-- One-time passcodes sent to email for registration or
-- password reset. status tracks whether the OTP has been
-- consumed or is still pending.
-- ============================================================
CREATE TABLE otp_verifications (
  otp_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email     VARCHAR(190) NOT NULL,
  otp_hash  VARCHAR(255) NOT NULL,
  purpose   ENUM('registration', 'password_reset', 'email_change') NOT NULL DEFAULT 'registration',
  expires_at DATETIME NOT NULL,
  status    ENUM('pending', 'used', 'expired') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_otp_email_purpose (email, purpose),
  INDEX idx_otp_expiry (expires_at)
);

-- ============================================================
-- DONATIONS
-- A food listing created by a donor.
-- status tracks the lifecycle of the donation.
-- qr_token_hash is used to verify physical pickup via QR scan.
-- ============================================================
CREATE TABLE donations (
  donation_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donor_id       INT UNSIGNED NOT NULL,
  food_name      VARCHAR(150) NOT NULL,
  category       ENUM('cookedMeal', 'rawProduce', 'bakery', 'beverages', 'cannedGoods', 'others') NOT NULL,
  quantity       DECIMAL(10, 2) NOT NULL,
  unit           ENUM('portions', 'kg', 'pieces') NOT NULL,
  image_url      VARCHAR(500) NULL,
  pickup_address VARCHAR(255) NOT NULL,
  expiry_at      DATETIME NOT NULL,
  status         ENUM('active', 'completed', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
  qr_token_hash  VARCHAR(255) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_donation_donor
    FOREIGN KEY (donor_id) REFERENCES users(user_id)
    ON DELETE RESTRICT,
  CHECK (quantity > 0),
  INDEX idx_donation_status_expiry (status, expiry_at)
);

-- ============================================================
-- DONATION_ALLERGY_TAGS
-- Many-to-many: each donation can carry multiple allergy labels.
-- Composite PK ensures each tag appears at most once per donation.
-- ============================================================
CREATE TABLE donation_allergy_tags (
  donation_id  INT UNSIGNED NOT NULL,
  allergy_name ENUM('Nuts', 'Dairy', 'Gluten', 'Shellfish', 'Eggs', 'Soy', 'Vegan Safe', 'None'),
  PRIMARY KEY (donation_id, allergy_name),
  CONSTRAINT fk_donation_allergy_donation
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id)
    ON DELETE CASCADE
);

-- ============================================================
-- PICKUP_SLOTS
-- Time windows the donor offers for collecting a donation.
-- status = available means no booking yet; reserved means taken.
-- ============================================================
CREATE TABLE pickup_slots (
  pickup_slot_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donation_id    INT UNSIGNED NOT NULL,
  slot_start_at  DATETIME NOT NULL,
  slot_end_at    DATETIME NOT NULL,
  status         ENUM('available', 'reserved') NOT NULL DEFAULT 'available',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pickup_slot_donation
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id)
    ON DELETE CASCADE,
  INDEX idx_pickup_slot_status_time (status, slot_start_at)
);

-- ============================================================
-- BOOKINGS
-- A receiver reserving a specific pickup slot for a donation.
-- status tracks whether the food was collected, missed, or cancelled.
-- ============================================================
CREATE TABLE bookings (
  booking_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donation_id    INT UNSIGNED NOT NULL,
  pickup_slot_id INT UNSIGNED NOT NULL UNIQUE,
  receiver_id    INT UNSIGNED NOT NULL,
  status         ENUM('reserved', 'collected', 'cancelled', 'missed') NOT NULL DEFAULT 'reserved',
  booked_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at   DATETIME NULL,
  collected_at   DATETIME NULL,
  qr_verified_at DATETIME NULL,
  CONSTRAINT fk_booking_donation
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_booking_slot
    FOREIGN KEY (pickup_slot_id) REFERENCES pickup_slots(pickup_slot_id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_booking_receiver
    FOREIGN KEY (receiver_id) REFERENCES users(user_id)
    ON DELETE RESTRICT,
  INDEX idx_booking_receiver_status (receiver_id, status)
);

-- ============================================================
-- NOTIFICATIONS
-- In-app messages delivered to a specific user.
-- created_at is used to order and display notifications chronologically.
-- ============================================================
CREATE TABLE notifications (
  notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  title           VARCHAR(160) NOT NULL,
  description     TEXT NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  INDEX idx_notification_user_date (user_id, created_at)
);

-- ============================================================
-- TRUST_SCORE_LOG
-- Audit trail of every trust score change for a user.
-- description = the event type that triggered the change.
-- points_change = positive means gained, negative means lost.
-- reason = human-readable explanation stored for admin review.
-- ============================================================
CREATE TABLE trust_score_log (
  trust_score_log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id            INT UNSIGNED NOT NULL,
  description        ENUM('successful_donation', 'high_quality_review', 'helpful_review', 'late_cancellation', 'missed_pickup', 'unsafe_food_report', 'admin_adjustment') NOT NULL,
  points_change      INT NOT NULL,
  reason             VARCHAR(255) NOT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_trust_score_log_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  INDEX idx_trust_score_log_user (user_id)
);

-- ============================================================
-- VOUCHERS
-- Rewards created by admin that donors can redeem with points.
-- points_required = how many reward_points the donor must spend.
-- valid_until = the date after which the voucher can no longer be used.
-- ============================================================
CREATE TABLE vouchers (
  voucher_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_name    VARCHAR(120) NOT NULL,
  reward_title    VARCHAR(160) NOT NULL,
  voucher_code    VARCHAR(80) NOT NULL UNIQUE,
  points_required INT UNSIGNED NOT NULL DEFAULT 0,
  valid_until     DATE NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- VOUCHER_REDEMPTIONS
-- Records when a donor spends reward points on a voucher.
-- redeemed_at = the exact timestamp the redemption was submitted.
-- ============================================================
CREATE TABLE voucher_redemptions (
  redemption_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voucher_id    INT UNSIGNED NOT NULL,
  donor_id      INT UNSIGNED NOT NULL,
  redeemed_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_redemption_voucher
    FOREIGN KEY (voucher_id) REFERENCES vouchers(voucher_id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_redemption_donor
    FOREIGN KEY (donor_id) REFERENCES users(user_id)
    ON DELETE RESTRICT,
  INDEX idx_redemption_donor (donor_id, redeemed_at)
);

-- ============================================================
-- CERTIFICATES
-- Digital certificates awarded to donors for their contribution.
-- certificate_type = the category of recognition.
-- meals_saved = number of meals estimated from completed donations.
-- file_url = path to the generated PDF/image certificate file.
-- ============================================================
CREATE TABLE certificates (
  certificate_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donor_id         INT UNSIGNED NOT NULL,
  certificate_type ENUM('carbon_offset', 'csr', 'donation_impact') NOT NULL,
  title            VARCHAR(180) NOT NULL,
  meals_saved      INT UNSIGNED NOT NULL DEFAULT 0,
  file_url         VARCHAR(500) NULL,
  issued_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_certificate_donor
    FOREIGN KEY (donor_id) REFERENCES users(user_id)
    ON DELETE CASCADE
);

-- ============================================================
-- REVIEWS
-- A receiver's feedback after successfully collecting a donation.
-- One review per booking (UNIQUE on booking_id).
-- rating = 1 to 5 stars.
-- ============================================================
CREATE TABLE reviews (
  review_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT UNSIGNED NOT NULL UNIQUE,
  receiver_id INT UNSIGNED NOT NULL,
  rating      INT UNSIGNED NOT NULL,
  comment     TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_review_booking
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
    ON DELETE CASCADE,
  CONSTRAINT fk_review_receiver
    FOREIGN KEY (receiver_id) REFERENCES users(user_id)
    ON DELETE RESTRICT,
  CHECK (rating BETWEEN 1 AND 5)
);

-- ============================================================
-- REPORTS
-- Complaints filed by any user about a donation or another user.
-- booking_id / donation_id provide context for the incident.
-- reporter_id = who filed it; reported_user_id = who is accused.
-- status tracks admin review progress.
-- ============================================================
CREATE TABLE reports (
  report_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id         INT UNSIGNED NULL,
  donation_id        INT UNSIGNED NULL,
  reporter_id        INT UNSIGNED NOT NULL,
  reported_user_id   INT UNSIGNED NULL,
  issue_type         VARCHAR(80) NOT NULL,
  evidence_image_url VARCHAR(500) NULL,
  status             ENUM('active', 'resolved', 'dismissed') NOT NULL DEFAULT 'active',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_report_booking
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
    ON DELETE SET NULL,
  CONSTRAINT fk_report_donation
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id)
    ON DELETE SET NULL,
  CONSTRAINT fk_report_reporter
    FOREIGN KEY (reporter_id) REFERENCES users(user_id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_report_reported_user
    FOREIGN KEY (reported_user_id) REFERENCES users(user_id)
    ON DELETE SET NULL,
  INDEX idx_report_status (status, created_at)
);

-- ============================================================
-- PLATFORM_SETTINGS
-- A single-row table that stores the global maintenance mode flag.
-- maintenance_mode = 'on' means the platform is under maintenance;
--                   'off' means the platform is operating normally.
-- Only one row exists; the ENUM value itself is the primary key.
-- ============================================================
CREATE TABLE platform_settings (
  maintenance_mode ENUM('on', 'off') NOT NULL PRIMARY KEY
);

-- Seed the single platform settings row.
INSERT INTO platform_settings (maintenance_mode) VALUES ('off');
