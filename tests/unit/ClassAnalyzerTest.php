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

    public function testAnalyze()
    {
        $path   = __DIR__ . "/../_data/Foo";
        $filterShowResult = new ClassAnalyzer([
            "fromPath" => $path,
            "toPath"   => $path,
        ]);

        ob_start();
        $filterShowResult->setShowOutputAfterCreatedFlag(true);
        $filterShowResult->analyze();
        $unused = $filterShowResult->getUnusedCode();
        unset($filterShowResult);

        $result1 = ob_get_clean();

        $filterNotShowResult = new ClassAnalyzer([
            "fromPath" => $path,
            "toPath"   => $path,
        ]);

        ob_start();
        $filterNotShowResult->setShowOutputAfterCreatedFlag(false);
        $filterNotShowResult->analyze();
        $unused = $filterNotShowResult->getUnusedCode();
        unset($filterNotShowResult);
        $result2 = ob_get_clean();

        $this->assertTrue(isset($unused["Foo\Bar"]));
        $this->assertTrue(isset($unused["Foo\Bar1"]));
        $this->assertTrue(in_array("testNotUsedInNestedNamespace", $unused["Foo\Bar\Bar"]));
        $this->assertTrue(in_array("testNotUsed", $unused["Foo\Bar"]));
        $this->assertFalse(in_array("testIsUsed", $unused["Foo\Bar"]));
        $this->assertFalse(in_array("testUseInBar2", $unused["Foo\Bar"]));
        $this->assertFalse(in_array("testParentUsed", $unused["Foo\Bar"]));
        $this->assertNotEmpty($result1);
        $this->assertEmpty($result2);
    }
}
