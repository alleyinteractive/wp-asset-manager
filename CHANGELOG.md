# Change Log
This project adheres to [Semantic Versioning](http://semver.org/).

## 1.3.0

**Changed**

* Use `am_view_asset_error` meta capability -- mapped to `manage_options` by default -- to determine whether to display errors messages related to asset enqueuing.

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
