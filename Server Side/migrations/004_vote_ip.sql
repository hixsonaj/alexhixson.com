-- 004_vote_ip.sql — store the voter's IP address alongside the hash.
--
-- The hash never provided anonymity: the webserver access log records the raw
-- address with a timestamp and poll_votes records voted_at, so the two line up;
-- an unsalted SHA-256 of an IPv4 address also falls to brute force in seconds.
-- Storing the address plainly makes the data honest about what it is.
--
-- ip_hash is kept, and still carries the one-vote-per-poll unique key. Votes
-- cast before this change have no address (see backfill_vote_ips.php, which
-- recovers what the retained access logs still cover), and dropping the hash
-- would let those people vote a second time.

ALTER TABLE `poll_votes`
  ADD COLUMN `ip_address` varchar(45) DEFAULT NULL AFTER `option_index`;
