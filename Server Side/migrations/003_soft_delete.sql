-- 003_soft_delete.sql — hide posts instead of destroying them.
-- Additive and nullable; existing rows and existing code are unaffected.

ALTER TABLE `messages`
  -- When set, the post is hidden everywhere on the site. NULL means visible.
  ADD COLUMN `deleted_at` timestamp NULL DEFAULT NULL,
  ADD KEY `idx_deleted_at` (`deleted_at`);
