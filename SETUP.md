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
`schema.sql` is always current; the files in `Server Side/migrations/` are only
for bringing an existing database up to date.

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

### Links

Anywhere in the body of a post or essay:

```
Check out [google](google.com) for more.     ->  "google" links to https://google.com
https://example.com/page                     ->  linked as itself
www.example.com                              ->  linked as itself
```

The part in square brackets is the text shown; the part in parentheses is where
it goes. `https://` is optional. Links open in a new tab. Only http and https
targets are linked — anything else is left as plain text.

Outlook turns addresses you type into hyperlinks and appends a copy in angle
brackets to the plain-text version it sends, e.g.
`[google](www.google.com<http://www.google.com/>)`. That copy is recognized and
dropped, so type links normally.

### Essays

```
Subject: Essay: Why I Stopped Buying New Things
```

The text after `Essay:` becomes the title and the body is the essay. The feed
shows the title, a short preview, and READ ENTRY, which opens it at
`/essay/<id>`. Essays can be about 64,000 characters, versus 2,000 for a normal
post. Links work the same. An attached image appears at the end.

A subject can be a poll or an essay, not both.

Because the site is shown through GoDaddy's masked forwarding, the address bar
stays on the .com. To share a particular essay, use its
`https://<site>.zerofour.tech/essay/<id>` address.

### Replies

Reply to the email of a post — the copy in your Sent folder — and send it to the
same feed address. The reply appears under that post, oldest first, with its
date and time. Under an essay, the feed shows a reply count and the essay page
shows the replies.

Only the new text is posted. Quoted history below it is removed: Outlook's
`From: / Sent:` block, `-----Original Message-----`, Gmail's `On … wrote:`, `>`
quoted lines, and sign-offs like "Sent from my iPhone". Links and an image work
in a reply the same as in a post.

How a reply finds its post, most reliable first:

1. **In-Reply-To / References** headers, matched to the Message-ID stored with
   each post.
2. **Outlook's Thread-Index**, which every message in a conversation shares.
3. **The quoted original text**, compared against the 300 most recent posts. This
   is what makes replying work for posts sent before threads existed, since
   those were stored without a Message-ID.

A reply to a reply joins the original post's thread; threads are one level
deep. If nothing matches, the text is posted as a new post and the mail log
says so. Replies can't create polls or essays.

Posting still requires an address in `allowed_senders` — replies are yours and
Leah's, not public comments.

### Times

Dates and reply times come from the database server's clock, which is not your
time zone. They're right to the day but a reply's time can be off by an hour or
more, depending on the time of year.

## Domains and certificates

The hosting plan allows no addon domains, so each `.com` is a cPanel **alias** of
zerofour.tech. An alias always serves `public_html`, and
`public_html/.htaccess` (source: `Server Side/public_html.htaccess`) routes each
`.com` to its site folder. The PHP accepts the `.com` through `aliases` in
`secrets.php`.

An alias shares zerofour.tech's web server entry, and cPanel allows one
certificate per entry. **Installing a certificate for just `alexhixson.com`
replaces zerofour.tech's** — this happened once. So zerofour.tech and every
aliased `.com` share one Let's Encrypt certificate, managed by acme.sh:

| Certificate | Names | Managed by | Renews |
|---|---|---|---|
| zerofour.tech | zerofour.tech, alexhixson.com (+ www each) | acme.sh | automatically, ~every 60 days |
| alexhixson.zerofour.tech | + www | ssl.com, manual | by hand |
| leahhixson.zerofour.tech | + www | ssl.com, manual | by hand |

acme.sh lives in `~/.acme.sh` on the server. A cron job checks four times a day;
when renewal is due it renews and reinstalls through cPanel on its own.

Check it:

```bash
ssh vuc923ya50qu@zerofour.tech "~/.acme.sh/acme.sh --list"
```

**Adding leahhixson.com.** Add it as an alias in cPanel (Domains → Create, leave
"Share document root" checked), point its DNS at `198.12.232.172`, then reissue
with every name — the list replaces the old one, so include all of them:

```bash
ssh vuc923ya50qu@zerofour.tech '~/.acme.sh/acme.sh --issue --force --server letsencrypt --keylength 2048 -d zerofour.tech -d www.zerofour.tech -d alexhixson.com -d www.alexhixson.com -d leahhixson.com -d www.leahhixson.com -w ~/public_html && ~/.acme.sh/acme.sh --deploy -d zerofour.tech --deploy-hook cpanel_uapi'
```

Validation files are served from `public_html/.well-known/` for every name —
the first rule in `.htaccess` makes sure of that.

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
