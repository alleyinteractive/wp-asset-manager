# WordPress Asset Manager

Asset Manager is a toolkit for managing front-end assets and more tightly controlling where, when, and how they're loaded.

## Installation

Download a [tagged release](https://github.com/alleyinteractive/wp-asset-manager/releases), install it into your plugins directory, and activate it on the plugins screen.

Or install with Composer:

```sh
composer require alleyinteractive/wp-asset-manager
```

## Quick start

The `am_enqueue_*` functions take the same parameters as their core WordPress equivalents, plus a few of their own. Options can be passed as an array or as individual parameters.

```php
// Load a CSS asset asynchronously.
am_enqueue_style(
  [
    'handle'      => 'site-styles',
    'src'         => 'css/styles.css',
    'deps'        => [],
    'condition'   => 'global',
    'load_method' => 'async', // 'sync', 'inline', 'async', 'defer', 'preload'
    'version'     => '1.0.0',
    'load_hook'   => 'wp_head',
    'media'       => 'all', // 'print', 'screen', or any valid media query
  ]
);
```

## Documentation

Full API reference lives in the [wiki](https://github.com/alleyinteractive/wp-asset-manager/wiki):

* **[Enqueue Functions](https://github.com/alleyinteractive/wp-asset-manager/wiki/Enqueue-Functions)** — `am_enqueue_script`, `am_enqueue_style`, and the full list of enqueue options.
* **[Conditions](https://github.com/alleyinteractive/wp-asset-manager/wiki/Conditions)** — control when an asset loads, and register your own conditions.
* **[Inline Assets](https://github.com/alleyinteractive/wp-asset-manager/wiki/Inline-Assets)** — print file contents or global JavaScript variables into the document.
* **[Preload](https://github.com/alleyinteractive/wp-asset-manager/wiki/Preload)** — `am_preload` and its options.
* **[SVG Sprite](https://github.com/alleyinteractive/wp-asset-manager/wiki/SVG-Sprite)** — register, display, and manage SVG symbols in a sprite sheet.

## Requirements

* WordPress: 6.3+
* PHP: 8.3+

## Downloads and Versioning

You can view [Asset Manager's official releases here](https://github.com/alleyinteractive/wp-asset-manager/releases).

The `production` branch is the latest stable release. The `production-built` branch tracks the same release with dependencies included, for sites that install the plugin directly from Git.

## License

[GPL-2.0-only](license.txt)

## Contributing

Bug reports and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for setup, tests, and coding standards.
