-- 005_bump.sql — forwarding a post back to the feed moves it to the top.
--
-- Sorting uses COALESCE(bumped_at, received_at), so a bumped post rises while
-- still showing the date it was originally written.

ALTER TABLE `messages`
  ADD COLUMN `bumped_at` timestamp NULL DEFAULT NULL,
  ADD KEY `idx_bumped_at` (`bumped_at`);
