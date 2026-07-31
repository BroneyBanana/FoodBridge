-- ===========================================================
-- ============================================================
-- 2. Enable Event Scheduler (run once)
-- ============================================================
SET GLOBAL event_scheduler = ON;

-- ============================================================
-- 3. Create the expiry reminder log table (if not exists)
-- ============================================================
CREATE TABLE IF NOT EXISTS expiry_reminder_log (
    donation_id INT NOT NULL,
    reminder_time ENUM('6h', '2h', '30m') NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (donation_id, reminder_time),
    CONSTRAINT fk_reminder_donation FOREIGN KEY (donation_id)
        REFERENCES donations(donation_id) ON DELETE CASCADE
);

-- ============================================================
-- 4. Helper stored procedure to insert a notification
-- ============================================================
DELIMITER //

CREATE PROCEDURE create_notification(
    IN p_user_id INT,
    IN p_title VARCHAR(160),
    IN p_description TEXT
)
BEGIN
    INSERT INTO notifications (user_id, title, description)
    VALUES (p_user_id, p_title, p_description);
END //

DELIMITER ;

-- ============================================================
-- 5. Stored procedure for trust-score penalty
-- ============================================================
DELIMITER //

CREATE PROCEDURE penalise_missed_pickup(
    IN p_receiver_id INT,
    IN p_booking_id INT
)
BEGIN
    DECLARE penalty INT DEFAULT 10;
    DECLARE threshold INT;
    DECLARE current_score INT;

    SELECT suspension_threshold INTO threshold
    FROM trust_rule_settings
    WHERE setting_id = 1;

    SELECT trust_score INTO current_score
    FROM users
    WHERE user_id = p_receiver_id;

    UPDATE users
    SET trust_score = trust_score - penalty
    WHERE user_id = p_receiver_id;

    INSERT INTO trust_score_log (user_id, description, points_change)
    VALUES (
        p_receiver_id,
        CONCAT('Missed pickup for booking #', p_booking_id, ' – penalty applied'),
        -penalty
    );

    IF (current_score - penalty) < threshold THEN
        UPDATE users
        SET status = 'banned'
        WHERE user_id = p_receiver_id;
    END IF;
END //

DELIMITER ;

-- ============================================================
-- 6. All TRIGGERS
-- ============================================================

-- 6.1 After new booking (safe)
DELIMITER //

CREATE TRIGGER after_booking_insert
AFTER INSERT ON bookings
FOR EACH ROW
BEGIN
    DECLARE v_donor_id INT;
    DECLARE v_food_name VARCHAR(150);

    SELECT donor_id, food_name INTO v_donor_id, v_food_name
    FROM donations WHERE donation_id = NEW.donation_id;

    IF v_donor_id IS NOT NULL THEN
        CALL create_notification(
            v_donor_id,
            'New booking',
            CONCAT('A receiver booked your donation "', v_food_name, '".')
        );
    END IF;

    CALL create_notification(
        NEW.receiver_id,
        'Booking confirmed',
        CONCAT('Your booking for "', IFNULL(v_food_name, 'item'), '" is confirmed. Please pick it up on time.')
    );
END //

DELIMITER ;

-- 6.2 After booking status update (includes total_food_donated + safe notifications)
DELIMITER //

CREATE TRIGGER after_booking_update
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
    DECLARE v_food_name VARCHAR(150);
    DECLARE v_donor_id INT;

    IF NEW.status != OLD.status THEN
        SELECT food_name, donor_id INTO v_food_name, v_donor_id
        FROM donations WHERE donation_id = NEW.donation_id;

        -- ---------- Update total_food_donated when collected ----------
        IF NEW.status = 'collected' AND OLD.status != 'collected' THEN
            IF v_donor_id IS NOT NULL THEN
                UPDATE users
                SET total_food_donated = total_food_donated + NEW.quantity
                WHERE user_id = v_donor_id;
            END IF;
        END IF;

        -- ---------- Notify receiver ----------
        IF v_food_name IS NOT NULL THEN
            CALL create_notification(
                NEW.receiver_id,
                CONCAT('Booking ', NEW.status),
                CONCAT('Your booking for "', v_food_name, '" is now ', NEW.status, '.')
            );
        ELSE
            CALL create_notification(
                NEW.receiver_id,
                CONCAT('Booking ', NEW.status),
                CONCAT('Your booking status is now ', NEW.status, '.')
            );
        END IF;

        -- ---------- Notify admin for cancellations/missed ----------
        IF NEW.status IN ('cancelled', 'missed') THEN
            IF v_food_name IS NOT NULL THEN
                CALL create_notification(
                    1,
                    'Booking issue',
                    CONCAT('Booking #', NEW.booking_id, ' for "', v_food_name, '" is ', NEW.status)
                );
            ELSE
                CALL create_notification(
                    1,
                    'Booking issue',
                    CONCAT('Booking #', NEW.booking_id, ' is ', NEW.status)
                );
            END IF;
        END IF;

        -- ---------- Penalty for missed pickup ----------
        IF NEW.status = 'missed' AND OLD.status != 'missed' THEN
            CALL penalise_missed_pickup(NEW.receiver_id, NEW.booking_id);
        END IF;
    END IF;
END //

DELIMITER ;

-- 6.3 After new donation
DELIMITER //

CREATE TRIGGER after_donation_insert
AFTER INSERT ON donations
FOR EACH ROW
BEGIN
    IF NEW.status = 'active' THEN
        CALL create_notification(
            NEW.donor_id,
            'Donation published',
            CONCAT('Your donation "', NEW.food_name, '" is now active and visible to receivers.')
        );
    END IF;
END //

DELIMITER ;

-- 6.4 After donation status update
DELIMITER //

CREATE TRIGGER after_donation_update
AFTER UPDATE ON donations
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        CALL create_notification(
            NEW.donor_id,
            CONCAT('Donation ', NEW.status),
            CONCAT('Your donation "', NEW.food_name, '" is now ', NEW.status, '.')
        );

        CALL create_notification(
            1,
            'Donation status change',
            CONCAT('Donation #', NEW.donation_id, ' (', NEW.food_name, ') is now ', NEW.status)
        );
    END IF;
END //

DELIMITER ;

-- 6.5 After trust score log entry
DELIMITER //

CREATE TRIGGER after_trust_log_insert
AFTER INSERT ON trust_score_log
FOR EACH ROW
BEGIN
    CALL create_notification(
        NEW.user_id,
        'Trust score updated',
        CONCAT('Your trust score changed by ', NEW.points_change, ' points. Reason: ', NEW.description)
    );
END //

DELIMITER ;

-- 6.6 After new review
DELIMITER //

CREATE TRIGGER after_review_insert
AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    DECLARE donor_id INT;
    DECLARE food_name VARCHAR(150);

    SELECT u.user_id, d.food_name INTO donor_id, food_name
    FROM bookings b
    JOIN donations d ON b.donation_id = d.donation_id
    JOIN users u ON d.donor_id = u.user_id
    WHERE b.booking_id = NEW.booking_id;

    CALL create_notification(
        donor_id,
        'New review',
        CONCAT('You received a ', NEW.rating, '-star review for "', food_name, '".')
    );
END //

DELIMITER ;

-- 6.7 After new report (notifies admin)
DELIMITER //

CREATE TRIGGER after_report_insert
AFTER INSERT ON reports
FOR EACH ROW
BEGIN
    CALL create_notification(
        1,
        'New report filed',
        CONCAT('Report #', NEW.report_id, ' for booking #', NEW.booking_id, ' – ', NEW.issue_type)
    );
END //

DELIMITER ;

-- 6.8 After report status update (notifies admin + reporter)
DELIMITER //

CREATE TRIGGER after_report_update
AFTER UPDATE ON reports
FOR EACH ROW
BEGIN
    DECLARE v_reporter_id INT;

    IF NEW.status != OLD.status THEN
        -- Get the receiver who filed this report
        SELECT receiver_id INTO v_reporter_id
        FROM bookings
        WHERE booking_id = NEW.booking_id;

        -- Notify admin
        CALL create_notification(
            1,
            'Report status updated',
            CONCAT('Report #', NEW.report_id, ' status changed from ', OLD.status, ' to ', NEW.status)
        );

        -- Notify the reporter (receiver) if found
        IF v_reporter_id IS NOT NULL THEN
            CALL create_notification(
                v_reporter_id,
                'Your report status updated',
                CONCAT('Report #', NEW.report_id, ' is now ', NEW.status)
            );
        END IF;
    END IF;
END //

DELIMITER ;

-- 6.9 After user registration (pending verification)
DELIMITER //

CREATE TRIGGER after_user_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    IF NEW.status = 'pending_verification' THEN
        CALL create_notification(
            1,
            'New user pending verification',
            CONCAT('User "', NEW.full_name, '" (', NEW.email, ') needs approval.')
        );
    END IF;
END //

DELIMITER ;

-- 6.10 After user status update
DELIMITER //

CREATE TRIGGER after_user_update
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        CALL create_notification(
            NEW.user_id,
            'Account status changed',
            CONCAT('Your account is now ', NEW.status, '.')
        );

        CALL create_notification(
            1,
            'User status change',
            CONCAT('User "', NEW.full_name, '" status changed to ', NEW.status)
        );
    END IF;
END //

DELIMITER ;

-- 6.11 After new certificate
DELIMITER //

CREATE TRIGGER after_certificate_insert
AFTER INSERT ON certificates
FOR EACH ROW
BEGIN
    CALL create_notification(
        NEW.donor_id,
        'New certificate earned',
        CONCAT('You received a certificate: "', NEW.certificate_name, '" from ', NEW.issued_by)
    );
END //

DELIMITER ;

-- 6.12 After voucher redemption unlocked
DELIMITER //

CREATE TRIGGER after_voucher_redemption_update
AFTER UPDATE ON voucher_redemptions
FOR EACH ROW
BEGIN
    IF NEW.status = 'unlocked' AND OLD.status = 'locked' THEN
        CALL create_notification(
            NEW.donor_id,
            'Voucher unlocked',
            CONCAT('You unlocked a voucher from "', 
                   (SELECT brand_name FROM vouchers WHERE voucher_id = NEW.voucher_id), '".')
        );
    END IF;
END //

DELIMITER ;

-- 6.13 After platform settings update (maintenance mode)
DELIMITER //

CREATE TRIGGER after_platform_settings_update
AFTER UPDATE ON platform_settings
FOR EACH ROW
BEGIN
    IF NEW.maintenance_mode != OLD.maintenance_mode THEN
        CALL create_notification(
            1,
            'Maintenance mode changed',
            CONCAT('Platform maintenance mode set to ', NEW.maintenance_mode)
        );
    END IF;
END //

DELIMITER ;

-- ============================================================
-- 7. Stored procedure for expiry reminders + auto‑missed
-- ============================================================
DELIMITER //

CREATE PROCEDURE process_expiry_reminders()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE d_id INT;
    DECLARE d_name VARCHAR(150);
    DECLARE exp_time DATETIME;
    DECLARE cur_donations CURSOR FOR
        SELECT donation_id, food_name, expiry_at
        FROM donations
        WHERE status = 'active' AND expiry_at > NOW();
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    UPDATE donations
    SET status = 'expired'
    WHERE status = 'active' AND expiry_at < NOW();

    UPDATE bookings
    SET status = 'missed'
    WHERE donation_id IN (
        SELECT donation_id FROM donations WHERE status = 'expired'
    )
    AND status = 'reserved';

    INSERT INTO notifications (user_id, title, description)
    SELECT DISTINCT b.receiver_id,
           'Donation expired',
           CONCAT('The donation "', d.food_name, '" has expired.')
    FROM bookings b
    JOIN donations d ON b.donation_id = d.donation_id
    WHERE d.status = 'expired' AND b.status IN ('reserved', 'collected')
      AND NOT EXISTS (
          SELECT 1 FROM notifications n
          WHERE n.user_id = b.receiver_id
            AND n.title = 'Donation expired'
            AND n.description LIKE CONCAT('%', d.food_name, '%')
            AND n.created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
      );

    OPEN cur_donations;
    read_loop: LOOP
        FETCH cur_donations INTO d_id, d_name, exp_time;
        IF done THEN LEAVE read_loop; END IF;

        IF exp_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 6 HOUR)
           AND NOT EXISTS (SELECT 1 FROM expiry_reminder_log WHERE donation_id = d_id AND reminder_time = '6h') THEN
            INSERT INTO expiry_reminder_log (donation_id, reminder_time) VALUES (d_id, '6h');
            INSERT INTO notifications (user_id, title, description)
            SELECT DISTINCT receiver_id,
                   'Donation expiring in 6 hours',
                   CONCAT('Donation "', d_name, '" expires in 6 hours. Please pick it up.')
            FROM bookings WHERE donation_id = d_id AND status = 'reserved';
        END IF;

        IF exp_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR)
           AND NOT EXISTS (SELECT 1 FROM expiry_reminder_log WHERE donation_id = d_id AND reminder_time = '2h') THEN
            INSERT INTO expiry_reminder_log (donation_id, reminder_time) VALUES (d_id, '2h');
            INSERT INTO notifications (user_id, title, description)
            SELECT DISTINCT receiver_id,
                   'Donation expiring in 2 hours',
                   CONCAT('Donation "', d_name, '" expires in 2 hours. Please pick it up.')
            FROM bookings WHERE donation_id = d_id AND status = 'reserved';
        END IF;

        IF exp_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 MINUTE)
           AND NOT EXISTS (SELECT 1 FROM expiry_reminder_log WHERE donation_id = d_id AND reminder_time = '30m') THEN
            INSERT INTO expiry_reminder_log (donation_id, reminder_time) VALUES (d_id, '30m');
            INSERT INTO notifications (user_id, title, description)
            SELECT DISTINCT receiver_id,
                   'Donation expiring in 30 minutes',
                   CONCAT('Donation "', d_name, '" expires in 30 minutes. Please pick it up immediately.')
            FROM bookings WHERE donation_id = d_id AND status = 'reserved';
        END IF;
    END LOOP;

    CLOSE cur_donations;
END //

DELIMITER ;

-- ============================================================
-- 8. Scheduled events
-- ============================================================

-- 8.1 Marks missed bookings based on pickup slot (every 1 second)
DELIMITER //

CREATE EVENT IF NOT EXISTS event_mark_missed_bookings
ON SCHEDULE EVERY 1 SECOND
DO
BEGIN
    UPDATE bookings b
    JOIN pickup_slots p ON b.pickup_slot_id = p.pickup_slot_id
    SET b.status = 'missed'
    WHERE b.status = 'reserved' 
      AND p.timeslot < NOW();
END //

DELIMITER ;

-- 8.2 Expiry reminders (every 30 minutes)
CREATE EVENT IF NOT EXISTS process_expiry_reminders_event
ON SCHEDULE EVERY 30 MINUTE
DO
    CALL process_expiry_reminders();

-- ============================================================
-- 9. Verification (optional)
-- ============================================================
-- SHOW TRIGGERS;
-- SHOW EVENTS;
-- SHOW PROCEDURE STATUS WHERE Db = 'foodbridge';