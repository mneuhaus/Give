<?php

namespace Herrera\Version\Tests;

use Herrera\PHPUnit\TestCase;
use Herrera\Version\Validator;

class ValidatorTest extends TestCase
{
    public function getInvalidIdentifiers()
    {
        return array(
            array('+')
        );
    }

    public function getInvalidNumbers()
    {
        return array(
            array('0x1'),
            array('a')
        );
    }

    public function getInvalidVersions()
    {
        return array(
            array('x.0.0'),
            array('0.x.0'),
            array('0.0.x'),

            array('-1.0.0'),
            array('0.-1.0'),
            array('0.0.-1'),

            array('0.0.0-'),
            array('0.0.0+'),
            array('0.0.0-!'),
            array('0.0.0+!'),

            array('0.0.0+0+0'),
        );
    }

    public function getValidIdentifiers()
    {
        return array(
            array('-'),
            array('abc-abc')
        );
    }

    public function getValidNumbers()
    {
        return array(
            array('01'),
            array('1'),
            array(1)
        );
    }

    public function getValidVersions()
    {
        return array(
            array('0.0.0'),
            array('1.0.0'),
            array('0.1.0'),
            array('0.0.1'),
            array('1.1.1'),

            array('0.0.0+0'),
            array('0.0.0-0'),

            array('0.0.0-0+0'),
            array('0.0.0-0-0'),
            array('0.0.0-0+0'),
            array('0.0.0+0-0'),
            array('0.0.0-a-a'),
            array('0.0.0-a+a'),
            array('0.0.0+a-a'),
        );
    }

    /**
     * @dataProvider getInvalidIdentifiers
     */
    public function testIsIdentifierInvalid($identifier)
    {
        $this->assertFalse(Validator::isIdentifier($identifier));
    }

    /**
     * @dataProvider getValidIdentifiers
     */
    public function testIsIdentifierValid($identifier)
    {
        $this->assertTrue(Validator::isIdentifier($identifier));
    }

    /**
     * @dataProvider getInvalidNumbers
     */
    public function testIsNumberInvalid($number)
    {
        $this->assertFalse(Validator::isNumber($number));
    }

    /**
     * @dataProvider getValidNumbers
     */
    public function testIsNumberValid($number)
    {
        $this->assertTrue(Validator::isNumber($number));
    }

    /**
     * @dataProvider getInvalidVersions
     */
    public function testIsVersionInvalid($version)
    {
        $this->assertFalse(Validator::isVersion($version));
    }

    /**
     * @dataProvider getValidVersions
     */
    public function testIsVersionValid($version)
    {
        $this->assertTrue(Validator::isVersion($version));
    }
}
