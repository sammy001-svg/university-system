-- =====================================================================
--  Super Administrator login
--  Import after schema.sql (phpMyAdmin > Import, or:
--    mysql -u <user> -p <database> < database/seed_admin_login.sql)
--
--    Username : admin            (or the e-mail admin@bestbrain.edu.lr)
--    Password : Admin@2026
--
--  The password must be changed at first sign-in.
--
--  Safe to run on any database, any number of times:
--   - creates the Super Administrator role if it is missing and grants it
--     every permission that exists;
--   - creates the "admin" account, or, if it already exists, resets its
--     password, re-activates it and clears any lockout. Use this to recover
--     a lost administrator password.
--  Rows are matched by username and role slug, never by numeric id, so no
--  other account can be affected.
-- =====================================================================
SET NAMES utf8mb4;

-- 1. Super Administrator role
INSERT INTO `roles` (`name`, `slug`, `description`, `level`, `is_system`)
VALUES ('Super Administrator', 'super-admin', 'Unrestricted access to every part of the system.', 1, 1)
ON DUPLICATE KEY UPDATE `level` = 1, `is_system` = 1;

-- 2. Every permission for that role
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
  FROM `roles` r
  CROSS JOIN `permissions` p
 WHERE r.`slug` = 'super-admin';

-- 3. The administrator account
--    password_hash is bcrypt (cost 12) of "Admin@2026", as App\Core\Hash::make() produces.
INSERT INTO `users` (
  `uuid`, `username`, `email`, `password_hash`, `title`, `first_name`, `last_name`,
  `gender`, `phone`, `user_type`, `status`, `must_change_password`, `email_verified_at`
) VALUES (
  UUID(),
  'admin',
  'admin@bestbrain.edu.lr',
  '$2y$12$DkXmJkIcqCdI45YZ7SuPX.tj7XnFUNE5OWM.pjsGt.9qLAqJ8.gcK',
  'Mr',
  'System',
  'Administrator',
  'male',
  '+231770000000',
  'admin',
  'active',
  1,
  NOW()
)
ON DUPLICATE KEY UPDATE
  `password_hash`        = VALUES(`password_hash`),
  `user_type`            = 'admin',
  `status`               = 'active',
  `must_change_password` = 1,
  `failed_attempts`      = 0,
  `locked_until`         = NULL,
  `deleted_at`           = NULL;

-- 4. Give the account the Super Administrator role
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT u.`id`, r.`id`
  FROM `users` u
  JOIN `roles` r ON r.`slug` = 'super-admin'
 WHERE u.`username` = 'admin';

-- 5. Check: one row, status active, role super-admin
SELECT u.`id`, u.`username`, u.`email`, u.`status`, u.`must_change_password`, r.`slug` AS `role`
  FROM `users` u
  JOIN `user_roles` ur ON ur.`user_id` = u.`id`
  JOIN `roles` r       ON r.`id` = ur.`role_id`
 WHERE u.`username` = 'admin';
