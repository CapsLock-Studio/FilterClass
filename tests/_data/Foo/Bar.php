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

    public function testUseInBar2()
    {

    }

    public function testUndefinedFunction()
    {
        IamUndefinedFunction()->call();
    }

    public function testOneLineFunction()
    {

    }

    public function testFullCallFunction()
    {

    }
}
