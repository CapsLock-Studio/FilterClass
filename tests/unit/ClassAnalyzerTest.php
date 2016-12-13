<?php

use Codeception\Util\Debug;
use CapsLockStudio\FilterClass\ClassAnalyzer;
use CapsLockStudio\FilterClass\Exception;

class ClassAnalyzerTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    /**
     * @expectedException CapsLockStudio\FilterClass\Exception
     */
    public function testInvalidFromPath()
    {
        $filter = new ClassAnalyzer([
            "fromPath" => "AAA",
            "toPath"   => __DIR__,
        ]);
    }

    /**
     * @expectedException CapsLockStudio\FilterClass\Exception
     */
    public function testInvalidToPath()
    {
        $filter = new ClassAnalyzer([
            "fromPath" => __DIR__,
            "toPath"   => "AAA",
        ]);
    }

    /**
     * @expectedException CapsLockStudio\FilterClass\Exception
     */
    public function testNoConfig()
    {
        $filter = new ClassAnalyzer();
    }

    public function testBasePath()
    {
        $filter = new ClassAnalyzer([
            "fromPath" => __DIR__,
            "toPath"   => __DIR__,
            "basePath" => __DIR__,
        ]);

        $this->assertEquals($filter->basePath, __DIR__);
        $this->assertEquals($filter->fromPath, __DIR__);
        $this->assertEquals($filter->toPath, [__DIR__]);
    }

    public function testAnalyze()
    {
        $path   = __DIR__ . "/../_data/Foo";
        $filterResult = new ClassAnalyzer([
            "fromPath" => $path,
            "toPath"   => $path,
        ]);

        $filterResult->analyze();
        $unused = $filterResult->getUnusedCode();
        $lines  = $filterResult->getLines();
        $total  = $filterResult->getTotal();

        $this->assertTrue(isset($unused["Foo\Bar"]));
        $this->assertTrue(isset($unused["Foo\Bar1"]));
        $this->assertTrue(in_array("testNotUsedInNestedNamespace", $unused["Foo\Bar\Bar"]));
        $this->assertTrue(in_array("testNotUsed", $unused["Foo\Bar"]));
        $this->assertFalse(in_array("testIsUsed", $unused["Foo\Bar"]));
        $this->assertEquals($lines["Foo\Bar"]["testIsUsed"], 2);
        $this->assertFalse(in_array("testUseInBar2", $unused["Foo\Bar"]));
        $this->assertEquals($lines["Foo\Bar"]["testUseInBar2"], 2);
        $this->assertFalse(in_array("testOneLineFunction", $unused["Foo\Bar"]));
        $this->assertEquals($lines["Foo\Bar"]["testOneLineFunction"], 2);
        $this->assertFalse(in_array("testParentUsed", $unused["Foo\Bar"]));
        $this->assertEquals($lines["Foo\Bar"]["testParentUsed"], 2);
        $this->assertFalse(in_array("testIsUsed", $unused["Foo\PHP7\Bar1"]));
        $this->assertEquals($lines["Foo\PHP7\Bar1"]["testIsUsed"], 2);
        $this->assertFalse(in_array("testIsUsed", $unused["Foo\PHP7\Bar"]));
        $this->assertEquals($lines["Foo\PHP7\Bar"]["testIsUsed"], 2);
        $this->assertEquals($total, 55);
        $this->assertFalse(in_array("testFullCallFunction", $unused["Foo\Bar"]));
    }
}
