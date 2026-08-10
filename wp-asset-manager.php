<?php
/**
 * Plugin Name: Asset Manager
 * Plugin URI: https://github.com/alleyinteractive/wp-asset-manager
 * Description: Asset Manager is a toolkit for managing front-end assets and more tightly controlling where, when, and how they're loaded.
 * Author: Alley Interactive
 * Author URI: https://alley.com
 * Version: 2.0.0
 * Requires at least: 6.3
 * Requires PHP: 8.3
 * License: GPLv2 or later
 * Text Domain: wp-asset-manager
 *
 * @package Asset_Manager
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// Require the main plugin file left as the original name for backwards compatibility.
require_once __DIR__ . '/asset-manager.php';
