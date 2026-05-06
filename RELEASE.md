# Release runbook — gtm-kit (free)

Cuts a release of the free `gtm-kit` plugin to wordpress.org. Three actors:

- **Cowork** — drafts the release post, plans the announcement, updates `ROADMAP.md` after release.
- **Claude Code** — does the mechanical release-prep work in a `release/X.Y.Z` branch and opens the release PR.
- **Torben** — decides scope, reviews and merges, tags, builds the zip, submits to wp.org, publishes the release post, handles post-release Premium follow-ups.

Each phase below labels the owner explicitly. Phases that can run in parallel say so. The order otherwise is sequential.

---

## Phase 0 — Decide to release

**Owner: Torben**

1. Confirm everything intended for the release is on `main`. Open PRs that should not block the release stay open and ship in the next one.
2. Confirm CI is green on `main` (the `.github/workflows/test.yml` matrix: PHP 8.1–8.5 × WP 6.9, PHPUnit unit + integration + Vitest).
3. Decide the version number. Semver guides this: a release with new admin sections + new public hooks is **minor** (e.g. `2.9.0` → `2.10.0`); a release with bugfixes only is **patch** (e.g. `2.10.0` → `2.10.1`).
4. Tell Cowork to draft the release post (Phase 1) and Claude Code to prepare the release branch (Phase 2). They can run in parallel.

---

## Phase 1 — Cowork drafts the release post

**Owner: Cowork**

Runs in parallel with Phase 2.

1. Read the `Unreleased` block in [`changelog.txt`](changelog.txt) and the `= Unreleased =` block in [`readme.txt`](readme.txt) to understand scope.
2. Read the merged PRs and any associated spec docs in `~/OneDrive - TLA Media ApS/GTM Kit/Claude/_planning/feature-specs/` to understand the why behind each change.
3. Draft the release post at `gtmkit.com/gtm-kit-X-Y/` (e.g. `gtmkit.com/gtm-kit-2-10/`):
   - One headline paragraph naming the release theme.
   - One section per major feature with a screenshot or example.
   - A short "Developer notes" section listing new hooks for integrators.
   - A migration note for any opt-in default changes.
4. Confirm with Torben before going live. The `readme.txt` `= X.Y.Z =` block links to the release-post URL, so the URL must exist before the wp.org submission in Phase 4.

Cowork does NOT touch any plugin file. The release post lives on gtmkit.com only.

---

## Phase 2 — Claude Code prepares the release branch

**Owner: Claude Code**

Runs in parallel with Phase 1.

Trigger from Torben: "Prepare the release of gtm-kit X.Y.Z." Claude Code follows this checklist end-to-end, in order:

### 2.1 Branch from current main

```bash
cd wp-content/plugins/gtm-kit
git checkout main
git pull --ff-only origin main
git checkout -b release/X.Y.Z
```

### 2.2 Pre-bump gates

All four must be green before touching anything else. If any is red, stop and surface the failure.

```bash
composer phpstan
composer phpcs
vendor/bin/phpunit --testsuite unit
WP_TESTS_DIR="$TMPDIR/wordpress-tests-lib-6.9" vendor/bin/phpunit --testsuite integration
npm test
```

### 2.3 Bump version everywhere

```bash
npm version --no-git-tag-version X.Y.Z
./bin/change-version.sh X.Y.Z
```

After this, **verify** all three identifiers match `X.Y.Z`:

```bash
grep -E "Version:|GTMKIT_VERSION" gtm-kit.php
grep "Stable tag" readme.txt
grep '"version"' package.json
```

### 2.4 Flip changelog headers

In [`changelog.txt`](changelog.txt): replace the literal line `Unreleased` with `YYYY-MM-DD - version X.Y.Z` (date format matches the existing entries).

In [`readme.txt`](readme.txt): replace the `= Unreleased =` block header with the canonical three-line block:

```
= X.Y.Z =

Release date: YYYY-MM-DD

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-X-Y/).
```

(Use the same URL Cowork is publishing in Phase 1.)

### 2.5 Regenerate the translation template

**This step is non-negotiable.** It is the most-missed step in the runbook because `bin/change-version.sh` does NOT cover it.

```bash
npm run i18n:pot
```

Verify `languages/gtm-kit.pot` now has `Project-Id-Version: GTM Kit X.Y.Z` at the top and that all the new admin strings the release introduced are present:

```bash
head -5 languages/gtm-kit.pot
git diff --stat languages/gtm-kit.pot     # should show a non-trivial diff
```

If the diff is suspiciously small for a release with new admin copy, re-run and investigate — translations are how non-English users see the new sections, and silently shipping the prior pot leaves them stuck on stale strings for an entire release cycle.

### 2.6 Rebuild every shipped asset

The build chain is split across three commands; running only `build:assets` deletes the React app bundles without rebuilding them. Always run all three:

```bash
npm run build:assets                                    # consent-gating shim, integration uglifies, image copy, size guard
npm run build                                           # WooCommerce blocks bundle (wp-scripts)
( cd ../gtm-kit-settings && npm run build )             # React admin app → ../gtm-kit/assets/admin/*
npm run uglify:consent-gating                           # back-fill: wp-scripts cleared assets/frontend/ in step 2
```

Step 4 is necessary because `wp-scripts` (called by step 2) cleans `assets/frontend/` before writing its own output. The shim has to be re-emitted afterwards or it ships missing.

Verify the bundle-size guard:

```bash
npm run check:consent-gating-size                       # must be ≤ 1024 bytes
```

If this fails, do not work around it; trim the shim source instead. Documented budget rationale is in [`docs/filters.md`](../../../docs/filters.md) under "Strong-block configuration".

### 2.7 Re-run all gates after the bump + asset build

Same as step 2.2. All must still be green.

### 2.8 Commit, push, open the release PR

Single commit titled `Release X.Y.Z`. The body summarises:

- The version-bump diffs (gtm-kit.php / readme.txt / package.json / package-lock.json).
- The changelog flip (`Unreleased` → date+version).
- The pot regen (mention the `Project-Id-Version` change).
- The rebuilt assets (consent-gating shim size, the React admin chunks).
- The headline scope of the release (3-5 bullets).

```bash
git add -A
git commit -m "Release X.Y.Z" -m "<body per template above>"
git push -u origin release/X.Y.Z
gh pr create --base main --head release/X.Y.Z --title "Release X.Y.Z" --body "<see PR template below>"
```

PR description template:

```
## Summary

Cuts gtm-kit X.Y.Z. Headline scope: <3–5 bullets matching the release post>.

Version bump:
- gtm-kit.php — Version header + GTMKIT_VERSION constant
- readme.txt — Stable tag X.Y.Z + canonical = X.Y.Z = block linked to gtmkit.com/gtm-kit-X-Y/
- package.json + package-lock.json — X.Y.Z

Translation template: languages/gtm-kit.pot regenerated, Project-Id-Version: GTM Kit X.Y.Z, <N> new strings.

Assets rebuilt:
- assets/frontend/consent-gating.js — <bytes>/1024 minified, bundle-size guard green
- assets/frontend/woocommerce-blocks.* — wp-scripts blocks bundle
- assets/admin/* — React admin app from gtm-kit-settings

## Quality gates
- [x] composer phpstan — 0 errors
- [x] composer phpcs — 0 errors, 0 warnings
- [x] PHPUnit unit + integration — <count>/<count> tests pass
- [x] Vitest — <count>/<count> tests pass
- [x] consent-gating bundle size — <bytes> / 1024 bytes

## Post-merge steps (Torben, Phase 4)
1. Tag merge commit: git tag -a X.Y.Z -m "Release X.Y.Z" && git push origin X.Y.Z
2. composer install --no-dev && ./bin/zip-package.sh
3. Publish gtmkit.com/gtm-kit-X-Y/ release post
4. Submit zip to wordpress.org SVN
5. (Premium follow-up) composer update tlamedia/gtm-kit in gtm-kit-premium once Packagist picks up the tag
```

Hand back to Torben.

---

## Phase 3 — Torben reviews and merges

**Owner: Torben**

1. Open the release PR. Read the diff end-to-end. Watch out for:
   - `Project-Id-Version` in the pot matches the new version.
   - The `readme.txt` release-post URL exists at gtmkit.com (Cowork's Phase 1 must be done).
   - No accidental version mismatch (one of the three identifiers can drift if the change-version script is interrupted).
2. Merge the PR (squash-merge or merge-commit; both work — just be consistent across releases).

---

## Phase 4 — Torben tags, builds, ships

**Owner: Torben**

After the merge lands on `main`:

### 4.1 Tag the merge commit

```bash
git checkout main
git pull --ff-only origin main
git tag -a X.Y.Z -m "Release X.Y.Z"
git push origin X.Y.Z
```

### 4.2 Build the wp.org zip

```bash
composer install --no-dev
./bin/zip-package.sh
```

The script outputs `gtm-kit-X.Y.Z.zip` ready for upload.

### 4.3 Publish the release post

If Cowork drafted the release post in Phase 1, publish it now at `gtmkit.com/gtm-kit-X-Y/`. The link in `readme.txt` already points there.

### 4.4 Submit to wordpress.org

Manual SVN submission to the wp.org plugin SVN. Standard wp.org plugin-author flow:

```bash
cd /path/to/svn-checkout/gtm-kit
# copy the zip's contents into trunk/, commit, then svn cp trunk/ tags/X.Y.Z/, commit again
```

### 4.5 Restore the Premium vendor

Once Packagist picks up the new tag (typically within minutes; check at `https://packagist.org/packages/tlamedia/gtm-kit`), bump the Premium plugin's vendored copy:

```bash
cd ../gtm-kit-premium
composer update tlamedia/gtm-kit
```

If this release added new public surface that Premium's tests want to exercise (e.g. the `ConsentSignalSourceRegistry` from 2.10.0), the test that was deferred can now be restored. Open a small PR for this.

---

## Phase 5 — Post-release follow-ups

### 5.1 Update the global ROADMAP

**Owner: Cowork**

Trigger from Torben: "Update the global roadmap, gtm-kit X.Y.Z is released."

Cowork:

1. Marks each shipped roadmap item complete in `~/OneDrive - TLA Media ApS/GTM Kit/Claude/ROADMAP.md`.
2. Moves any items unblocked by the release from "Blocked on …" to "Next up".
3. Adds any backlog items that emerged during implementation (from the per-feature handoff prompts).
4. Prints the diff so Torben can eyeball it.

`ROADMAP.md` is not under git; Cowork edits it in OneDrive directly.

### 5.2 Communicate

**Owner: Torben**

- Announce the release on whichever channels matter (email list, Twitter/X, LinkedIn, Slack support communities).
- Watch the support forum on wp.org for the first 24-48 hours; new admin sections in particular tend to surface "I don't see this on my install" issues that turn out to be cache/asset problems.
- If a critical regression surfaces, cut a patch release X.Y.(Z+1) by re-running this whole runbook with the bugfix branch as the base.

---

## Cross-references

- Companion runbooks: [`gtm-kit-woo/RELEASE.md`](../gtm-kit-woo/RELEASE.md), [`gtm-kit-premium/RELEASE.md`](../gtm-kit-premium/RELEASE.md).
- Build pipeline: see `package.json` `build`, `build:assets`, `uglify:*`, `i18n:pot` scripts.
- Bundle-size guard: [`bin/check-consent-gating-size.js`](bin/check-consent-gating-size.js).
- Lessons captured from prior releases (including the 2.10.0 i18n:pot miss): `gtmkitdev/tasks/lessons.md`.
