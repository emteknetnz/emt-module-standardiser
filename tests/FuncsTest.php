<?php

use PHPUnit\Framework\TestCase;

class FuncsTest extends TestCase
{
    /**
     * @dataProvider provideCheckoutBranch
     */
    public function testCheckoutBranch(
        $expected,
        $branches,
        $branchOption,
        $defaultBranch
    ) {
        $actual = checkoutBranch($branches, $branchOption, $defaultBranch);
        $this->assertSame($expected, $actual);
    }

    public function provideCheckoutBranch()
    {
        $branches = ['1.5', '1.6', '1', '2.0', '2.1' , '2.2', '2', '3', 'pulls/2.3/something', 'random'];
        $defaultBranch = '2';
        return [
            ['3', $branches, 'next-major-next-minor', $defaultBranch],
            ['2', $branches, 'next-minor', $defaultBranch],
            ['2', $branches, 'lorem-ipsum', $defaultBranch],
            ['2.2', $branches, 'next-patch', $defaultBranch],
            ['1', $branches, 'last-major-next-minor', $defaultBranch],
            ['1.6', $branches, 'last-major-next-patch', $defaultBranch],
        ];
    }
}
