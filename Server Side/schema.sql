-- Schema for a new site's database.
-- Run once against the empty database in cPanel > phpMyAdmin > SQL.
--
-- utf8mb4 throughout, unlike the original alexhixson database, which is latin1.
-- latin1 can't hold emoji or many punctuation characters — they arrive from
-- Outlook and get mangled or truncated on the way in. New sites start correct.

CREATE TABLE IF NOT EXISTS `messages` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `sender_name`  varchar(255) DEFAULT NULL,
  `sender_email` varchar(255) DEFAULT NULL,
  `subject`      varchar(255) DEFAULT NULL,
  `message`      text         DEFAULT NULL,
  `image_url`    varchar(500) DEFAULT NULL,
  `received_at`  timestamp    NOT NULL DEFAULT current_timestamp(),
  -- Threads (see migrations/002_threads.sql)
  `parent_id`        int(11)      DEFAULT NULL,
  `email_message_id` varchar(255) DEFAULT NULL,
  `thread_key`       varchar(40)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_received_at` (`received_at`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_email_message_id` (`email_message_id`),
  KEY `idx_thread_key` (`thread_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `polls` (
  `id`         int(11)  NOT NULL AUTO_INCREMENT,
  `message_id` int(11)  NOT NULL,
  `options`    longtext NOT NULL CHECK (json_valid(`options`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `poll_votes` (
  `id`           int(11)     NOT NULL AUTO_INCREMENT,
  `poll_id`      int(11)     NOT NULL,
  `option_index` int(11)     NOT NULL,
  `ip_hash`      varchar(64) NOT NULL,
  `voted_at`     timestamp   NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  -- One vote per poll per visitor. INSERT IGNORE relies on this to make a
  -- repeat vote a silent no-op rather than an error.
  UNIQUE KEY `unique_vote` (`poll_id`,`ip_hash`),
  KEY `idx_poll_id` (`poll_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
