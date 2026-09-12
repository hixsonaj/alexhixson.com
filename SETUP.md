# Running two sites from this repo

One codebase, two sites. Nothing in `src/` or `Server Side/` names a specific
site — the domain arrives at build time for the front end, and at request time
for the PHP.

| | Alex | Leah |
|---|---|---|
| Domain | alexhixson.zerofour.tech | leahhixson.zerofour.tech |
| Post by emailing | alexfeed@zerofour.tech | leahfeed@zerofour.tech |
| Config | `sites/alex.env` | `sites/leah.env` |
| Build output | `builds/alex/` | `builds/leah/` |

## Everyday use

```bash
npm run build:leah      # -> builds/leah/
npm run deploy:leah     # front end only

./scripts/deploy-site.sh leah --php    # + web PHP
./scripts/deploy-site.sh leah --mail   # + mail pipe (chmods it for you)
./scripts/deploy-site.sh leah --all    # everything
```

`deploy-site.sh` refuses to upload a build that doesn't reference the target
domain, so a stale or wrong-site build can't reach the server.

## How a site is identified

- **Front end** — `sites/<name>.env` sets `REACT_APP_API_BASE`, baked in at build
  time. If it's ever missing, `src/config.js` falls back to the domain the page
  is served from, never to the other site.
- **Web PHP** — `Host` header, resolved by `site_config.php`. An unconfigured
  domain gets a 500, not another site's data.
- **Mail pipe** — its own path. `~/mail/<domain>/message/message.php` reads
  `<domain>` from the directory name, so the same file self-configures wherever
  it's placed.

## One-time setup for a new site

**1. Database** — cPanel > MySQL Databases. Create the database and a user, grant
all privileges. Then phpMyAdmin > SQL, paste `Server Side/schema.sql`.

**2. Secrets** — add a block to `secrets.php` keyed by domain (see
`secrets.example.php`), then upload to the account root:

```bash
scp "Server Side/secrets.php" vuc923ya50qu@zerofour.tech:secrets.php
```

**3. Folders**

```bash
ssh vuc923ya50qu@zerofour.tech "mkdir -p public_html/<domain>/{gallery,profile-images,post-images} && chmod 755 public_html/<domain>/{gallery,profile-images,post-images}"
```

**4. Mail routing** — cPanel > Email > Forwarders > Add Forwarder. Address
`leahfeed@zerofour.tech`, destination *Pipe to a Program*:

```
mail/leahhixson.zerofour.tech/message/message.php
```

Path is relative to the home directory, with no leading slash.

The inbound address appears nowhere in the code — it is only this forwarder, so
renaming it is a cPanel change and nothing else. `allowed_senders` in
`secrets.php` is a separate thing: who may post, not where they post to.

Current forwarders:

| Address | Pipes to |
|---|---|
| `alexfeed@zerofour.tech` | `mail/alexhixson.zerofour.tech/message/message.php` |
| `leahfeed@zerofour.tech` | `mail/leahhixson.zerofour.tech/message/message.php` |

**5. Deploy**

```bash
npm run build:leah
./scripts/deploy-site.sh leah --all
```

**6. Check** — email the site from an address in its `allowed_senders`, then:

```bash
ssh vuc923ya50qu@zerofour.tech "tail -5 mail/leahhixson.zerofour.tech/message/mail_debug.log"
```

You want `script started (site=leahhixson.zerofour.tech)` through to `done`.

## Posting by email

Send from an address in that site's `allowed_senders`. Body becomes the post;
paragraph breaks are preserved; the first image attachment is saved and shown
under the text.

A poll goes in the subject line:

```
Poll: pizza, tacos, sushi
```

Two or more options, comma separated. The subject is consumed by the poll and
doesn't appear as a title. One vote per visitor per poll, deduplicated by a hash
of their IP.

## Things that bite

**`message.php` must be executable.** SCP and FTP both drop the execute bit, and
without it the mail pipe never runs — no error, no log line, mail just disappears.
`deploy-site.sh --mail` chmods it; if you upload by FileZilla, do it yourself:

```bash
ssh vuc923ya50qu@zerofour.tech "chmod 755 mail/<domain>/message/message.php"
```

**Images need 644.** Uploads often land unreadable and the webserver returns 403.
Every `deploy-site.sh` run fixes this for all three image folders.

**Deploy `index.html` with the bundle.** It names the hashed JS file. Uploading
`static/` alone leaves the old filename referenced and the page loads nothing.
The deploy script copies the whole build, so this only bites manual uploads.

**Each site needs its own `subject_token`.** Anyone who learns a token can post
to that site from any address.

## Known issue: Alex's database is latin1

`alexhixson.com_mail` is latin1, which can't represent emoji or several
punctuation characters. It mostly works by accident — PHP writes UTF-8 bytes and
MySQL hands them back untouched — but emoji can truncate a post at the emoji.
Leah's database is utf8mb4 via `schema.sql` and doesn't have this problem.
Converting Alex's is a `ALTER TABLE ... CONVERT TO CHARACTER SET utf8mb4` per
table, worth doing during a quiet moment with a backup first.

## Still Alex-specific

`src/Socials.js` links one Instagram account, from `REACT_APP_INSTAGRAM_URL`.
`src/Rome.js` is Alex's content and is routed at `/Rome` on both sites. Neither
is linked from Leah's nav, but the route exists.
