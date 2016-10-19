<?php

namespace Foo;

class Bar
{

    public function testIsUsed()
    {
        # code...
    }

    public function testNotUsed()
    {
        $test = $this->testIsUsed();
    }

    public function testParentUsed()
    {

    }

    public static function testStaicUsed()
    {
        self::testStaicUsed();
        $this::testStaicUsed();
    }

    public function testNewObject()
    {
        $foo = new Bar1;
    }
}
