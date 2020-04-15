<?php

class PostPaginationTest extends \Codeception\TestCase\WPTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        register_post_type('test_cpt', [
            'show_ui' => true,
            'labels' => [
                'menu_name' => __('Docs', 'your-textdomain'),
            ],
            'supports' => ['title'],
            'show_in_graphql' => true,
            'hierarchical' => true,
            'graphql_single_name' => 'testCpt',
            'graphql_plural_name' => 'testCpts',
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        \WPGraphQL::clear_schema();
    }

    function createPosts($count, $args = [])
    {
        $title_prefix = 'Post';

        if (isset($args['title_prefix'])) {
            $title_prefix = $args['title_prefix'];
            unset($args['title_prefix']);
        }

        foreach (range(1, $count) as $number) {
            $number = str_pad($number, 2, '0', STR_PAD_LEFT);
            self::factory()->post->create(
                array_merge(
                    [
                        'post_title' => "$title_prefix $number",
                    ],
                    $args
                )
            );
        }
    }

    public function testContentNodesCanLimitPostsPerPAge()
    {
        $this->createPosts(10);

        $res = graphql([
            'query' => '
            query Posts {
                contentNodes(where: {
                    orderby: {field: TITLE, order: ASC},
                    offsetPagination: {size: 5}
                }) {
                nodes {
                    ... on Post {
                    title
                    }
                }
                }
            }
        ',
        ]);

        $nodes = $res['data']['contentNodes']['nodes'];
        $titles = \wp_list_pluck($nodes, 'title');

        $this->assertEquals($titles, [
            'Post 01',
            'Post 02',
            'Post 03',
            'Post 04',
            'Post 05',
        ]);
    }

    public function testContentNodesCanReadTotalViaPageInfo()
    {
        $this->createPosts(10);

        $res = graphql([
            'query' => '
            query Posts {
                contentNodes(where: {
                    orderby: {field: TITLE, order: ASC},
                    offsetPagination: {size: 5}
                }) {
                pageInfo {
                    offsetPagination {
                        total
                    }
                }
                nodes {
                    ... on Post {
                        title
                     }
                   }
                }
            }
        ',
        ]);

        $total =
            $res['data']['contentNodes']['pageInfo']['offsetPagination'][
                'total'
            ];
        $this->assertEquals(10, $total);
    }

    public function testContentNodesCanMoveOffset()
    {
        $this->createPosts(10);

        $res = graphql([
            'query' => '
            query Posts {
                contentNodes(where: {
                    orderby: {field: TITLE, order: ASC},
                    offsetPagination: {size: 5, offset: 1}
                }) {
                nodes {
                    ... on Post {
                    title
                    }
                }
                }
            }
        ',
        ]);

        $nodes = $res['data']['contentNodes']['nodes'];
        $titles = \wp_list_pluck($nodes, 'title');

        $this->assertEquals(5, count($titles));
        $this->assertEquals(
            ['Post 02', 'Post 03', 'Post 04', 'Post 05', 'Post 06'],
            $titles
        );
    }

    public function testConentNodesCanHavePageBiggerThanDefaultCursor()
    {
        $this->createPosts(25);

        $res = graphql([
            'query' => '
            query Posts {
                contentNodes(where: {
                    orderby: {field: TITLE, order: ASC},
                    offsetPagination: {size: 15, offset: 5}
                }) {
                nodes {
                    ... on Post {
                    title
                    }
                }
                }
            }
        ',
        ]);

        $nodes = $res['data']['contentNodes']['nodes'];
        $titles = \wp_list_pluck($nodes, 'title');

        $this->assertEquals(15, count($titles));
        $this->assertEquals(
            [
                'Post 06',
                'Post 07',
                'Post 08',
                'Post 09',
                'Post 10',
                'Post 11',
                'Post 12',
                'Post 13',
                'Post 14',
                'Post 15',
                'Post 16',
                'Post 17',
                'Post 18',
                'Post 19',
                'Post 20',
            ],
            $titles
        );
    }

    public function testContentNodesCanMixPostTypes()
    {
        $this->createPosts(10);
        $this->createPosts(10, [
            'post_type' => 'page',
            'title_prefix' => 'Page',
        ]);

        $res = graphql([
            'query' => '
            query Posts {
                contentNodes(where: {
                    orderby: {field: TITLE, order: ASC},
                    offsetPagination: {size: 15, offset: 5}
                }) {
                nodes {
                    ... on Post {
                        title
                    }
                    ... on Page {
                        title
                    }
                  }
                }
            }
        ',
        ]);

        $nodes = $res['data']['contentNodes']['nodes'];
        $titles = \wp_list_pluck($nodes, 'title');

        $this->assertEquals(15, count($titles));
        $this->assertEquals(
            [
                'Page 06',
                'Page 07',
                'Page 08',
                'Page 09',
                'Page 10',
                'Post 01',
                'Post 02',
                'Post 03',
                'Post 04',
                'Post 05',
                'Post 06',
                'Post 07',
                'Post 08',
                'Post 09',
                'Post 10',
            ],
            $titles
        );
    }

    public function testContentNodesCanFilterAndPaginate()
    {
        $this->createPosts(10);
        $this->createPosts(10, [
            'post_type' => 'page',
            'title_prefix' => 'Page',
        ]);

        $res = graphql([
            'query' => '
            query Posts {
                contentNodes(where: {
                    orderby: {field: TITLE, order: ASC},
                    offsetPagination: {size: 5, offset: 2},
                    contentTypes: [POST]
                }) {
                nodes {
                    ... on Post {
                        title
                    }
                    ... on Page {
                        title
                    }
                  }
                }
            }
        ',
        ]);

        $nodes = $res['data']['contentNodes']['nodes'];
        $titles = \wp_list_pluck($nodes, 'title');

        $this->assertEquals(5, count($titles));
        $this->assertEquals(
            ['Post 03', 'Post 04', 'Post 05', 'Post 06', 'Post 07'],
            $titles
        );
    }

    public function testPostsCanLimitPostsPerPage()
    {
        $this->createPosts(10);

        $res = graphql([
            'query' => '
            query Posts {
                posts(where: {
                    orderby: {field: TITLE, order: ASC},
                    offsetPagination: {size: 5}
                }) {
                nodes {
                    title
                }
              }
            }
        ',
        ]);

        $nodes = $res['data']['posts']['nodes'];
        $titles = \wp_list_pluck($nodes, 'title');

        $this->assertEquals($titles, [
            'Post 01',
            'Post 02',
            'Post 03',
            'Post 04',
            'Post 05',
        ]);
    }

    public function testPostsMoveOffset()
    {
        $this->createPosts(10);

        $res = graphql([
            'query' => '
            query Posts {
                posts(where: {
                    orderby: {field: TITLE, order: ASC},
                    offsetPagination: {size: 5, offset: 2}
                }) {
                nodes {
                    title
                }
              }
            }
        ',
        ]);

        $nodes = $res['data']['posts']['nodes'];
        $titles = \wp_list_pluck($nodes, 'title');

        $this->assertEquals($titles, [
            'Post 03',
            'Post 04',
            'Post 05',
            'Post 06',
            'Post 07',
        ]);
    }

    public function testCPTCanLimitPostsPerPage()
    {
        $this->createPosts(10, [
            'post_type' => 'test_cpt',
            'title_prefix' => 'Test CPT',
        ]);

        $res = graphql([
            'query' => '
            query Posts {
                testCpts(where: {
                    orderby: {field: TITLE, order: ASC},
                    offsetPagination: {size: 5}
                }) {
                nodes {
                    title
                }
              }
            }
        ',
        ]);

        $this->assertEquals('', $res['errors'][0]['message'] ?? '');
        $nodes = $res['data']['testCpts']['nodes'];
        $titles = \wp_list_pluck($nodes, 'title');

        $this->assertEquals($titles, [
            'Test CPT 01',
            'Test CPT 02',
            'Test CPT 03',
            'Test CPT 04',
            'Test CPT 05',
        ]);
    }

    public function testCPTCanMoveOffset()
    {
        $this->createPosts(10, [
            'post_type' => 'test_cpt',
            'title_prefix' => 'Test CPT',
        ]);

        $res = graphql([
            'query' => '
            query Posts {
                testCpts(where: {
                    orderby: {field: TITLE, order: ASC},
                    offsetPagination: {size: 5, offset: 2}
                }) {
                nodes {
                    title
                }
              }
            }
        ',
        ]);

        $nodes = $res['data']['testCpts']['nodes'];
        $titles = \wp_list_pluck($nodes, 'title');

        $this->assertEquals($titles, [
            'Test CPT 03',
            'Test CPT 04',
            'Test CPT 05',
            'Test CPT 06',
            'Test CPT 07',
        ]);
    }
}
