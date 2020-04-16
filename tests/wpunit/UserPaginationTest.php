<?php

class UserPaginationTest extends \Codeception\TestCase\WPTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        \WPGraphQL::clear_schema();
    }

    function createUsers($count, $args = [])
    {
        $name_prefix = 'test-user';

        if (isset($args['name_prefix'])) {
            $name_prefix = $args['name_prefix'];
            unset($args['name_prefix']);
        }

        foreach (range(1, $count) as $number) {
            $number = str_pad($number, 2, '0', STR_PAD_LEFT);
            self::factory()->user->create(
                array_merge(
                    [
                        'user_login' => "${name_prefix}-${number}",
                    ],
                    $args
                )
            );
        }
    }

    public function testUsersCanLimit()
    {
        $this->createUsers(10);
        wp_set_current_user(get_user_by('login', 'admin')->ID);

        $res = graphql([
            'query' => '
            query Users {
                users(where: {
                    orderby: {field: DISPLAY_NAME, order: ASC},
                    offsetPagination: {size: 5}
                }) {
                nodes {
                    name
                   }
                }
            }
        ',
        ]);

        $this->assertEquals('', $res['errors'][0]['message'] ?? '');
        $nodes = $res['data']['users']['nodes'];
        $names = \wp_list_pluck($nodes, 'name');

        $this->assertEquals(5, count($names));
        $this->assertEquals(
            [
                'admin',
                'test-user-01',
                'test-user-02',
                'test-user-03',
                'test-user-04',
            ],
            $names
        );
    }

    public function testUsersCannotBeReadWithoutAuth()
    {
        $this->createUsers(2);

        $res = graphql([
            'query' => '
            query Users {
                users(where: {
                    orderby: {field: DISPLAY_NAME, order: ASC},
                    offsetPagination: {size: 5}
                }) {
                nodes {
                    name
                   }
                }
            }
        ',
        ]);

        $this->assertEquals('', $res['errors'][0]['message'] ?? '');
        $nodes = $res['data']['users']['nodes'];
        $names = \wp_list_pluck($nodes, 'name');

        $this->assertEquals(0, count($names));
    }

    public function testUsersSetOffset()
    {
        $this->createUsers(10);
        wp_set_current_user(get_user_by('login', 'admin')->ID);

        $res = graphql([
            'query' => '
            query Users {
                users(where: {
                    orderby: {field: DISPLAY_NAME, order: ASC},
                    offsetPagination: {size: 5, offset: 3}
                }) {
                nodes {
                    name
                   }
                }
            }
        ',
        ]);

        $this->assertEquals('', $res['errors'][0]['message'] ?? '');
        $nodes = $res['data']['users']['nodes'];
        $names = \wp_list_pluck($nodes, 'name');

        $this->assertEquals(5, count($names));
        $this->assertEquals(
            [
                'test-user-03',
                'test-user-04',
                'test-user-05',
                'test-user-06',
                'test-user-07',
            ],
            $names
        );
    }

    public function testUsersLongOffset()
    {
        $this->createUsers(20);
        wp_set_current_user(get_user_by('login', 'admin')->ID);

        $res = graphql([
            'query' => '
            query Users {
                users(where: {
                    orderby: {field: DISPLAY_NAME, order: ASC},
                    offsetPagination: {size: 5, offset: 15}
                }) {
                nodes {
                    name
                   }
                }
            }
        ',
        ]);

        $this->assertEquals('', $res['errors'][0]['message'] ?? '');
        $nodes = $res['data']['users']['nodes'];
        $names = \wp_list_pluck($nodes, 'name');

        $this->assertEquals(5, count($names));
        $this->assertEquals(
            [
                'test-user-15',
                'test-user-16',
                'test-user-17',
                'test-user-18',
                'test-user-19',
            ],
            $names
        );
    }

    public function testHasMoreWithoutOffset()
    {
        $this->createUsers(10);
        wp_set_current_user(1);
        wp_set_current_user(get_user_by('login', 'admin')->ID);

        $res = graphql([
            'query' => '
            query Users {
                users(where: {
                    orderby: {field: DISPLAY_NAME, order: ASC},
                    offsetPagination: {size: 5}
                }) {
                pageInfo {
                    offsetPagination {
                        hasMore
                    }
                }
                nodes {
                    name
                   }
                }
            }
        ',
        ]);

        $this->assertEquals('', $res['errors'][0]['message'] ?? '');
        $nodes = $res['data']['users']['nodes'];
        $names = \wp_list_pluck($nodes, 'name');

        $this->assertEquals(5, count($names));
        $this->assertEquals(
            [
                'admin',
                'test-user-01',
                'test-user-02',
                'test-user-03',
                'test-user-04',
            ],
            $names
        );

        $has_more =
            $res['data']['users']['pageInfo']['offsetPagination']['hasMore'];
        $this->assertEquals(true, $has_more);
    }

    public function testHasMoreWithOffsetOne()
    {
        $this->createUsers(10);
        wp_set_current_user(get_user_by('login', 'admin')->ID);

        $res = graphql([
            'query' => '
            query Users {
                users(where: {
                    orderby: {field: DISPLAY_NAME, order: ASC},
                    offsetPagination: {size: 5, offset: 1}
                }) {
                pageInfo {
                    offsetPagination {
                        hasMore
                    }
                }
                nodes {
                    name
                   }
                }
            }
        ',
        ]);

        $this->assertEquals('', $res['errors'][0]['message'] ?? '');
        $nodes = $res['data']['users']['nodes'];
        $names = \wp_list_pluck($nodes, 'name');

        $this->assertEquals(5, count($names));
        $this->assertEquals(
            [
                'test-user-01',
                'test-user-02',
                'test-user-03',
                'test-user-04',
                'test-user-05',
            ],
            $names
        );

        $has_more =
            $res['data']['users']['pageInfo']['offsetPagination']['hasMore'];
        $this->assertEquals(true, $has_more);
    }

    public function testHasMoreFalseAtEnd()
    {
        $this->createUsers(10);
        wp_set_current_user(get_user_by('login', 'admin')->ID);

        $res = graphql([
            'query' => '
            query Users {
                users(where: {
                    orderby: {field: DISPLAY_NAME, order: ASC},
                    offsetPagination: {size: 5, offset: 6}
                }) {
                pageInfo {
                    offsetPagination {
                        hasMore
                    }
                }
                nodes {
                    name
                   }
                }
            }
        ',
        ]);

        $this->assertEquals('', $res['errors'][0]['message'] ?? '');
        $nodes = $res['data']['users']['nodes'];
        $names = \wp_list_pluck($nodes, 'name');

        $this->assertEquals(
            [
                'test-user-06',
                'test-user-07',
                'test-user-08',
                'test-user-09',
                'test-user-10',
            ],
            $names
        );

        $has_more =
            $res['data']['users']['pageInfo']['offsetPagination']['hasMore'];
        $this->assertEquals(false, $has_more);
    }

    public function testCanGetTotal()
    {
        $this->createUsers(10);
        wp_set_current_user(get_user_by('login', 'admin')->ID);

        $res = graphql([
            'query' => '
            query Users {
                users(where: {
                    orderby: {field: DISPLAY_NAME, order: ASC},
                    offsetPagination: {size: 5, offset: 2}
                }) {
                pageInfo {
                    offsetPagination {
                        total
                    }
                }
                nodes {
                    name
                   }
                }
            }
        ',
        ]);

        $this->assertEquals('', $res['errors'][0]['message'] ?? '');

        $total = $res['data']['users']['pageInfo']['offsetPagination']['total'];

        $this->assertEquals(11, $total);
    }
}
