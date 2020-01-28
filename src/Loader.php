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
            [$this, 'op_map_offset_to_query_args'],
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
    }

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
        $page_info['total'] = $query->found_posts;

        // $page_info['previousPage'] = null;
        // $page_info['nextPage'] = null;
        // $page_info['totalPages'] = null;
        // $page_info['startCursor'] = null;
        // $page_info['endCursor'] = null;

        return $page_info;
    }

    function op_map_offset_to_query_args(array $query_args, array $where_args)
    {
        if (isset($where_args['offsetPagination']['offset'])) {
            $query_args['offset'] = $where_args['offsetPagination']['offset'];
        }

        if (isset($where_args['offsetPagination']['postsPerPage'])) {
            $query_args['posts_per_page'] =
                $where_args['offsetPagination']['postsPerPage'];
        }

        $query_args['no_found_rows'] = false;

        return $query_args;
    }

    function op_register_types()
    {
        foreach (\WPGraphQL::get_allowed_post_types() as $post_type) {
            $this->add_post_type_fields(get_post_type_object($post_type));
        }

        register_graphql_field('WPPageInfo', 'total', [
            'type' => 'Int',
        ]);

        register_graphql_input_type('OffsetPagination', [
            'description' => __('lala', 'wp-graphql-offet-pagination'),
            'fields' => [
                'postsPerPage' => [
                    'type' => 'Int',
                    'description' => __(
                        'Number of post to show per page. Passed to posts_per_page of WP_Query.',
                        'wp-graphql-offet-pagination'
                    ),
                ],
                'offset' => [
                    'type' => 'Int',
                    'description' => __(
                        'Number of post to show per page. Passed to posts_per_page of WP_Query.',
                        'wp-graphql-offet-pagination'
                    ),
                ],
            ],
        ]);

        register_graphql_field(
            'RootQueryToContentNodeConnectionWhereArgs',
            'offsetPagination',
            [
                'type' => 'OffsetPagination',
                'description' => 'wat',
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
