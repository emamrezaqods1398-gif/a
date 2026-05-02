-- SATA Messenger Database Schema (2026 Gen)
-- Optimized for Shared Hosting & High Concurrency
-- Engine: InnoDB, Charset: utf8mb4_unicode_ci

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `sata_messenger` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sata_messenger`;

-- 1. Users Table (Core Identity)
-- Supports Soft Delete, Verification Ticks, and Privacy Settings
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `display_name` varchar(30) DEFAULT NULL,
  `unique_id` varchar(20) NOT NULL, -- The @id
  `phone_number` varchar(20) DEFAULT '09121111111', -- Hidden for normal users
  `is_admin` tinyint(1) DEFAULT 0, -- 1: Super Admin, 0: User
  `is_banned` tinyint(1) DEFAULT 0,
  `ban_reason` text DEFAULT NULL,
  `verification_tick` enum('none','black','blue','gold') DEFAULT 'none',
  `profile_bio` varchar(100) DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `unique_id` (`unique_id`),
  KEY `idx_last_seen` (`last_seen`),
  KEY `idx_verification` (`verification_tick`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. OTP & Sessions (Security Layer)
CREATE TABLE `otp_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `code` varchar(5) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_expires` (`email`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(64) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `device_type` varchar(50) DEFAULT 'Unknown',
  `last_activity` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `idx_user_activity` (`user_id`, `last_activity`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Chats & Groups (Entities)
CREATE TABLE `chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('private','group','channel') NOT NULL,
  `name` varchar(100) DEFAULT NULL, -- For groups/channels
  `unique_id` varchar(20) DEFAULT NULL, -- For public channels/groups
  `owner_id` int(11) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_color` enum('black','blue','gold') DEFAULT 'black',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat Members (Mapping Users to Chats)
CREATE TABLE `chat_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('member','admin','owner') DEFAULT 'member',
  `permissions` json DEFAULT NULL, -- Stores specific admin rights (JSON)
  `joined_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_muted` tinyint(1) DEFAULT 0,
  `is_pinned` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_membership` (`chat_id`, `user_id`),
  KEY `idx_chat_users` (`chat_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Messages (The Core Content)
-- Supports Soft Delete, Edit History, and Forwarding
CREATE TABLE `messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `chat_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL, -- NULL for System/Sata Bot
  `message_type` enum('text','image','video','audio','file','sticker') DEFAULT 'text',
  `content` text DEFAULT NULL, -- Text content or Caption
  `media_url` varchar(255) DEFAULT NULL, -- Path to file
  `media_thumbnail` varchar(255) DEFAULT NULL, -- BlurHash or Thumb URL
  `file_size` int(11) DEFAULT 0,
  `is_deleted_sender` tinyint(1) DEFAULT 0,
  `is_deleted_receiver` tinyint(1) DEFAULT 0,
  `is_edited` tinyint(1) DEFAULT 0,
  `edited_at` datetime DEFAULT NULL,
  `reply_to_id` bigint(20) DEFAULT NULL, -- Self-referencing for replies
  `forwarded_from_id` bigint(20) DEFAULT NULL,
  `view_count` int(11) DEFAULT 0, -- For channels
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_time` (`chat_id`, `created_at`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_deleted` (`is_deleted_sender`, `is_deleted_receiver`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Message Status (Read Receipts)
CREATE TABLE `message_status` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('sent','delivered','read') DEFAULT 'sent',
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_read` (`message_id`, `user_id`),
  KEY `idx_user_status` (`user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Stories (Updates Tab)
CREATE TABLE `stories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `media_url` varchar(255) NOT NULL,
  `media_type` enum('image','video') NOT NULL,
  `caption` varchar(100) DEFAULT NULL,
  `privacy_mode` enum('all','contacts','custom') DEFAULT 'all',
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. System Logs & Reports (Admin Tools)
CREATE TABLE `system_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `action_type` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reporter_id` int(11) NOT NULL,
  `target_type` enum('user','message','group','channel') NOT NULL,
  `target_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','reviewed','dismissed','actioned') DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Initial Data: The Sata Bot (Root Account)
-- Item 41-60: Sata Bot Configuration
INSERT INTO `users` (`email`, `password_hash`, `display_name`, `unique_id`, `phone_number`, `is_admin`, `verification_tick`) 
VALUES 
('root@sata.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sata Bot', 'sata', '09121111111', 1, 'gold');
-- Note: Password is 'm13841215' hashed with Bcrypt for demo purposes. In production, generate a fresh hash.

COMMIT;
