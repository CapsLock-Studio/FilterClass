<?php

use Codeception\Util\Debug;
use FilterClass\ClassAnalyzer;
use FilterClass\Exception;

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
     * @expectedException FilterClass\Exception
     */
    public function testInvalidFromPath()
    {
        $filter = new ClassAnalyzer([
            "fromPath" => "AAA",
            "toPath"   => __DIR__,
        ]);
    }

    /**
     * @expectedException FilterClass\Exception
     */
    public function testInvalidToPath()
    {
        $filter = new ClassAnalyzer([
            "fromPath" => __DIR__,
            "toPath"   => "AAA",
        ]);
    }

    /**
     * @expectedException FilterClass\Exception
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
        ]);

        $filter->setBasePath(__DIR__);
        $this->assertEquals($filter->getBasePath(), __DIR__);
        $this->assertEquals($filter->getFromPath(), __DIR__);
        $this->assertEquals($filter->getToPath(), __DIR__);
    }

    public function testShowOutputAfterCreatedFlag()
    {
        $filter = new ClassAnalyzer([
            "fromPath" => __DIR__,
            "toPath"   => __DIR__,
        ]);

        $filter->setShowOutputAfterCreatedFlag(true);
        $flag = $filter->getShowOutputAfterCreatedFlag();
        $this->assertTrue($flag);

        $filter->setShowOutputAfterCreatedFlag(false);
        $flag = $filter->getShowOutputAfterCreatedFlag();
        $this->assertFalse($flag);
    }

    public function testGetResultResource()
    {
        $filter = new ClassAnalyzer([
            "fromPath" => __DIR__,
            "toPath"   => __DIR__,
        ]);

        $fn = $filter->getResultResource();

        $this->assertNotEquals(false, $fn);
    }

    public function testAnalyze()
    {
        $path   = __DIR__ . "/../_data/Foo";
        $filter = new ClassAnalyzer([
            "fromPath" => $path,
            "toPath"   => $path,
        ]);

        $filter->analyze();
        $unused = $filter->getUnusedCode();

        $this->assertTrue(isset($unused["Bar"]));
        $this->assertTrue(isset($unused["Bar1"]));
        $this->assertTrue(in_array("testNotUsed", $unused["Bar"]));
        $this->assertFalse(in_array("testIsUsed", $unused["Bar"]));
        $this->assertFalse(in_array("testParentUsed", $unused["Bar"]));
    }
}
