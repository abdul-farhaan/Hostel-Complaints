-- Hostel Maintenance and Complaint Tracker (NCSM301 Mini Project, P4)
-- Run this whole script once in the phpMyAdmin SQL tab (with no database selected).
-- To start again from scratch, first run: DROP DATABASE hostel_complaints;

CREATE DATABASE hostel_complaints CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hostel_complaints;

-- Students live in a hostel room; staff (warden, maintenance office) do not.
CREATE TABLE users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    login_id      VARCHAR(20)  NOT NULL UNIQUE,
    full_name     VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(10)  NOT NULL DEFAULT 'student',
    hostel        VARCHAR(50)  NULL,
    room_no       VARCHAR(10)  NULL,
    CHECK (role IN ('student', 'staff'))
);

CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(30) NOT NULL UNIQUE
);

-- A complaint moves only forward: Submitted -> In Progress -> Resolved.
CREATE TABLE complaints (
    complaint_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT          NOT NULL,
    category_id  INT          NOT NULL,
    title        VARCHAR(100) NOT NULL,
    description  TEXT         NOT NULL,
    location     VARCHAR(80)  NOT NULL,
    priority     VARCHAR(10)  NOT NULL DEFAULT 'Medium',
    status       VARCHAR(20)  NOT NULL DEFAULT 'Submitted',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at  TIMESTAMP    NULL,
    CHECK (priority IN ('Low', 'Medium', 'High')),
    CHECK (status IN ('Submitted', 'In Progress', 'Resolved')),
    FOREIGN KEY (user_id) REFERENCES users (user_id),
    FOREIGN KEY (category_id) REFERENCES categories (category_id)
);

-- History of every status change. old_status is NULL for the row written
-- when the student submits the complaint.
CREATE TABLE complaint_updates (
    update_id    INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT          NOT NULL,
    old_status   VARCHAR(20)  NULL,
    new_status   VARCHAR(20)  NOT NULL,
    remark       VARCHAR(255) NULL,
    updated_by   INT          NOT NULL,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints (complaint_id),
    FOREIGN KEY (updated_by) REFERENCES users (user_id)
);

INSERT INTO categories (name) VALUES
    ('Electrical'),
    ('Plumbing'),
    ('Network'),
    ('Housekeeping'),
    ('Furniture'),
    ('Other');

-- Every demo account uses the password: hostel123
INSERT INTO users (login_id, full_name, password_hash, role, hostel, room_no) VALUES
    ('warden',      'Hostel Warden',      '$2y$10$mFhu3DvknpBQqjINyTF/XeagIovtdW55mxVYV3mF3v0QyLw7EeeFa', 'staff',   NULL, NULL),
    ('maintenance', 'Maintenance Office', '$2y$10$mFhu3DvknpBQqjINyTF/XeagIovtdW55mxVYV3mF3v0QyLw7EeeFa', 'staff',   NULL, NULL),
    ('24JE9101',    'Rahul Sharma',       '$2y$10$mFhu3DvknpBQqjINyTF/XeagIovtdW55mxVYV3mF3v0QyLw7EeeFa', 'student', 'Amber Hostel', 'A-112'),
    ('24JE9102',    'Priya Nair',         '$2y$10$mFhu3DvknpBQqjINyTF/XeagIovtdW55mxVYV3mF3v0QyLw7EeeFa', 'student', 'Rosaline Hostel', 'R-205'),
    ('24JE9103',    'Karan Mehta',        '$2y$10$mFhu3DvknpBQqjINyTF/XeagIovtdW55mxVYV3mF3v0QyLw7EeeFa', 'student', 'Amber Hostel', 'A-318'),
    -- Team members (replace the names with your own)
    ('24JE0134',    'Team Member 1',      '$2y$10$mFhu3DvknpBQqjINyTF/XeagIovtdW55mxVYV3mF3v0QyLw7EeeFa', 'student', 'Jasper Hostel', 'J-101'),
    ('24JE0312',    'Team Member 2',      '$2y$10$mFhu3DvknpBQqjINyTF/XeagIovtdW55mxVYV3mF3v0QyLw7EeeFa', 'student', 'Jasper Hostel', 'J-102'),
    ('24JE0369',    'Team Member 3',      '$2y$10$mFhu3DvknpBQqjINyTF/XeagIovtdW55mxVYV3mF3v0QyLw7EeeFa', 'student', 'Jasper Hostel', 'J-103');

-- Dates are relative to the day the script is run, so the demo always looks recent.
INSERT INTO complaints (user_id, category_id, title, description, location, priority, status, created_at, updated_at, resolved_at) VALUES
    (3, 1, 'Ceiling fan not working',
     'The ceiling fan stopped working last night. The regulator turns but the fan does not move at any speed.',
     'Amber Hostel, Room A-112', 'High', 'In Progress',
     NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 1 DAY, NULL),
    (3, 3, 'Wi-Fi keeps disconnecting',
     'The hostel Wi-Fi disconnects every few minutes in the evening. Other rooms on the first floor have the same problem.',
     'Amber Hostel, first floor corridor', 'Medium', 'Submitted',
     NOW() - INTERVAL 5 HOUR, NOW() - INTERVAL 5 HOUR, NULL),
    (3, 2, 'Leaking tap in washroom',
     'The tap in the first-floor washroom keeps dripping even when fully closed.',
     'Amber Hostel, first floor washroom', 'Low', 'Resolved',
     NOW() - INTERVAL 6 DAY, NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 4 DAY),
    (4, 4, 'Corridor not cleaned',
     'The second-floor corridor has not been swept for three days and the dustbin is overflowing.',
     'Rosaline Hostel, second floor corridor', 'Medium', 'Submitted',
     NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY, NULL),
    (4, 5, 'Broken study chair',
     'One leg of the study chair is cracked and the chair is unsafe to sit on.',
     'Rosaline Hostel, Room R-205', 'Low', 'In Progress',
     NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 2 DAY, NULL),
    (5, 1, 'Power socket sparking',
     'The power socket next to the study table sparks when a charger is plugged in. I have stopped using it.',
     'Amber Hostel, Room A-318', 'High', 'Submitted',
     NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 2 HOUR, NULL),
    (5, 2, 'No water in bathroom',
     'There is no water supply in the third-floor bathroom since this morning.',
     'Amber Hostel, third floor bathroom', 'High', 'Resolved',
     NOW() - INTERVAL 8 DAY, NOW() - INTERVAL 7 DAY, NOW() - INTERVAL 7 DAY);

-- The history behind the statuses above. The first row of each complaint is
-- written by the student who submitted it; later rows are written by staff.
INSERT INTO complaint_updates (complaint_id, old_status, new_status, remark, updated_by, updated_at) VALUES
    (1, NULL, 'Submitted', NULL, 3, NOW() - INTERVAL 2 DAY),
    (1, 'Submitted', 'In Progress', 'Electrician assigned. The fan capacitor will be replaced tomorrow morning.', 2, NOW() - INTERVAL 1 DAY),
    (2, NULL, 'Submitted', NULL, 3, NOW() - INTERVAL 5 HOUR),
    (3, NULL, 'Submitted', NULL, 3, NOW() - INTERVAL 6 DAY),
    (3, 'Submitted', 'In Progress', 'Plumber will visit in the afternoon.', 2, NOW() - INTERVAL 5 DAY),
    (3, 'In Progress', 'Resolved', 'Tap washer replaced. No more leakage.', 2, NOW() - INTERVAL 4 DAY),
    (4, NULL, 'Submitted', NULL, 4, NOW() - INTERVAL 1 DAY),
    (5, NULL, 'Submitted', NULL, 4, NOW() - INTERVAL 3 DAY),
    (5, 'Submitted', 'In Progress', 'A replacement chair has been requested from the store.', 1, NOW() - INTERVAL 2 DAY),
    (6, NULL, 'Submitted', NULL, 5, NOW() - INTERVAL 2 HOUR),
    (7, NULL, 'Submitted', NULL, 5, NOW() - INTERVAL 8 DAY),
    (7, 'Submitted', 'In Progress', 'Pump motor checked by the maintenance team.', 2, NOW() - INTERVAL 8 DAY + INTERVAL 3 HOUR),
    (7, 'In Progress', 'Resolved', 'Pump motor repaired and water supply restored.', 2, NOW() - INTERVAL 7 DAY);
