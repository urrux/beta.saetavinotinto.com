# Project rules

## What this repo is

Active codebase for `beta.saetavinotinto.com` — a staging / development
site planned to soon replace placeholder content at the production
domain `saetavinotinto.com`.

The site is a custom PHP 8.0 application served from cPanel:
- Static landing layer at the root (`index.html`, `index.php`, root
  JS/CSS, `analytics.js`, `app.js`, `styles.css`, `llms.txt`)
- The actual application lives under `portal/` (database schemas under
  `portal/database/`, uploads under `portal/uploads/` — NOT tracked,
  see below)
- Server: shared cPanel host, PHP 8.0 (ea-php80 handler in `.htaccess`)

## Deployment

- Deployment is via cPanel Git Version Control only. Never SSH-push or
  FTP-upload directly to the live docroot.
- The user triggers deploys from cPanel. Claude does not deploy.
- `.cpanel.yml` deploys to `/home/sietebit/beta.saetavinotinto.com/`
  via tar with `--no-overwrite-dir` and a trailing `chmod 750`. Both
  flags are load-bearing — see the sietebit repo's history (commit
  `4a2068b`) for the story of how we learned that the hard way.

## Repository hygiene

- **Never commit secrets.** `.env*`, `*.key`, `*.pem`, anything matching
  `*credentials*` or `*secrets*` is gitignored. Don't bypass; extend
  `.gitignore` if a new secret-bearing pattern appears.
- **Never commit user-uploaded content.** `portal/uploads/` is
  gitignored. Production uploads stay on the server; the tar deploy
  has no `--delete`, so they survive every deploy.
- **Never commit database dumps.** `*-before.sql`, `*-after.sql`,
  `*-backup.sql`, `*-dump.sql`, `database-before.sql` patterns are
  gitignored. Schema files (`portal/database/schema.mysql.sql`,
  `schema.sqlite.sql`) ARE tracked — they're source.
- **Never commit `error_log` / `*.log`.** Server-generated.
- **Never commit `_rollback/` or `_audit/`.** These were filesystem-based
  versioning from before git. Git now handles versioning; do not
  re-introduce them to the repo. The existing `_rollback/` on the
  server stays put — our deploy doesn't propagate deletions.
- No force-push to `main`. No rewriting deployed history.
- Don't commit binaries >1MB without flagging.

## Editing

- The local tree mirrors the docroot 1:1 (minus the gitignored items).
- Don't edit files directly on the server — round-trip through git so
  the repo stays the single source of truth.
- `.htaccess` changes can break the live site immediately on deploy —
  flag any change to it explicitly.
- `.cpanel.yml` is load-bearing — propose changes as a diff and wait
  for review before committing.

## Promoting beta → production (saetavinotinto.com)

When this site is ready to replace placeholder content at
`saetavinotinto.com`, the simplest path:

1. **Decide whether you want beta to keep running.** Two flavors:
   - **Beta becomes prod, no separate staging:** change `DEPLOYPATH`
     in `.cpanel.yml` from `/home/sietebit/beta.saetavinotinto.com/`
     to `/home/sietebit/saetavinotinto.com/`, commit, push, deploy.
     The cPanel Git Version Control entry now publishes to the www
     docroot. (Beta domain can stay configured but won't receive
     deploys.)
   - **Keep beta alongside prod:** add a SECOND cPanel Git Version
     Control entry that clones this same repo, with a different
     `.cpanel.yml` deploying to the prod docroot. Or use git branches
     (`main` → prod, `develop` → beta) and configure each cPanel entry
     to track its own branch.

2. **Don't forget to snapshot before promotion.** The
   `saetavinotinto-backup` repo (urrux/saetavinotino-backup on GitHub)
   already captures pre-promotion prod content. Sufficient rollback
   safety.

3. **Email forwarders & cPanel-side config** for `info@saetavinotinto.com`
   etc. don't move automatically with a Git deploy — handle separately
   in cPanel UI.

## Verification

- Test locally when behavior changes. PHP built-in server:
  `php -S localhost:8000` from the project root.
- For database-driven features, you'll need a local MySQL/SQLite setup
  matching the schemas under `portal/database/`.
- If a change can't be tested locally, say so rather than claiming it
  works.
