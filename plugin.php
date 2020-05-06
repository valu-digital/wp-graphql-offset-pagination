<?php
/**
 * Plugin Name: WPGraphQL Offset Pagination
 * Plugin URI: https://github.com/valu-digital/wp-graphql-offset-pagination
 * Description: Adds offset pagination to the wp-graphql plugin
 * Author: Esa-Matti Suuronen, Valu Digital Oy
 * Version: 0.2.0
 *
 */

// To make this plugin work properly for both Composer users and non-composer
// users we must detect whether the project is using a global autolaoder. We
// can do that by checking whether our autoloadable classes will autoload with
// class_exists(). If not it means there's no global autoloader in place and
// the user is not using composer. In that case we can safely require the
// bundled autoloader code.
if (!\class_exists('\WPGraphQL\Extensions\OffsetPagination')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load the actual plugin code
\WPGraphQL\Extensions\OffsetPagination\Loader::init();
