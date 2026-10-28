# Working on this repo

Two personal blogs — alexhixson.com and leahhixson.com — served from one React
codebase and one set of PHP endpoints on shared cPanel hosting. Posts arrive by
email: a mail pipe parses the message and files it as a post, reply, essay, poll
or forward.

`SETUP.md` is the user-facing manual (how posting, polls, essays, replies,
forwards, deleting and certificates work). Read it before changing behaviour.
This file is the operational context that isn't obvious from the code.

## Shape of the system

| Piece | Where it runs |
|---|---|
| React front end | built per site into `builds/<site>/`, deployed to that site's web root |
| Web PHP (`retrieve_messages`, `submit_vote`, `gallery_images`, `profile_images`) | each site's web root; identifies its site from the `Host` header |
| `site_config.php`, `mail_reply.php`, `secrets.php` | **account root** (`~/`), outside `public_html`, shared by everything |
| `message.php` (mail pipe) | `~/mail/<domain>/message/`; identifies its site from its own path |
| Databases | one per site, separate, never shared |

Host: `vuc923ya50qu@zerofour.tech`. Web roots are
`public_html/<domain>/`.

## Rules that aren't negotiable

**Never commit `Server Side/secrets.php`.** It holds both databases' passwords
and is gitignored. Don't print credentials into the conversation either; read
them in PHP on the server instead of echoing them.

**Deploy with the scripts.** `./scripts/deploy-site.sh <site> [--web|--php|--mail|--all]`.
They refuse a build that doesn't reference the target domain and fix the two
permission bits that fail silently. Editing files directly on the server puts
the repo out of sync with reality.

**Two permissions fail silently after any upload:**
- `message.php` without `+x` — the mail pipe never runs. No error, no log line,
  mail just disappears.
- images without `644` — the webserver returns 403 and photos don't load.

The deploy script handles both. Manual uploads need them by hand.

**Alex's database is latin1 and must stay that way.** Its rows hold raw UTF-8
bytes written through a connection that never declared a charset. Setting the
connection to utf8mb4 makes MySQL "convert" them and apostrophes render as
`â€™`. That's why `secrets.php` carries `'charset' => 'latin1'` for that site
only. New databases are genuinely utf8mb4. Don't "fix" this.

**A domain with no config gets a 500, never a fallback.** `site_secrets()`
deliberately has no default site: quietly serving one site's database under
another's domain is the failure the whole arrangement exists to prevent. Keep it
that way.

## Testing

**Leah's database is the test bed** — it's empty and nobody reads her site yet.
Pipe test emails into `mail/leahhixson.zerofour.tech/message/message.php`
directly, verify, then delete the rows. Guard the cleanup with a count check so
a wrong assumption can't wipe real data.

**Never cast test votes on Alex's live polls**, and never pipe test mail into his
database.

**Run the parser tests after touching `mail_reply.php`:**

```bash
php "Server Side/tests/test_mail_reply.php"
```

They cover quote stripping across Outlook, Gmail and Apple Mail, plus subject
detection for replies, forwards and delete. They've caught real bugs — an empty
`Subject:` line in Outlook quotes, which every one of these posts has.

**Back up before any migration:**

```bash
mysqldump --defaults-extra-file=<cnf> --single-transaction <db> | gzip > ~/backups/<db>-$(date +%Y%m%d-%H%M%S).sql.gz
```

Migrations in `Server Side/migrations/` are additive and nullable so code and
schema can deploy in either order. `schema.sql` is the current full state for a
new site; migrations only bring an existing database forward.

## Things that look like bugs but aren't

- **The feed returns some posts twice.** A forwarded post appears at the top
  under its new comment and again in its original chronological place. Rows carry
  `is_bump` to say which appearance is which; only the bumped one gets the notes.
  React keys must stay unique per appearance.
- **`ip_hash` is still written on votes** even though `ip_address` is the one
  that's read. It carries the one-vote-per-poll unique key, including for votes
  cast before addresses were stored.
- **Replies match on an exact text prefix, forwards on similarity.** Filing a
  reply under the wrong post is worse than not filing it; a forward that can't be
  matched confidently becomes a new post instead.
- **`message@alexhixson.zerofour.tech` still pipes into the feed** alongside
  `alexfeed@`. Kept so replies to old emails still work.

## Known weaknesses

- **The mail pipe trusts the `From:` header**, which is forgeable. Nothing checks
  SPF or DMARC before the pipe runs, hotmail publishes `p=none`, and
  zerofour.tech has no DMARC record. Anyone who guesses the feed address could
  post — or delete. Unresolved; the fix is requiring the subject token always, or
  at least for `delete`.
- **PHP version is the host's call.** Only one build is installed and cPanel
  controls it. As of 2026-10-27 the server is on 8.2.34 with 8.2.35 (two critical
  RCE fixes) not yet shipped, and 8.2 reaches end of life 2026-12-31.
- **Certificates auto-renew** via acme.sh on the server (`~/.acme.sh`, cron four
  times daily, cPanel deploy hook). Don't install ssl.com certificates over them:
  an alias shares the main domain's vhost, and cPanel keeps one certificate per
  vhost, so doing that once knocked zerofour.tech offline.

## Conventions

- Comments explain **why**, especially where the code looks odd — the latin1
  connection, the retained hash, the refusal to fall back. Match the existing
  density; don't annotate the obvious.
- Commit messages say what changed and why, including what was ruled out.
- Verify against the live endpoints after deploying rather than assuming, and
  check the other site didn't regress.
- Deleting is soft (`deleted_at`), because a mis-matched reply must stay
  recoverable. Keep destructive operations reversible.
