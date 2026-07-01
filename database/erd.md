erDiagram
    USERS {
        INT user_id PK
        ENUM role
        VARCHAR full_name
        VARCHAR email
        VARCHAR password_hash
        VARCHAR location
        INT trust_score
        INT reward_points
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
        ENUM allergy_name PK
    }

    PICKUP_SLOTS {
        INT pickup_slot_id PK
        INT donation_id FK
        DATETIME slot_start_at
        DATETIME slot_end_at
        ENUM status
    }

    BOOKINGS {
        INT booking_id PK
        INT donation_id FK
        INT pickup_slot_id FK
        INT receiver_id FK
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
    }

    VOUCHERS {
        INT voucher_id PK
        VARCHAR partner_name
        VARCHAR reward_title
        VARCHAR voucher_code
        INT points_required
        DATE valid_until
    }

    VOUCHER_REDEMPTIONS {
        INT redemption_id PK
        INT voucher_id FK
        INT donor_id FK
        DATETIME redeemed_at
    }

    CERTIFICATES {
        INT certificate_id PK
        INT donor_id FK
        ENUM certificate_type
        VARCHAR title
        INT meals_saved
        VARCHAR file_url
    }

    REVIEWS {
        INT review_id PK
        INT booking_id FK
        INT receiver_id FK
        INT rating
        TEXT comment
    }

    REPORTS {
        INT report_id PK
        INT booking_id FK
        INT donation_id FK
        INT reporter_id FK
        INT reported_user_id FK
        VARCHAR issue_type
        VARCHAR evidence_image_url
        ENUM status
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
    USERS ||--o{ REVIEWS : receiver_writes
    BOOKINGS ||--o{ REPORTS : reported_from
    DONATIONS ||--o{ REPORTS : reported_from
    USERS ||--o{ REPORTS : submits
