-- SQL Script to Set Up Topspring Gems Comprehensive School (TGCS)
-- Run this script in your database (sadik_app) to initialize the school

-- 1. Insert or Update the primary school record
INSERT INTO `schools` (`id`, `name`, `code`, `address`, `logo_path`, `border_color`, `created_by`, `created_at`) 
VALUES (4, 'Topspring Gems Comprehensive School', 'TGCS', 'Your School Address Here', 'uploads/logos/tgcs_logo_1746814969_IMG-20250509-WA0002.jpg', '#2ecc71', 1, NOW())
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`), 
    `logo_path` = VALUES(`logo_path`),
    `border_color` = VALUES(`border_color`);

-- 2. Update School Details for reporting
INSERT INTO `school_details` (`id`, `school_name`, `address`, `logo_path`, `created_by`, `created_at`, `border_color`)
VALUES (4, 'Topspring Gems Comprehensive School', 'Your School Address Here', 'uploads/logos/tgcs_logo_1746814969_IMG-20250509-WA0002.jpg', 1, NOW(), '#2ecc71')
ON DUPLICATE KEY UPDATE 
    `school_name` = VALUES(`school_name`), 
    `logo_path` = VALUES(`logo_path`),
    `border_color` = VALUES(`border_color`);

-- 3. Assign the Admin (ID 1) and any default Teachers (e.g. ID 4, 5, 6) to the new school
UPDATE `users` SET `school_id` = 4 WHERE `id` = 1; -- Admin
UPDATE `users` SET `school_id` = 4 WHERE `role` = 'teacher' AND `school_id` = 1; -- Also move default teachers if needed

-- 4. Set up the Report Card colors for the new school
INSERT INTO `report_card_settings` (`school_id`, `primary_color`, `secondary_color`, `created_by`, `created_at`)
VALUES (4, '#2ecc71', '#2c3e50', 1, NOW())
ON DUPLICATE KEY UPDATE 
    `primary_color` = VALUES(`primary_color`),
    `secondary_color` = VALUES(`secondary_color`);
