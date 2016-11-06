<?php

namespace FilterClass\Visitor;

use PhpParser\Node;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use FilterClass\Visitor;

class Class_ extends Visitor
{

    public function leaveNode(Node $node)
    {
        $printer = $this->getPrinter();

        if ($node instanceof Node\Stmt\ClassMethod) {
            $code = $printer->prettyPrint([$node]);

            // prepare method parser
            $traverser = new NodeTraverser();
            $parser    = (new ParserFactory)->create(ParserFactory::PREFER_PHP5);

            // visitor class method
            $methodVisitor = new Method_();
            $methodVisitor->setUseStmt($this->use);
            $traverser->addVisitor($methodVisitor);

            // set class name
            $classname = $this->classname ?: "TEST";

            // set namespace
            $namespace = $this->namespace ? "namespace {$this->namespace};" : "";

            // parent
            $parent = $this->parent ? "extends {$this->parent}" : "";

            // parse it
            $stmts = $parser->parse("<?php {$namespace} class {$classname} {$parent}{{$code}}");

            // parse method in each method call
            $traverser->traverse($stmts);

            // TODO: handle private function

            // get used code
            $code = $methodVisitor->getCode();

            $this->code = array_merge_recursive($this->code, $code);
        }
    }

    public function getCode()
    {
        return $this->code;
    }
}
