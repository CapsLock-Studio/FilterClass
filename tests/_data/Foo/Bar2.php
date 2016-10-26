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
}
