# Contributing

Development of Asset Manager happens on [GitHub](https://github.com/alleyinteractive/wp-asset-manager). Bugs should be reported in the issue queue; enhancements and fixes are welcome as pull requests.

## Requirements

* WordPress: 6.3+
* PHP: 8.3+

## Getting set up

```sh
git clone git@github.com:alleyinteractive/wp-asset-manager.git
cd wp-asset-manager
composer install
```

## Running the checks

```sh
composer test         # everything: phpcs, then phpunit
composer phpcs        # coding standards only
composer phpcbf       # fix what can be fixed automatically
composer phpunit      # tests only
```

Tests are built on [Mantle's testing framework](https://mantle.alley.com/docs/testing). The bootstrap installs WordPress for you, so there is no separate test-suite setup step.

Target a single class or group while iterating:

```sh
vendor/bin/phpunit --filter test_print_asset
vendor/bin/phpunit --group assets
```

Test classes are grouped with `#[Group]` and scoped with `#[CoversClass]` at the class level.

### A note on WordPress versions

When you run the tests from inside an existing WordPress installation — for example with the plugin checked out under `wp-content/plugins/` — Mantle uses that surrounding installation and ignores `WP_VERSION`. Run the suite from a standalone checkout to exercise a specific WordPress version locally; otherwise rely on CI, which covers the minimum and latest supported versions.

## Branches

`production` is the latest stable release. `production-built` tracks the same release with dependencies included, for sites installing the plugin directly from Git.

## Coding standards

The project follows [Alley's coding standards](https://github.com/alleyinteractive/alley-coding-standards), configured in `phpcs.xml.dist`. Two deliberate deviations are documented in that file:

Please keep `composer test` green, and add a `CHANGELOG.md` entry for anything user-facing.
