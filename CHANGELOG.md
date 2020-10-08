# Change Log
This project adheres to [Semantic Versioning](http://semver.org/).

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
