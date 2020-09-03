# WordPress Asset Manager

Asset Manager is a toolkit for managing front-end assets and more tightly controlling where, when, and how they're loaded.

* [Using Asset Manager in your WordPress Project](#using-asset-manager-in-your-wordpress-project)
* [Enqueue Functions](#enqueue-functions)
  * [am_enqueue_script](#am_enqueue_script)
  * [am_enqueue_style](#am_enqueue_style)
  * [Inline Assets](#inline-assets)
  * [Enqueue Options](#enqueue-options)
* [Preload Function](#preload-function)
  * [Preload Options](#preload-options)
* [Requirements](#requirements)
* [Downloads and Versioning](#downloads-and-versioning)
* [Contributing to Development](#contributing-to-development)

## Using Asset Manager in your WordPress Project

To get started, simply [download](#downloads-and-versioning) and install this plugin into your plugins directory and activate it on the plugins screen.

## Enqueue Functions

The `am_enqueue_*` functions will enqueue an asset with additional attributes based upon its `load_method` value. Options can be passed in as an array or individual parameters.

### `am_enqueue_script`

<!-- The `am_enqueue_script` function will enqueue a JavaScript file with additional attributes based upon the `load_method` value. -->

```php
// Enqueue a JavaScript asset.
am_enqueue_script(
  [
    'handle'      => 'footer-script',
    'src'         => 'js/script.js',
    'deps'        => [],
    'condition'   => 'global',
    'load_method' => 'sync', // 'sync', 'inline', 'async', 'defer', 'async-defer'
    'version'     => '1.0.0',
    'load_hook'   => 'wp_foot',
  ]
);
```

Use `am_modify_load_method` to modify the load method of an already-enqueued script.

```php
// Defer an enqueued JavaScript asset.
am_modify_load_method(
  [
    'handle'      => 'footer-script', 
    'load_method' => 'defer',
  ]
);
```

### `am_enqueue_style`

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

### Inline Assets

Use `load_method => inline` for either enqueue function to print the contents of a file to the document head.

**Print the contents of a file**

```php
// Print the contents of this CSS asset into a <style> tag.
// Also works with `am_enqueue_script` for printing a JavaScript asset into a <script> tag.
am_enqueue_style(
  [
    'handle'      => 'inline-styles',
    'src'         => 'css/styles.css',
    'condition'   => 'global',
    'load_method' => 'inline',
  ]
);
```

**Print inline global variables**

Pass an array of values as the `src` to print global JavaScript variables to the document head.

```php
// Add JavaScript values to a property on the `window.amScripts` object.
am_enqueue_script(
  [
    'handle'      => 'inline-vars',
    'src'         => [
      'myGlobalVar' => true,
    ],
    'load_method' => 'inline',
  ]
);
```

The result will be an object added to the `window.amScripts[$handle]` object:

```html
<script class="wp-asset-manager inline-vars" type="text/javascript">window.amScripts = window.amScripts || {}; window.amScripts["inline-vars"] = {"myGlobalVar":true}</script>
```

Use the `am_inline_script_context` filter to change the global object name.

```php
add_filter(
  'am_inline_script_context',
  function() {
    return 'myContext'; // window.myContext[$handle]
  }
);
```

### Enqueue Options

| Name                   | Description                                                         | Default     |
|:-----------------------|:--------------------------------------------------------------------|:-----------:|
| `handle`               | The handle for the asset ❗️                                         |             |
| `src`                  | The URI for the asset ❗️                                            |             |
| `condition`            | The condition for which this asset should load                      | `'global'`  |
| `version`              | The asset version                                                   | `'1.0.0'`   |
| `deps`                 | An array of the asset's dependencies                                | `[]`        |
| `load_hook`            |                                                                     | `'wp_head'` |
| &emsp; — `am_critical` |                                                                     |             |
| &emsp; — `wp_head`     | Load this asset via `wp_head`                                       |             |
| &emsp; — `wp_foot`     | Load this asset via `wp_foot`                                       |             |
| `load_method`          |                                                                     | `'sync'`    |
| &emsp; — `sync`        | Use the core`wp_enqueue` function                                   |             |
| &emsp; — `async`       | Adds the `async` attribute to the enqueue                           |             |
| &emsp; — `defer`       | Adds the `defer` attribute to the enqueue                           |             |
| &emsp; — `async-defer` | Adds the `async` and `defer` attributes to the script tag 📜        |             |
| &emsp; — `inline`      | Prints the asset inline in the document head                        |             |
| `media`                | The media attribute value used to conditionally apply the CSS 🎨    | `'all'`     |

❗️ Required, 📜 Scripts only, 🎨 Styles only

## Preload Function

Use `am_preload` for preloading assets of any [supported type](https://developer.mozilla.org/en-US/docs/Web/HTML/Preloading_content#What_types_of_content_can_be_preloaded).

```php
// `as` and `mime_type` options will be automatically added for CSS, but are included here for clarity.
am_preload(
  [
    'handle'    => 'preload-styles',
    'src'       => 'css/styles.css',
    'condition' => 'global',
    'version'   => '1.0.0',
    'as'        => 'style'
    'mime_type' => 'text/css',
  ]
);
```

Result:

```html
<link rel="preload" href="http://client/css/test.css?ver=1.0.0" class="wp-asset-manager preload-styles" as="style" media="all" type="text/css" />
```

The `am_preload` function patches the `as` and `mime_type` option values for common use-cases (CSS, JavaScript and WOFF2 fonts), but will throw an error if the `as` option is missing for any other file type.

From [the spec](https://www.w3.org/TR/preload/#as-attribute):
> The [as] attribute is necessary to guarantee correct prioritization, request matching, application of the correct Content Security Policy policy, and setting of the appropriate `Accept` request header.

This function will also automatically add the `crossorigin` attribute for fonts, which [is required](https://drafts.csswg.org/css-fonts/#font-fetching-requirements) when preloading fonts, even if they're not actually cross-origin requests.

### Preload Options

| Name          | Description                                                        | Default     |
|:--------------|:-------------------------------------------------------------------|:-----------:|
| `handle`      | The handle for the asset ❗️                                        |             |
| `src`         | The URI for the asset ❗️                                           |             |
| `condition`   | The condition for which this asset should load                     | `'global'`  |
| `version`     | The asset version                                                  | `'1.0.0'`   |
| `as`          | The `as` attribute's value ([info][preload-types]) ❗️              |             |
| `mime_type`   | The `type` attribute's value ([info][mime-types])  ❗️              |             |
| `media`       | The media attribute value used to conditionally preload the asset  | `'all'`     |

❗️ Required

## Requirements

* WordPress: 4.7+
* PHP: 7.0+

## Downloads and Versioning.

You can view [Asset Manager's official releases here](https://github.com/alleyinteractive/wp-asset-manager/releases).

The `develop` branch on GitHub contains the "bleeding edge" releases (alpha, beta, RC). The `production` branch is the latest stable release.

## Contributing to Development

Development of Asset Manager happens on [Github](http://github.com/alleyinteractive/wp-asset-manager). Bugs with Asset Manager should be addressed in the Github issue queue, and enhancements or bug fixes should be submitted as pull requests, which are always welcome.

[preload-types]: https://developer.mozilla.org/en-US/docs/Web/HTML/Preloading_content#What_types_of_content_can_be_preloaded
[mime-types]: https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
