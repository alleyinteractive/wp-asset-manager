# ChangeLog

This project adheres to [Semantic Versioning](http://semver.org/).

## 2.0.0

### Breaking

* Raised the minimum PHP version to 8.3.
* Raised the minimum WordPress version to 6.3.
* Changed the text domain from `am` to `wp-asset-manager` to match the plugin slug. Existing
  translation files will need to be renamed.
* Removed the `async-defer` load method ([#63](https://github.com/alleyinteractive/wp-asset-manager/issues/63)).
  `async` takes precedence over `defer` when both are present, so the combination had no effect
  worth supporting. Use `async` instead. Scripts still using `async-defer` fall back to `sync`.
* Removed `Scripts::$async_scripts`, `Scripts::add_to_async()`, `Scripts::add_attributes()`,
  `Scripts::disable_concat()`, and `Scripts::manage_async()`. These implemented the pre-WordPress-6.3 fallback for async and defer, which core's `strategy` argument now handles.
* Removed `Asset_Manager::$assets_by_dependency` and `Asset_Manager::$assets_manual`. Both were
  public but always empty and referenced nowhere in the plugin.
* Removed the `preload` load method from `am_enqueue_style()`, deprecated since 0.1.1. It patched
  in a call to `am_preload()` and downgraded the style to `sync`. Call `am_preload()` directly.
  Styles still passing `preload` fall back to `sync` and no preload hint is emitted.

### Added

* Added `imagesrcset`, `imagesizes`, and `fetchpriority` options to `am_preload()` for preloading responsive images ([#55](https://github.com/alleyinteractive/wp-asset-manager/issues/55)).
* Added PHPStan support, at level 9.

### Deprecations

* The `am_*` template tags — `am_enqueue_script()`, `am_enqueue_style()`, `am_preload()`,
  `am_register_symbol()`, `am_use_symbol()`, `am_get_symbol()`, and the rest — will be formally
  deprecated in the next major version. They continue to work in 2.x.

### Fixed

* Fixed test failures on WordPress 7.0, which rebuilt `wp_kses_hair()` on top of the HTML API
  ([#76](https://github.com/alleyinteractive/wp-asset-manager/issues/76)). Escaped output now
  lowercases SVG attribute names such as `viewBox` and encodes single quotes as `&apos;`. Both
  are cosmetic — browsers restore SVG attribute casing when parsing HTML, and `&apos;` is decoded
  before an inline handler runs — so the plugin's markup is unchanged. Test expectations adapt to
  the running WordPress version instead of assuming one.
* Fixed `deps` being ignored for assets the plugin prints itself
  ([#22](https://github.com/alleyinteractive/wp-asset-manager/issues/22)). Inline styles and
  scripts, and async and defer styles, bypass `wp_enqueue_*` and so never reached core's
  dependency resolution — they printed in the order they were registered, which made `deps`
  meaningless for critical CSS. They are now ordered so a dependency prints before anything that
  depends on it, transitively. Assets with no dependency between them keep their registration
  order. This changes output order only where the registration order already contradicted the
  declared dependencies.
* Fixed a fatal error when an asset was enqueued with a function that doesn't exist. The
  `invalid_enqueue_function` branch passed the return value of `generate_asset_error()` — which
  returns nothing and has already printed the error — into `format_error()`, so any user with the
  `am_view_asset_error` capability hit a method call on null.
* Fixed `create_symbol()` returning null instead of a pair when an SVG file can't be read or
  parsed. The caller destructures the return value, so a missing sprite file raised two
  "Trying to access array offset on value of type null" warnings on every page that used it.
* Corrected `SVG_Sprite::get_svg()`, which was documented as returning a `DOMDocument` but returns
  the `<svg>` `DOMElement`, or now null on failure. The wrong type made `get_default_dimensions()`
  and `create_symbol()` look broken to static analysis even though they were correct.
* Corrected `generate_asset_error()`, documented as taking an array error code when every one of
  its call sites passes a string, and `asset_should_add()`, documented as taking a string asset
  when it is always given an array and whose `@return WP_Error` resolved to a class that does not
  exist.
* Fixed a PHPStan type mismatch so the `condition` key of `am_enqueue_script()` is recognized as
  accepting both an array and a string ([#72](https://github.com/alleyinteractive/wp-asset-manager/pull/72)).
* Fixed `am_modify_load_method()` raising a `TypeError` when passed an array of options, the form
  shown in the documentation. It now accepts `array|string` like every other `am_*` helper.
* Fixed `am_modify_load_method()` failing to apply `async` or `defer` on WordPress 6.3+
  ([#62](https://github.com/alleyinteractive/wp-asset-manager/issues/62)). The load method is
  carried by core's `strategy` argument, which is only read at enqueue time, so changing it
  afterwards now writes the strategy back to the registered script via `wp_script_add_data()`.
* Fixed `SVG_Sprite` adding a nested array to the `safe_style_css` allowlist
  ([#73](https://github.com/alleyinteractive/wp-asset-manager/issues/73)). The filter is documented
  as `string[]`, and the nested value raised an "Array to string conversion" warning on every
  rendered page once a downstream callback — such as Gutenberg's
  `gutenberg_add_svg_to_safe_style_css()` — ran `array_unique()` over the list.
* `SVG_Sprite::remove_symbol()` now compares handles strictly, matching every other `in_array()`
  call in the codebase.
* Added an explicit dependency on `alleyinteractive/composer-wordpress-autoloader`. It was
  previously inherited from `mantle-framework/testkit`, which dropped it in v1.21.0, leaving a
  fresh `composer install` unable to autoload the plugin's own classes.

### Changed

* Moved the API reference out of the README and into the
  [wiki](https://github.com/alleyinteractive/wp-asset-manager/wiki).
* CI now tests PHP 8.3, 8.4, and 8.5 against the latest WordPress, plus a dedicated job covering
  the minimum supported WordPress version.
* Excluded the `WordPress.NamingConventions.PrefixAllGlobals` sniff. WPCS 3.x enforces a
  four-character minimum prefix, which rejects the plugin's published `am_` prefix.
* Tests use Mantle's `Mantle\Support\Helpers\capture()` in place of a local `get_echo()` helper.
* Renamed `phpcs.xml` to `phpcs.xml.dist` so the ruleset can be overridden locally.
* Test suite is now linted. Test files declare `strict_types`, test methods declare `public`
  visibility and `void` return types, and each test class is scoped with `#[CoversClass]`.
* Excluded the `WordPress.WP.EnqueuedResources` sniff. Printing `<link>` and `<script>` tags
  directly is what the async and defer load methods are for.
* Added a `Text Domain` header to the plugin file.
* `am_enqueue_script()` and `am_enqueue_style()` now call `_doing_it_wrong()` when given an
  unrecognised load method, instead of silently falling back to `sync`. Omitting the load method
  entirely still selects the default without a notice.
* CI no longer starts the MySQL, Redis, and Memcached containers; the test bootstrap uses SQLite.
* Added `CONTRIBUTING.md`.

## 1.4.3

### Changed

* Register an asset to the WP dependency registry. (#66)
  * Available only if `QueryMonitor` is active.
* Updated plugin header `description` to match the one in package and in the git repo.
* Added support for `.phpunit-watcher.yml`.
* Fix alignment for `package.json` and `composer.json` (Use spaces instead of tabs per `.editorconfig` rules).

## 1.4.2

### Fixed

- Fixed issue with array of data being passed to the `$src` argument of `am_enqueue_script()`.

## 1.4.1

### Fixed

- Ensure that `$version` can be null when passed to helper methods.

## 1.4.0

### Fixed

- Added proper types for helper functions that support array in the first argument.
- Fix: `Asset_Manager_Preload::set_asset_types()` should return an empty array if no valid arguments are passed.

### Changed

* Migrates code into the `Alley\WP\Asset_Manager` namespace. Legacy classes such
  as `Asset_Manager_Scripts` and `Asset_Manager_Styles` are aliased to their new
  namespace for backward compatibility. Helper functions are left un-namespaced.
* Adds a dependency on Composer autoloader. For submodules, you can track the
  `production-built` branch of the plugin or any tagged release (which will be
  built) to include the dependencies.

## 1.3.7

* Adds support for async and defer using the 'strategy' argument added for wp_enqueue_script in WordPress 6.3.

## 1.3.6

* Adds support for running the plugin on a Windows hosting environment (#57)

## 1.3.5

* Removes default kses allowed SVG attributes

## 1.3.4

* Fix PHP 8.2 deprecations.

## 1.3.3

* Removes `visibility:hidden` SVG sprite style declaration, which breaks some SVG element references (#50)
* Upgrades to `mantle-framework/testkit` v0.11 (#50)

## 1.3.2

**Changed**

* The SVG Sprite is no longer hidden with `display:none` and instead visually hidden and moved offscreen (#49)

## 1.3.1

* Check for array key before using when preloading assets (#47).
* Upgrades to `mantle-framework/testkit` v0.10.1 (#47).

## 1.3.0

**Changed**

* Use `am_view_asset_error` meta capability to determine whether to display error messages related to asset enqueuing. `am_view_asset_error` is mapped to `manage_options` by default.

## 1.2.0

**Added**

* `am_symbol_is_registered` for determining if a symbol is registered (#41)

**Changed**

* Filters kses allowed svg+use tags & attributes (#43)

**Fixed**

* `print_asset()` no longer fails for local files on WP VIP environments (#40)

## 1.1.2

* Updates the requirements on `mantle-framework/testkit` to permit the latest version (#36)
* Addresses PHP 8 compatibility issue with the global usage in `Asset_Manager::add_core_asset()` (#37)
* Fixes a bug where `get_svg()` returns false for local files on WP VIP environments (#39)

## 1.1.1

Adds support for registering SVG assets to be added to a template's sprite sheet, with methods for displaying those assets

## 1.0.0

Stable release 🎊

No large changes since [v0.1.3](https://github.com/alleyinteractive/wp-asset-manager/releases/tag/0.1.3) other than switching the unit tests over to Mantle Teskit

## 0.1.3

**Added**

* `am_inline_stylesheet` filter for inline stylesheets

## 0.1.2

**Added**

* GPL License
* Caching for `am_asset_conditions`
* Composer Autoloader
* GitHub Actions CI

## 0.1.1

**Added**

* Supports `include_any` in the `condition` parameter for matching _any_ condition (#10, #19)
* Adds `am_preload` for preloading assets of any supported type (#16)
* Improves overall plugin documentation

**Changed**

* Uses the print media swap method for async-loaded styles, based on Filament Group's [The Simplest Way to Load CSS Asynchronously](https://www.filamentgroup.com/lab/load-css-simpler/) (#13)

**Fixed**

* Includes the `media` attribute only if `media` exists (#7)
* Uses self-closing link tags (#7)
* Ensures `in_footer` is set (#8)
* Corrects an issue where `loadCSS` was being output for load_methods other than defer (3510e8c)

**Removed**

* `am_enqueue_style` with `load_method => preload` is no longer supported. For backward compatibility this configuration will patch in a call to `am_preload` and also `sync` enqueue the asset (127acbc)
* The `loadCSS` preload polyfill is removed since it is [no longer supported](https://github.com/filamentgroup/loadCSS#changes-in-version-30-no-more-preload-polyfill) (5d820d9)

## 0.1.0

Initial release.
