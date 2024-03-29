# WordPress Asset Manager

Asset Manager is a toolkit for managing front-end assets and more tightly controlling where, when, and how they're loaded.

* [Using Asset Manager in your WordPress Project](#using-asset-manager-in-your-wordpress-project)
* [Enqueue Functions](#enqueue-functions)
  * [am_enqueue_script](#am_enqueue_script)
  * [am_enqueue_style](#am_enqueue_style)
  * [Conditions](#conditions)
  * [Inline Assets](#inline-assets)
  * [Enqueue Options](#enqueue-options)
* [Preload Function](#preload-function)
  * [Preload Options](#preload-options)
* [SVG Sprite](#svg-sprite)
  * [Setup](#setup)
    * [Admin pages](#admin-pages)
  * [Registering Symbols](#registering-symbols)
  * [Verifying a symbol is registered](#verifying-a-symbol-is-registered)
  * [Changing the SVG Directory](#changing-the-svg-directory)
  * [Setting Global Attributes](#setting-global-attributes)
  * [Update `$sprite_allowed_tags`](#update-sprite_allowed_tags)
  * [Removing a Symbol](#removing-a-symbol)
    * [Replacing a Symbol](#replacing-a-symbol)
  * [Displaying a Symbol](#displaying-a-symbol)
    * [Notes on SVG sizing](#notes-on-svg-sizing)
  * [Getting a Symbol](#getting-a-symbol)
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
    'load_hook'   => 'wp_footer',
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

### Conditions

The `condition` parameter determines under which condition(s) the asset should load.

**`include`**

> `string|array`
> 
> Requires that all conditions be truthy in order for the asset to load.

The `include` property is implied if the `condition` parameter is a string or array of strings; otherwise the `condition` parameter must contain the `include` property.

**`include_any`**

> `string|array`
> 
> Allows for _any_ condition to be truthy, instead of requiring that all conditions be.

**`exclude`**

> `string|array`
> 
> Requires that all conditions be falsey in order for the asset to load. This is skipped if neither `include` nor `include_any` are truthy.

#### Custom Conditions

There are a few default conditions included out-of-the-box:

| Name       | Condition     |
|:-----------|---------------|
| `'global'` | `true`        |
| `'single'` | `is_single()` |
| `'search'` | `is_search()` |

Use the `am_asset_conditions` filter to add or replace conditions.

```php
function asset_conditions( $conditions ) {
  return array_merge(
    $conditions,
    [
      'home'    => ( is_home() || is_front_page() ),
      'archive' => is_archive(),
      'page'    => is_page(),
    ]
  );
}

add_filter( 'am_asset_conditions', 'asset_conditions', 10 );
```

### Inline Assets

Use `load_method => inline` with an absolute `src` path for either enqueue function to print the contents of the file to the document head.

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

The `am_enqueue_*` functions use the same parameters as their core WordPress enqueue equivelant, with the exception of the `$in_footer` parameter for scripts; use `'load_hook'` (details below) instead.

**Additional options:**

| Name                   | Description                                                         | Default     |
|:-----------------------|:--------------------------------------------------------------------|:-----------:|
| `condition`            | The condition for which this asset should load                      | `'global'`  |
| `load_hook`            |                                                                     | `'wp_head'` |
| &emsp; — `wp_head`     | Load this asset via `wp_head`                                       |             |
| &emsp; — `wp_footer`   | Load this asset via `wp_footer`                                     |             |
| `load_method`          |                                                                     | `'sync'`    |
| &emsp; — `sync`        | Use the core`wp_enqueue` function                                   |             |
| &emsp; — `async`       | Adds the `async` attribute to the enqueue                           |             |
| &emsp; — `defer`       | Adds the `defer` attribute to the enqueue                           |             |
| &emsp; — `async-defer` | Adds the `async` and `defer` attributes (scripts only)              |             |
| &emsp; — `inline`      | Prints the asset inline in the document head                        |             |

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

| Name          | Description                                                        | Required    | Default     |
|:--------------|:-------------------------------------------------------------------|:-----------:|:-----------:|
| `handle`      | The handle for the asset                                           | •           |             |
| `src`         | The URI for the asset                                              | •           |             |
| `condition`   | The condition for which this asset should load                     |             | `'global'`  |
| `version`     | The asset version                                                  |             | `'1.0.0'`   |
| `as`          | The `as` attribute's value ([info][preload-types])                 | •           |             |
| `mime_type`   | The `type` attribute's value ([info][mime-types])                  | •           |             |
| `media`       | The media attribute value used to conditionally preload the asset  |             | `'all'`     |

## SVG Sprite

Allows for fine-grained control over SVG assets in WordPress templates by creating a sprite of registered symbols and providing helper functions for displaying them.

Asset Manager will add an SVG file's contents to the sprite if:

1. The symbol is registered via `am_register_symbol` with a unique handle and valid file path
2. The symbol's `condition` is truthy

See [Conditions](#conditions) for more about Asset Manager's conditions and how to update them.

### Setup

The sprite is printed via the [`wp_body_open`](https://developer.wordpress.org/reference/hooks/wp_body_open/) hook, so be sure your templates have the [wp_body_open()](https://developer.wordpress.org/reference/functions/wp_body_open/) function at the top of the document's `<body>` element.

#### Admin pages

Prints the sprite to admin pages via the `in_admin_header` hook, which is the most similar admin hook to `wp_body_open`. Symbols registered with condition(s) not matching admin pages will not be added to the sprite.

```php
/**
 * Add SVG symbols to the admin page content.
 */
function print_admin_sprite() {
  if ( class_exists( 'Asset_Manager_SVG_Sprite' ) ) {
    Asset_Manager_SVG_Sprite::instance()->print_sprite_sheet();
  }
}
add_action( 'in_admin_header', 'print_admin_sprite' );
```

### Registering Symbols

Use the `am_register_symbol` function to add a symbol to the sprite. Like `wp_register_script` and `wp_register_style`, an attempt to re-register a symbol with an existing handle will be ignored.

**Symbols should be registered via an action that fires before [`wp_body_open`](https://developer.wordpress.org/reference/hooks/wp_body_open/).**

```php
am_register_symbol(
  [
    'handle'    => 'logomark',
    'src'       => 'svg/logomark.svg',
    'condition' => 'global',
  ]
);
```

Options can be passed in as an array or individual parameters.

**`$handle`**

> `string`
>
> Handle for the asset. Should be unique.

**`$src`** 

> `string`
>
> Absolute path to the SVG file, or a relative path based on the current theme root. Use the `am_modify_svg_directory` filter to update the directory from which relative paths will be completed.

**`$condition`** 

> `string|array`
>
> Loading condition(s) that, when matched, will allow the asset to be added to the sprite.

**`$attributes`** 

> `array`
>
> An array of HTML attribute names and values to add to the resulting `<svg>` everywhere it is rendered.

### Verifying a symbol is registered

```php
am_symbol_is_registered( $handle = '' );
```

**`$handle`**

> `string`
> 
> The handle with which the symbol should be registered.

**Return**

> `bool`
>
> Whether the symbol has been registered.

### Changing the SVG directory

Use the `am_modify_svg_directory` filter to update the directory from which relative paths will be completed.

**Default**: The current theme root.

```php
add_filter(
  'am_modify_svg_directory',
  function( $theme_root ) {
    return $theme_root . '/svg/';
  }
);
```

### Setting Global Attributes

Use the `am_global_svg_attributes` filter to add global attributes that will apply to all symbols.

**Default**: `[]`

```php
add_filter(
  'am_global_svg_attributes',
  function() {
    return [
      'aria-hidden' => 'true',
      'focusable'   => 'false',
    ];
  }
);
```

### Update `$sprite_allowed_tags`

Use the `am_sprite_allowed_tags` to filter [elements and attributes](php/kses-svg.php) used in escaping the sprite, such as certain deprecated attributes, script tags, and event handlers.

```php
add_filter(
  'am_sprite_allowed_tags',
  function( $sprite_allowed_tags ) {
    $sprite_allowed_tags['path']['onclick'] = true;

    return $sprite_allowed_tags;
  }
);
```

### Removing a Symbol

Use `am_deregister_symbol` to remove a registered a symbol.

This should be added via an action that fires after, or at a lower priority, than the action used for `am_register_symbol`.

```php
am_deregister_symbol( $handle = '' );
```

**`$handle`**

> `string`
> 
> The handle with which the symbol was registered.

**Return**

> `bool`
>
> Whether the symbol has been deregistered and removed from the sprite. `true` on success, or if the symbol hadn't been previously registered; `false` on failure.

#### Replacing a symbol

Prior to re-registering a symbol, verify the symbol to be replaced is not registered.

```php
if ( am_deregister_symbol( 'logomark' ) ) {
  am_register_symbol(
    [
      'handle'    => 'logomark',
      'src'       => 'svg/logomark-alt.svg',
      'condition' => 'global',
    ]
  );
}
```

### Displaying a Symbol

`am_use_symbol` prints an `<svg>` element with the specified attributes.

```php
am_use_symbol( $handle = '', $attributes = [] );
```

**`$handle`**

> `string`
> 
> The handle with which the symbol was registered.

**`$attributes`**

> `array` 
> 
> An array of attribute-value pairs to add to the resulting SVG markup.
> 
> Override global attributes, or those defined via `am_register_symbol`, by declaring a new value here; remove it entirely by passing a falsy value.

#### Notes on SVG sizing

Asset Manager will attempt to establish a default size for each SVG, which will be used to calculate the dimensions if only one, or neither, of `height` or `width` is passed to `am_use_symbol`.

The default size is based on (in order):
1. The values set in the symbol's `am_register_symbol` attributes array
1. The `height` and `width` attributes from the SVG
1. The `viewBox` attribute values

If Asset Manager cannot determine a symbol's dimensions, both `height` _and_ `width` will need to be declared in the `attributes` array passed to `am_use_symbol`.

**The simplest way to ensure SVGs are sized as expected is to verify each file's `<svg>` element either has both `height` and `width` attributes or a viewBox attribute.**

_**Example**_:

```php
am_use_symbol(
  'logomark',
  [
    'width' => 200,
    'class' => 'header-logo',
  ]
);
```

_**Output**_:

```html
<svg width="200" height="27.67" class="header-logo" aria-hidden="true" focusable="false">
  <use href="#am-symbol-logomark"></use>
</svg>
```

### Getting a Symbol

`am_get_symbol` returns a string containing the `<svg>` element with the specified attributes.

```php
$symbol_markup = am_get_symbol( $handle = '', $attributes = [] );
```

This function uses the same arguments as `am_use_symbol`.

_**Example**_:

```php
$logomark_svg_markup = am_get_symbol(
  'logomark',
  [
    'width' => 200,
    'class' => 'header-logo',
  ]
);
```

## Requirements

* WordPress: 5.2.0+
* PHP: 7.4+

## Downloads and Versioning.

You can view [Asset Manager's official releases here](https://github.com/alleyinteractive/wp-asset-manager/releases).

The `develop` branch on GitHub contains the "bleeding edge" releases (alpha, beta, RC). The `production` branch is the latest stable release.

## Contributing to Development

Development of Asset Manager happens on [Github](http://github.com/alleyinteractive/wp-asset-manager). Bugs with Asset Manager should be addressed in the Github issue queue, and enhancements or bug fixes should be submitted as pull requests, which are always welcome.

[preload-types]: https://developer.mozilla.org/en-US/docs/Web/HTML/Preloading_content#What_types_of_content_can_be_preloaded
[mime-types]: https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
