<?php

namespace Foo;

class Bar1 extends Bar
{

    public function test()
    {
        $this->testParentUsed();
    }

    public function test1()
    {
        parent::testStaicUsed();
    }
}
