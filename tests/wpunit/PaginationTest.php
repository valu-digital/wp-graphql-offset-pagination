<?php

class PaginationTest extends \Codeception\TestCase\WPTestCase
{
    /**
     * @var \WpunitTester
     */
    protected $tester;

    public function setUp(): void
    {
        // Before...
        parent::setUp();

        // Your set up methods here.
    }

    public function tearDown(): void
    {
        // Your tear down methods here.

        // Then...
        parent::tearDown();
    }

    function createPosts($count)
    {
        foreach (range(0, $count) as $number) {
            $number = str_pad($number, 2, '0', STR_PAD_LEFT);
            $tags = self::factory()->post->create([
                'post_title' => "Post $number",
            ]);
        }
    }

    function createPages($count)
    {
        foreach (range(0, $count) as $number) {
            $number = str_pad($number, 2, '0', STR_PAD_LEFT);
            $tags = self::factory()->post->create([
                'post_type' => 'page',
                'post_title' => "Page $number",
            ]);
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
                    offsetPagination: {postsPerPage: 5}
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
            'Post 00',
            'Post 01',
            'Post 02',
            'Post 03',
            'Post 04',
        ]);
    }

    public function testContentNodesCanMoveOffset()
    {
        $this->createPosts(10);

        $res = graphql([
            'query' => '
            query Posts {
                contentNodes(where: {
                    orderby: {field: TITLE, order: ASC},
                    offsetPagination: {postsPerPage: 5, offset: 1}
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
            ['Post 01', 'Post 02', 'Post 03', 'Post 04', 'Post 05'],
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
                    offsetPagination: {postsPerPage: 15, offset: 5}
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
                'Post 05',
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
            ],
            $titles
        );
    }

    public function testContentNodesCanMixPostTypes()
    {
        $this->createPosts(10);
        $this->createPages(10);

        $res = graphql([
            'query' => '
            query Posts {
                contentNodes(where: {
                    orderby: {field: TITLE, order: ASC},
                    offsetPagination: {postsPerPage: 15, offset: 5}
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
                'Page 05',
                'Page 06',
                'Page 07',
                'Page 08',
                'Page 09',
                'Page 10',
                'Post 00',
                'Post 01',
                'Post 02',
                'Post 03',
                'Post 04',
                'Post 05',
                'Post 06',
                'Post 07',
                'Post 08',
            ],
            $titles
        );
    }
}
