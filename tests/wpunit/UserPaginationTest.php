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
        \WPGraphQL::__clear_schema();
    }

    function createUsers($count, $args = [])
    {
        $name_prefix = 'test-user';

        if (isset($args['name_prefix'])) {
            $name_prefix = $args['name_prefix'];
            unset($args['name_prefix']);
        }

        foreach (range(0, $count) as $number) {
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
        wp_set_current_user(1);

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
                'test-user-00',
                'test-user-01',
                'test-user-02',
                'test-user-03',
            ],
            $names
        );
    }

    public function testUsersSetOffset()
    {
        $this->createUsers(10);
        wp_set_current_user(1);

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
                'test-user-02',
                'test-user-03',
                'test-user-04',
                'test-user-05',
                'test-user-06',
            ],
            $names
        );
    }

    public function testUsersLongOffset()
    {
        $this->createUsers(20);
        wp_set_current_user(1);

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
                'test-user-14',
                'test-user-15',
                'test-user-16',
                'test-user-17',
                'test-user-18',
            ],
            $names
        );
    }
}
