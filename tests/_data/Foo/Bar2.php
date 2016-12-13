<?php

namespace Foo;

class Bar2
{

    public function testUseBarFunction()
    {
        $foo = new Bar;
        $foo->testUseInBar2();
    }

    public function testStaticCall()
    {
        Foo\Bar::testStaicUsed();
    }

    public function testFullCall()
    {
        $bar = new \Foo\Bar();
        $bar->testFullCallFunction();
    }
}
