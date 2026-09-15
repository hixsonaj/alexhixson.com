-- 002_threads.sql — reply threads.
-- Additive and nullable: existing rows are untouched and existing code keeps
-- working, so this can run before or after the code that uses it.

ALTER TABLE `messages`
  -- The post a reply belongs to. Always the top-level post, never another reply,
  -- so a thread is one post and a flat, time-ordered list under it.
  ADD COLUMN `parent_id` int(11) DEFAULT NULL,
  -- This email's own Message-ID, so a later reply's In-Reply-To can find it.
  ADD COLUMN `email_message_id` varchar(255) DEFAULT NULL,
  -- Outlook's conversation key: the first 22 bytes of Thread-Index, base64.
  ADD COLUMN `thread_key` varchar(40) DEFAULT NULL,
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_email_message_id` (`email_message_id`),
  ADD KEY `idx_thread_key` (`thread_key`);
