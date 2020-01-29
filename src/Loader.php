<?php

namespace WPGraphQL\Extensions\OffsetPagination;

use WPGraphQL\Data\Connection\AbstractConnectionResolver;

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

        add_filter('graphql_connection_nodes', [$this, 'op_get_nodes'], 10, 2);
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
     * Returns true when the resolver is resolving offset pagination
     */
    function get_page_size(AbstractConnectionResolver $resolver)
    {
        $args = $resolver->get_query_args();
        $size = $args['graphql_args']['where']['offsetPagination']['size'] ?? 0;
        return intval($size);
    }

    function get_offset_nodes(AbstractConnectionResolver $resolver)
    {
        $size = $this->get_page_size($resolver);
        return array_slice($resolver->get_items(), 0, $size);
    }

    function op_get_nodes($nodes, AbstractConnectionResolver $resolver)
    {
        if ($this->is_offset_resolver($resolver)) {
            return $this->get_offset_nodes($resolver);
        }

        return $nodes;
    }

    function is_offset_resolver(AbstractConnectionResolver $resolver)
    {
        $args = $resolver->get_query_args();
        return isset($args['graphql_args']['where']['offsetPagination']);
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
    function op_graphql_connection(
        array $connection,
        AbstractConnectionResolver $resolver
    ) {
        if ($this->is_offset_resolver($resolver)) {
            $connection['nodes'] = $this->get_offset_nodes($resolver);
        }

        return $connection;
    }

    function op_graphql_connection_page_info(
        $page_info,
        AbstractConnectionResolver $resolver
    ) {
        $size = $this->get_page_size($resolver);
        $query = $resolver->get_query();
        $args = $resolver->get_query_args();
        $offset = $args['offsetPagination']['offset'] ?? 0;
        $page_info['offsetPagination'] = [
            'total' => $query->found_posts,
            'hasMore' => count($resolver->get_items()) > $size,
            'hasPrevious' => $offset > 0,
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
            // Fetch size+1 to be able calculate "hasMore" field without
            // slowly counting full totals.
            $query_args['posts_per_page'] =
                intval($where_args['offsetPagination']['size']) + 1;
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
