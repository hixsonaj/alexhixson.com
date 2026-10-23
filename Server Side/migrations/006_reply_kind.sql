-- 006_reply_kind.sql — tell a forward's comment apart from an ordinary reply.
--
-- A forward's comment is a later thought about an older post, so it reads above
-- the post it belongs to. A reply reads below. Both are rows in messages with a
-- parent_id; this is what separates them.
--   NULL      ordinary reply, shown under the post
--   'forward' comment that came in with a forward, shown above the post

ALTER TABLE `messages`
  ADD COLUMN `reply_kind` varchar(16) DEFAULT NULL;
