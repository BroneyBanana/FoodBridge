---
config:
  theme: base
  layout: elk
---
erDiagram
    USERS {
        INT user_id PK
        ENUM role
        VARCHAR full_name
        VARCHAR email
        VARCHAR password_hash
        VARCHAR location
        DECIMAL latitude
        DECIMAL longitude
        INT trust_score
        INT total_food_donated
        ENUM status
        DATETIME created_at
    }
    OTP_VERIFICATIONS {
        INT otp_id PK
        VARCHAR email
        VARCHAR otp_hash
        ENUM purpose
        DATETIME expires_at
        ENUM status
    }

    DONATIONS {
        INT donation_id PK
        INT donor_id FK
        VARCHAR food_name
        ENUM category
        DECIMAL quantity
        ENUM unit
        VARCHAR image_url
        VARCHAR pickup_address
        DATETIME expiry_at
        ENUM status
        VARCHAR qr_token_hash
    }

    DONATION_ALLERGY_TAGS {
        INT donation_id PK, FK
        ENUM allergy_name FK
    }

    PICKUP_SLOTS {
        INT pickup_slot_id PK
        INT donation_id FK
        DATETIME timeslot
    }

    BOOKINGS {
        INT booking_id PK
        INT donation_id FK
        INT pickup_slot_id FK
        INT receiver_id FK
        DATETIME booking_time
        DECIMAL quantity
        ENUM status
    }

    NOTIFICATIONS {
        INT notification_id PK
        INT user_id FK
        VARCHAR title
        TEXT description
        DATETIME created_at
    }

    TRUST_SCORE_LOG {
        INT trust_score_log_id PK
        INT user_id FK
        VARCHAR description
        INT points_change
        DATETIME created_at
    }

    VOUCHERS {
        INT voucher_id PK
        VARCHAR brand_name
        VARCHAR reward_title
        VARCHAR voucher_code
        INT required_donations
        DATETIME expiration_date
    }

    VOUCHER_REDEMPTIONS {
        INT redemption_id PK
        INT voucher_id FK
        INT donor_id FK
        ENUM status "locked, unlocked, redeemed"
    }

    CERTIFICATES {
        INT certificate_id PK
        INT donor_id FK
        VARCHAR certificate_name
        VARCHAR issued_by
        DATETIME period_start
        DATETIME period_end
        INT food_donated_count
        ENUM receiver_satisfaction_rate
        VARCHAR file_url
    }

    REVIEWS {
        INT review_id PK
        INT booking_id FK
        INT rating
        TEXT comment
        VARCHAR review_image_url
        DATETIME created_at
    }

    REPORTS {
        INT report_id PK
        INT booking_id FK
        VARCHAR issue_type
        TEXT comment
        VARCHAR evidence_image_url
        ENUM status
        DATETIME created_at
    }

    PLATFORM_SETTINGS {
        ENUM maintenance_mode PK
    }

    USERS ||--o{ DONATIONS : publishes
    DONATIONS ||--o{ DONATION_ALLERGY_TAGS : has
    DONATIONS ||--o{ PICKUP_SLOTS : has
    DONATIONS ||--o{ BOOKINGS : booked_for
    PICKUP_SLOTS ||--o| BOOKINGS : selected
    USERS ||--o{ BOOKINGS : books
    USERS ||--o{ NOTIFICATIONS : receives
    USERS ||--o{ TRUST_SCORE_LOG : has_score_logs
    VOUCHERS ||--o{ VOUCHER_REDEMPTIONS : redeemed
    USERS ||--o{ VOUCHER_REDEMPTIONS : redeems
    USERS ||--o{ CERTIFICATES : earns
    BOOKINGS ||--o| REVIEWS : reviewed_after
    BOOKINGS ||--o{ REPORTS : reported_from

