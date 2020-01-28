<?php

namespace WPGraphQL\Extensions\OffsetPagination;

class Loader {
    public static function init() {
        define( 'WP_GRAPHQL_OFFSET_PAGINATION', 'initialized' );
        error_log("WP_GRAPHQL_OFFSET_PAGINATION initialized");
    }
}