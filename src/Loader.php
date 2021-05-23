<?php

namespace WPGraphQL\Extensions\OffsetPagination;

use WPGraphQL\Data\Connection\AbstractConnectionResolver;
use WPGraphQL\Data\Connection\UserConnectionResolver;

class Loader
{
    public static function init()
    {
        define('WP_GRAPHQL_OFFSET_PAGINATION', 'initialized');
        (new Loader())->bind_hooks();
    }

    function bind_hooks()
    {
        add_action(
            'graphql_register_types',
            [$this, 'op_action_register_types'],
            9,
            0
        );

        add_filter(
            'graphql_map_input_fields_to_wp_query',
            [$this, 'op_filter_map_offset_to_wp_query_args'],
            10,
            2
        );

        add_filter(
            'graphql_map_input_fields_to_wp_user_query',
            [$this, 'op_filter_map_offset_to_wp_user_query_args'],
            10,
            2
        );

        add_filter(
            'graphql_connection_page_info',
            [$this, 'op_filter_graphql_connection_page_info'],
            10,
            2
        );

        add_filter(
            'graphql_connection_query_args',
            [$this, 'op_filter_graphql_connection_query_args'],
            10,
            5
        );

        add_filter(
            'graphql_connection_amount_requested',
            [$this, 'op_filter_graphql_connection_amount_requested'],
            10,
            2
        );
    }

    function op_filter_graphql_connection_amount_requested($amount, $resolver)
    {
        if (self::is_offset_resolver($resolver)) {
            return self::get_page_size($resolver);
        }

        return $amount;
    }

    /**
     * Returns true when the resolver is resolving offset pagination
     */
    static function get_page_size(AbstractConnectionResolver $resolver)
    {
        $args = $resolver->getArgs();
        return intval($args['where']['offsetPagination']['size'] ?? 0);
    }

    static function is_offset_resolver(AbstractConnectionResolver $resolver)
    {
        $args = $resolver->getArgs();
        return isset($args['where']['offsetPagination']);
    }

    /**
     * Lazily enable total calculations only when they are asked in the
     * selection set.
     */
    function op_filter_graphql_connection_query_args(
        $query_args,
        AbstractConnectionResolver $resolver
    ) {
        $info = $resolver->getInfo();
        $selection_set = $info->getFieldSelection(2);

        if (!isset($selection_set['pageInfo']['offsetPagination']['total'])) {
            // get out if not requesting total counting
            return $query_args;
        }

        if ($resolver instanceof UserConnectionResolver) {
            // Enable slow total counting for user connections
            $query_args['count_total'] = true;
        } else {
            // Enable slow total counting for posts connections
            $query_args['no_found_rows'] = false;
        }

        return $query_args;
    }

    static function add_post_type_fields(\WP_Post_Type $post_type_object)
    {
        $type = ucfirst($post_type_object->graphql_single_name);
        register_graphql_fields("RootQueryTo${type}ConnectionWhereArgs", [
            'offsetPagination' => [
                'type' => 'OffsetPagination',
                'description' => "Paginate ${type}s with offsets",
            ],
        ]);
    }

    function op_filter_graphql_connection_page_info(
        $page_info,
        AbstractConnectionResolver $resolver
    ) {
        $size = self::get_page_size($resolver);
        $query = $resolver->get_query();
        $args = $resolver->getArgs();
        $offset = $args['where']['offsetPagination']['offset'] ?? 0;

        $total = null;

        if ($query instanceof \WP_Query) {
            $total = $query->found_posts;
        } elseif ($query instanceof \WP_User_Query) {
            $total = $query->total_users;
        }

        $page_info['offsetPagination'] = [
            'total' => $total,
            'hasMore' => count($resolver->get_ids()) > $size,
            'hasPrevious' => $offset > 0,
        ];
        return $page_info;
    }

    function op_filter_map_offset_to_wp_query_args(
        array $query_args,
        array $where_args
    ) {
        if (isset($where_args['offsetPagination']['offset'])) {
            $query_args['offset'] = $where_args['offsetPagination']['offset'];
        }

        if (isset($where_args['offsetPagination']['size'])) {
            // Fetch size+1 to be able calculate "hasMore" field without
            // slowly counting full totals.
            $query_args['posts_per_page'] =
                intval($where_args['offsetPagination']['size']) + 1;
        }

        return $query_args;
    }

    function op_filter_map_offset_to_wp_user_query_args(
        array $query_args,
        array $where_args
    ) {
        if (isset($where_args['offsetPagination']['offset'])) {
            $query_args['offset'] = $where_args['offsetPagination']['offset'];
        }

        if (isset($where_args['offsetPagination']['size'])) {
            $query_args['number'] =
                intval($where_args['offsetPagination']['size']) + 1;
        }

        return $query_args;
    }

    function op_action_register_types()
    {
        foreach (\WPGraphQL::get_allowed_post_types() as $post_type) {
            self::add_post_type_fields(get_post_type_object($post_type));
        }

        register_graphql_object_type('OffsetPaginationPageInfo', [
            'description' => __(
                'Get information about the offset pagination state',
                'wp-graphql-offset-pagination'
            ),
            'fields' => [
                'total' => [
                    'type' => 'Int',
                    'description' => __(
                        'Total amount of nodes in this connection',
                        'wp-graphql-offset-pagination'
                    ),
                ],
                'hasMore' => [
                    'type' => 'Boolean',
                    'description' => __(
                        'True if there is one or more nodes available in this connection. Eg. you can increase the offset at least by one.',
                        'wp-graphql-offset-pagination'
                    ),
                ],
                'hasPrevious' => [
                    'type' => 'Boolean',
                    'description' => __(
                        'True when offset can be decresed eg. offset is 0<',
                        'wp-graphql-offset-pagination'
                    ),
                ],
            ],
        ]);

        register_graphql_field('WPPageInfo', 'offsetPagination', [
            'type' => 'OffsetPaginationPageInfo',
            'description' => __(
                'Get information about the offset pagination state in the current connection',
                'wp-graphql-offset-pagination'
            ),
        ]);

        register_graphql_input_type('OffsetPagination', [
            'description' => __(
                'Offset pagination input type',
                'wp-graphql-offet-pagination'
            ),
            'fields' => [
                'size' => [
                    'type' => 'Int',
                    'description' => __(
                        'Number of post to show per page. Passed to posts_per_page of WP_Query.',
                        'wp-graphql-offset-pagination'
                    ),
                ],
                'offset' => [
                    'type' => 'Int',
                    'description' => __(
                        'Number of post to show per page. Passed to posts_per_page of WP_Query.',
                        'wp-graphql-offset-pagination'
                    ),
                ],
            ],
        ]);

        register_graphql_field(
            'RootQueryToContentNodeConnectionWhereArgs',
            'offsetPagination',
            [
                'type' => 'OffsetPagination',
                'description' => 'Paginate content nodes with offsets',
            ]
        );

        register_graphql_field(
            'RootQueryToUserConnectionWhereArgs',
            'offsetPagination',
            [
                'type' => 'OffsetPagination',
                'description' => 'Paginate users with offsets',
            ]
        );
    }
}
