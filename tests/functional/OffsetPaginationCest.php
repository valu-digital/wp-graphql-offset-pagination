<?php

class OffsetPaginationCest
{
    public function testPagination(FunctionalTester $I)
    {
        foreach (range(0, 10) as $number) {
            $number = str_pad($number, 2, '0', STR_PAD_LEFT);

            $I->havePageInDatabase(['post_title' => "Post $number"]);
        }

        $query = '
        query Posts {
            pages(where: {
                orderby: {field: TITLE, order: ASC},
                offsetPagination: {size: 5, offset: 2}
            }) {
            nodes {
                title
            }
          }
        }
        ';

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/graphql', [
            'query' => $query,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        // $res = $I->grabResponse();
        $I->seeResponseContainsJson([
            'data' => [
                'pages' => [
                    'nodes' => [
                        ['title' => 'Post 02'],
                        ['title' => 'Post 03'],
                        ['title' => 'Post 04'],
                        ['title' => 'Post 05'],
                        ['title' => 'Post 06'],
                    ],
                ],
            ],
        ]);
    }
}
