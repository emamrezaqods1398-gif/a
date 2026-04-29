-- Messenger DB Schema v2.0 (185 Features Optimized)
-- Supports Partitioning, High Concurrency, and Advanced Admin Features

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- 1. System Settings Table (Dynamic Configs)
-- --------------------------------------------------------
CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('registration_enabled', '1'),
('invite_only_mode', '0'),
('email_verification_required', '0'),
('maintenance_mode', '0'),
('welcome_message', 'به پیام‌رسان ما خوش آمدید!'),
('story_limit_normal', '5'),
('story_limit_blue', '20'),
('story_limit_gold', '100'),
('story_limit_black', '999'),
('public_link_limit_normal', '2'),
('public_link_limit_blue', '10'),
('public_link_limit_gold', '999'),
('auto_suspend_threshold', '10');

-- --------------------------------------------------------
-- 2. Reserved Handles Table
-- --------------------------------------------------------
CREATE TABLE `reserved_handles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `handle` varchar(50) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `handle` (`handle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `reserved_handles` (`handle`, `reason`) VALUES
('admin', 'System Reserved'),
('support', 'System Reserved'),
('news', 'System Reserved'),
('gold', 'Premium Reserved');

-- --------------------------------------------------------
-- 3. Users Table (With Badges, Privacy, and Status)
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `display_name` varchar(100) DEFAULT NULL,
  `bio` varchar(255) DEFAULT 'Hey there! I am using this messenger.',
  `avatar_path` varchar(255) DEFAULT NULL,
  `badge_type` enum('normal','blue','gold','black') DEFAULT 'normal',
  `is_deleted` tinyint(1) DEFAULT 0,
  `is_banned` tinyint(1) DEFAULT 0,
  `ban_reason` text DEFAULT NULL,
  `appeal_status` enum('none','pending','approved','rejected') DEFAULT 'none',
  `device_fingerprint` varchar(255) DEFAULT NULL,
  `two_step_pin` varchar(6) DEFAULT NULL,
  `two_step_email` varchar(100) DEFAULT NULL,
  `privacy_last_seen` enum('everyone','contacts','nobody') DEFAULT 'everyone',
  `privacy_profile_photo` enum('everyone','contacts','nobody') DEFAULT 'everyone',
  `privacy_bio` enum('everyone','contacts','nobody') DEFAULT 'everyone',
  `privacy_status` enum('everyone','contacts','nobody') DEFAULT 'everyone',
  `read_receipts_enabled` tinyint(1) DEFAULT 1,
  `groups_privacy` enum('everyone','contacts') DEFAULT 'everyone',
  `default_message_timer` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `phone` (`phone`),
  KEY `is_deleted` (`is_deleted`),
  KEY `is_banned` (`is_banned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Admin User (Pass: admin123)
INSERT INTO `users` (`username`, `phone`, `password_hash`, `display_name`, `badge_type`, `is_banned`) VALUES
('admin', '0000000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'black', 0);

-- --------------------------------------------------------
-- 4. Invites Table (Invite-Only Mode)
-- --------------------------------------------------------
CREATE TABLE `invites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `created_by` int(11) NOT NULL,
  `uses_limit` int(11) DEFAULT 1,
  `uses_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Chats Table (Private, Group, Channel)
-- --------------------------------------------------------
CREATE TABLE `chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('private','group','channel') NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `public_handle` varchar(50) DEFAULT NULL,
  `join_approval_required` tinyint(1) DEFAULT 0,
  `send_messages_allowed` enum('all','admins') DEFAULT 'all',
  `edit_info_allowed` enum('all','admins') DEFAULT 'admins',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_handle` (`public_handle`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. Chat Participants & Permissions
-- --------------------------------------------------------
CREATE TABLE `chat_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('member','admin','owner') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_read_message_id` int(11) DEFAULT 0,
  `is_muted` tinyint(1) DEFAULT 0,
  `mute_until` timestamp NULL DEFAULT NULL,
  `is_pending` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_participant` (`chat_id`, `user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. Messages Table (Partitioned by Date for Scale)
-- --------------------------------------------------------
CREATE TABLE `messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `chat_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `message_type` enum('text','image','video','audio','file','sticker') DEFAULT 'text',
  `content` text DEFAULT NULL,
  `media_path` varchar(255) DEFAULT NULL,
  `media_mime` varchar(50) DEFAULT NULL,
  `media_size` int(11) DEFAULT 0,
  `reply_to_id` bigint(20) DEFAULT NULL,
  `is_edited` tinyint(1) DEFAULT 0,
  `edited_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `delete_for_all` tinyint(1) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`, `created_at`),
  KEY `chat_id` (`chat_id`),
  KEY `sender_id` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) (
  PARTITION p0 VALUES LESS THAN (UNIX_TIMESTAMP('2024-01-01')),
  PARTITION p1 VALUES LESS THAN (UNIX_TIMESTAMP('2024-06-01')),
  PARTITION p2 VALUES LESS THAN (UNIX_TIMESTAMP('2025-01-01')),
  PARTITION p3 VALUES LESS THAN MAXVALUE
);

-- --------------------------------------------------------
-- 8. Message Reports (User Reporting System)
-- --------------------------------------------------------
CREATE TABLE `message_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reason_category` enum('spam','harassment','adult','violence','other') NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','reviewed','action_taken','dismissed') DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `message_id` (`message_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 9. User Reports (Profile Reporting)
-- --------------------------------------------------------
CREATE TABLE `user_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reported_user_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reason_category` enum('spam','fake_account','harassment','other') NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','reviewed','action_taken','dismissed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `reported_user_id` (`reported_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 10. Ban Appeals
-- --------------------------------------------------------
CREATE TABLE `ban_appeals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `appeal_text` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 11. Stories (Status Updates)
-- --------------------------------------------------------
CREATE TABLE `stories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `media_path` varchar(255) NOT NULL,
  `media_type` enum('image','video') NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 12. Story Views
-- --------------------------------------------------------
CREATE TABLE `story_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `story_id` int(11) NOT NULL,
  `viewer_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_view` (`story_id`, `viewer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 13. Admin Logs
-- --------------------------------------------------------
CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` enum('user','message','group','setting') DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 14. Pinned Chats & Messages
-- --------------------------------------------------------
CREATE TABLE `pinned_chats` (
  `user_id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `pinned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`, `chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pinned_messages` (
  `chat_id` int(11) NOT NULL,
  `message_id` bigint(20) NOT NULL,
  `pinned_by` int(11) NOT NULL,
  `pinned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`chat_id`, `message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
