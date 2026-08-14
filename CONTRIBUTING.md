# Contributing to Asset Manager 1.x

Development of Asset Manager happens on [GitHub](https://github.com/alleyinteractive/wp-asset-manager). Bugs should be reported in the issue queue; fixes are welcome as pull requests.

## This branch

`release/v1.x` is the maintenance line for the 1.x series. It exists for one reason: to keep shipping patch releases — 1.4.4, 1.4.5, and so on — to sites that cannot move to the current major.

Scope is deliberately narrow. Bug fixes, security fixes, and compatibility fixes with newer PHP and WordPress. No new features, no refactors, and no dependency upgrades beyond what a fix requires. Everything that lands here has to be maintained separately from `production`, so the smaller the delta, the longer this line stays viable.

If a fix applies to both lines, land it on `production` first and cherry-pick it here.

## Requirements

CI tests PHP 8.2 and 8.3 against the latest WordPress, and that is the supported range in practice.

`composer.json` deliberately declares no `php` constraint on this branch, so that a patch release cannot refuse an install that 1.4.3 already allowed. The floor is really PHP 8.0, which is where the union types in `src/helpers.php` were introduced in 1.4.0 — the README's claim of 7.4+ has been wrong since then.

## Getting set up

```sh
git clone git@github.com:alleyinteractive/wp-asset-manager.git
cd wp-asset-manager
git checkout release/v1.x
composer install
```

## Making a patch release

Releases are cut from a *built branch* — the source plus `vendor/`, tagged — so that sites can install straight from Git. `.github/workflows/built-release.yml` on this branch does that on every push to `release/v1.x`.

Throughout, `X.Y.Z` is the version you are releasing. Increment the patch number from the last 1.x tag.

### 1. Land the fix

Open a pull request against `release/v1.x`. CI runs on pull requests to this branch and must be green.

### 2. Bump the version in three places

All three must read exactly `X.Y.Z`:

* `Version:` in `wp-asset-manager.php`
* `version` in `package.json`
* a new `## X.Y.Z` section in `CHANGELOG.md`

The changelog heading has to match the plugin header exactly. The release tooling looks the section up *by* the version it reads from the header and fails outright if it finds none, so a heading of `Unreleased` — or a version that disagrees with the header — breaks the release rather than degrading.

If the version is unchanged, the push still refreshes `release/v1.x-built` but creates no release, because the tag already exists. That is the normal outcome for a merge that isn't a release.

### 3. Merge

The push to `release/v1.x` builds `release/v1.x-built` — force-pushed each time, since it is a build artifact rather than history — tags `vX.Y.Z` against it, and opens a **draft** release with that version's changelog section as the notes.

### 4. Publish, leaving "Set as latest" unticked

Releases from this line are tagged after the current major's, so publishing as latest would drag the repository's "Latest" marker backwards onto an older series. This is why the workflow drafts rather than publishes: `action-release` always passes `--latest`, and drafting is the only point at which that can be corrected.
