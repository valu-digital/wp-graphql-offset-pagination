<?php

class ExampleCest {
    public function testTitleExample( FunctionalTester $I ) {
        $I->amOnPage('/');
        $I->see('EXAMPLE TITLE MOD');
    }
}