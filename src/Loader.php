<?php

namespace WPGraphQL\Extensions\OffsetPagination;

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
            [$this, 'op_register_types'],
            9,
            0
        );

        add_filter(
            'graphql_map_input_fields_to_wp_query',
            [$this, 'op_map_offset_to_wp_query_args'],
            10,
            2
        );

        add_filter(
            'graphql_map_input_fields_to_wp_user_query',
            [$this, 'op_map_offset_to_wp_user_query_args'],
            10,
            2
        );

        add_filter(
            'graphql_connection_page_info',
            [$this, 'op_graphql_connection_page_info'],
            10,
            2
        );

        add_filter(
            'graphql_connection',
            [$this, 'op_graphql_connection'],
            10,
            2
        );

        add_filter(
            'graphql_post_object_connection_query_args',
            [$this, 'op_graphql_post_object_connection_query_args'],
            10,
            5
        );
    }

    /**
     * Lazily enable total calculations only when they are asked in the
     * selection set.
     */
    function op_graphql_post_object_connection_query_args(
        $query_args,
        $source,
        $args,
        $context,
        \GraphQL\Type\Definition\ResolveInfo $info
    ) {
        $selection_set = $info->getFieldSelection(2);
        if (isset($selection_set['pageInfo']['offsetPagination']['total'])) {
            $query_args['no_found_rows'] = false;
        }
        return $query_args;
    }

    /**
     * By default wp-graphql slices the "nodes" in the connection nodes based
     * on the "first" input field:
     *
     * https://github.com/wp-graphql/wp-graphql/blob/d5089db403c30f634e8de422e32c46074e1f140d/src/Data/Connection/AbstractConnectionResolver.php#L682
     * https://github.com/wp-graphql/wp-graphql/blob/d5089db403c30f634e8de422e32c46074e1f140d/src/Data/Connection/AbstractConnectionResolver.php#L490
     *
     * It defaults to 10 which interferes with offset pagination. This filter
     * restores the nodes to original items when offset pagination is in use.
     */
    function op_graphql_connection(array $connection, $resolver)
    {
        $args = $resolver->get_query_args();
        if (isset($args['graphql_args']['where']['offsetPagination'])) {
            $connection['nodes'] = $resolver->get_items();
        }
        return $connection;
    }

    function op_graphql_connection_page_info($page_info, $resolver)
    {
        $query = $resolver->get_query();
        $page_info['offsetPagination'] = [
            'total' => $query->found_posts,
        ];
        return $page_info;
    }

    function op_map_offset_to_wp_query_args(
        array $query_args,
        array $where_args
    ) {
        if (isset($where_args['offsetPagination']['offset'])) {
            $query_args['offset'] = $where_args['offsetPagination']['offset'];
        }

        if (isset($where_args['offsetPagination']['size'])) {
            $query_args['posts_per_page'] =
                $where_args['offsetPagination']['size'];
        }

        return $query_args;
    }

    function op_map_offset_to_wp_user_query_args(
        array $query_args,
        array $where_args
    ) {
        if (isset($where_args['offsetPagination']['offset'])) {
            $query_args['offset'] = $where_args['offsetPagination']['offset'];
        }

        if (isset($where_args['offsetPagination']['size'])) {
            $query_args['number'] = $where_args['offsetPagination']['size'];
        }

        return $query_args;
    }

    function op_register_types()
    {
        foreach (\WPGraphQL::get_allowed_post_types() as $post_type) {
            $this->add_post_type_fields(get_post_type_object($post_type));
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
    function add_post_type_fields(\WP_Post_Type $post_type_object)
    {
        $type = ucfirst($post_type_object->graphql_single_name);
        register_graphql_fields("RootQueryTo${type}ConnectionWhereArgs", [
            'offsetPagination' => [
                'type' => 'OffsetPagination',
                'description' => "Paginate ${type}s with offsets",
            ],
        ]);
    }
}
