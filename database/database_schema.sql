CREATE DATABASE IF NOT EXISTS foodbridge;
USE foodbridge;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE users (
  user_id             INT AUTO_INCREMENT PRIMARY KEY,
  role                ENUM('donor', 'receiver', 'admin') NOT NULL,
  full_name           VARCHAR(120) NOT NULL,
  email               VARCHAR(190) NOT NULL UNIQUE,
  profile_url         VARCHAR(500) NULL,
  password_hash       VARCHAR(255) NOT NULL,
  location            VARCHAR(255) NULL,
  latitude            DECIMAL(10, 8) NULL,
  longitude           DECIMAL(11, 8) NULL,
  trust_score         INT NOT NULL DEFAULT 100,
  total_food_donated  INT NOT NULL DEFAULT 0,
  status              ENUM('pending_verification', 'active', 'warned', 'suspended', 'banned') NOT NULL DEFAULT 'pending_verification',
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- OTP_VERIFICATIONS
-- ============================================================
CREATE TABLE otp_verifications (
  otp_id     INT AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(190) NOT NULL,
  otp_hash   VARCHAR(255) NOT NULL,
  purpose    ENUM('registration', 'password_reset', 'email_change') NOT NULL DEFAULT 'registration',
  expires_at DATETIME NOT NULL,
  status     ENUM('pending', 'used', 'expired') NOT NULL DEFAULT 'pending'
);

CREATE INDEX idx_otp_email_purpose ON otp_verifications (email, purpose);
CREATE INDEX idx_otp_expiry ON otp_verifications (expires_at);

-- ============================================================
-- DONATIONS
-- ============================================================
CREATE TABLE donations (
  donation_id    INT AUTO_INCREMENT PRIMARY KEY,
  donor_id       INT NOT NULL,
  food_name      VARCHAR(150) NOT NULL,
  category       ENUM('cookedMeal', 'rawProduce', 'bakery', 'beverages', 'cannedGoods', 'others') NOT NULL,
  quantity       DECIMAL(10, 2) NOT NULL,
  unit           ENUM('portions', 'kg', 'pieces') NOT NULL,
  image_url      VARCHAR(500) NULL,
  pickup_address VARCHAR(255) NOT NULL,
  expiry_at      DATETIME NOT NULL,
  status         ENUM('active', 'completed', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
  qr_token_hash  VARCHAR(255) NULL,
  CONSTRAINT fk_donation_donor
    FOREIGN KEY (donor_id) REFERENCES users(user_id)
    ON DELETE RESTRICT
);

CREATE INDEX idx_donation_status_expiry ON donations (status, expiry_at);

-- ============================================================
-- DONATION_ALLERGY_TAGS
-- ============================================================
CREATE TABLE donation_allergy_tags (
  donation_id  INT NOT NULL,
  allergy_name ENUM('nuts', 'dairy', 'gluten', 'shellfish', 'eggs', 'soy', 'vegan-safe', 'none') NOT NULL,
  PRIMARY KEY (donation_id, allergy_name),
  CONSTRAINT fk_donation_allergy_donation
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id)
    ON DELETE CASCADE
);

-- ============================================================
-- PICKUP_SLOTS
-- ============================================================
CREATE TABLE pickup_slots (
  pickup_slot_id INT AUTO_INCREMENT PRIMARY KEY,
  donation_id    INT NOT NULL,
  timeslot       DATETIME NOT NULL,
  CONSTRAINT fk_pickup_slot_donation
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id)
    ON DELETE CASCADE
);

-- ============================================================
-- BOOKINGS
-- ============================================================
CREATE TABLE bookings (
  booking_id     INT AUTO_INCREMENT PRIMARY KEY,
  donation_id    INT NOT NULL,
  pickup_slot_id INT NOT NULL,
  receiver_id    INT NOT NULL,
  booking_time   DATETIME NOT NULL,
  quantity       DECIMAL(10, 2) NOT NULL,
  status         ENUM('reserved', 'collected', 'cancelled', 'missed') NOT NULL DEFAULT 'reserved',
  CONSTRAINT fk_booking_donation
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_booking_slot
    FOREIGN KEY (pickup_slot_id) REFERENCES pickup_slots(pickup_slot_id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_booking_receiver
    FOREIGN KEY (receiver_id) REFERENCES users(user_id)
    ON DELETE RESTRICT
);

CREATE INDEX idx_booking_receiver_status ON bookings (receiver_id, status);

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  title           VARCHAR(160) NOT NULL,
  description     TEXT NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE
);

CREATE INDEX idx_notification_user_date ON notifications (user_id, created_at);

-- ============================================================
-- TRUST_SCORE_LOG
-- ============================================================
CREATE TABLE trust_score_log (
  trust_score_log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id            INT NOT NULL,
  description        VARCHAR(255) NOT NULL,
  points_change      INT NOT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_trust_score_log_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE
);

CREATE INDEX idx_trust_score_log_user ON trust_score_log (user_id);

-- ============================================================
-- VOUCHERS
-- ============================================================
CREATE TABLE vouchers (
  voucher_id         INT AUTO_INCREMENT PRIMARY KEY,
  brand_name         VARCHAR(120) NOT NULL,
  reward_title       VARCHAR(160) NOT NULL,
  voucher_code       VARCHAR(80) NOT NULL UNIQUE,
  required_donations INT NOT NULL DEFAULT 0,
  expiration_date    DATETIME NOT NULL
);

-- ============================================================
-- VOUCHER_REDEMPTIONS
-- ============================================================
CREATE TABLE voucher_redemptions (
  redemption_id INT AUTO_INCREMENT PRIMARY KEY,
  voucher_id    INT NOT NULL,
  donor_id      INT NOT NULL,
  status        ENUM('locked', 'unlocked', 'redeemed') NOT NULL DEFAULT 'locked',
  CONSTRAINT fk_redemption_voucher
    FOREIGN KEY (voucher_id) REFERENCES vouchers(voucher_id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_redemption_donor
    FOREIGN KEY (donor_id) REFERENCES users(user_id)
    ON DELETE RESTRICT
);

-- ============================================================
-- CERTIFICATES
-- ============================================================
CREATE TABLE certificates (
  certificate_id             INT AUTO_INCREMENT PRIMARY KEY,
  donor_id                   INT NOT NULL,
  certificate_name           VARCHAR(180) NOT NULL,
  issued_by                  VARCHAR(120) NOT NULL,
  period_start               DATETIME NOT NULL,
  period_end                 DATETIME NOT NULL,
  food_donated_count         INT NOT NULL DEFAULT 0,
  receiver_satisfaction_rate ENUM('Excellent', 'Good', 'Average', 'Poor') NOT NULL DEFAULT 'Good',
  file_url                   VARCHAR(500) NULL,
  CONSTRAINT fk_certificate_donor
    FOREIGN KEY (donor_id) REFERENCES users(user_id)
    ON DELETE CASCADE
);

-- ============================================================
-- REVIEWS
-- ============================================================
CREATE TABLE reviews (
  review_id        INT AUTO_INCREMENT PRIMARY KEY,
  booking_id       INT NOT NULL UNIQUE,
  rating           INT NOT NULL,
  comment          TEXT NULL,
  review_image_url VARCHAR(500) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_review_booking
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
    ON DELETE CASCADE
);

-- ============================================================
-- REPORTS
-- ============================================================
CREATE TABLE reports (
  report_id          INT AUTO_INCREMENT PRIMARY KEY,
  booking_id         INT NOT NULL,
  issue_type         VARCHAR(80) NOT NULL,
  comment            TEXT NULL,
  evidence_image_url VARCHAR(500) NULL,
  admin_message      TEXT NULL,
  status             ENUM('active', 'resolved', 'dismissed') NOT NULL DEFAULT 'active',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_report_booking
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
    ON DELETE CASCADE
);

CREATE INDEX idx_report_status ON reports (status, created_at);

-- ============================================================
-- PLATFORM_SETTINGS
-- ============================================================
CREATE TABLE platform_settings (
  maintenance_mode ENUM('on', 'off') NOT NULL PRIMARY KEY
);

-- ============================================================
-- TRUST RULE SETTINGS
-- ============================================================
CREATE TABLE trust_rule_settings (
  setting_id            TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  suspension_threshold  TINYINT UNSIGNED NOT NULL DEFAULT 30,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHECK (setting_id = 1),
  CHECK (suspension_threshold BETWEEN 0 AND 100)
);

-- ============================================================
-- EXPIRY REMINDER LOG
-- ============================================================
CREATE TABLE expiry_reminder_log (
    donation_id INT NOT NULL,
    reminder_time ENUM('6h', '2h', '30m') NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (donation_id, reminder_time),
    CONSTRAINT fk_reminder_donation FOREIGN KEY (donation_id)
        REFERENCES donations(donation_id) ON DELETE CASCADE
);

INSERT INTO trust_rule_settings (setting_id, suspension_threshold) VALUES (1, 30);
