<?php

namespace Foo\PHP7\Bar;

use Foo\PHP7\{Bar,Bar1};

class BarTest
{

    public function test()
    {
        $bar = new Bar();
        $bar1 = new Bar1();
        $bar->testIsUsed();
        $bar1->testIsUsed();
    }
}
