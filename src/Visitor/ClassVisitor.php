<?php

namespace FilterClass\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

class ClassVisitor extends NodeVisitorAbstract
{
    private $code      = [];
    private $use       = [];
    private $classname = "";
    private $namespace = "";
    private $parent    = "";

    public function enterNode(Node $node)
    {
        $printer = new PrettyPrinter\Standard;
        if ($node instanceof Node\Stmt\Class_) {
            $this->classname = $node->name;
            if (isset($node->extends)) {
                $this->parent = implode("\\", $node->extends->parts);
            }
        }

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespace = $node->name;
        }

        if ($node instanceof Node\Stmt\Use_ || $node instanceof Node\Stmt\GroupUse) {
            $prefix = isset($node->prefix) ? implode("\\", $node->prefix->parts) : "";
            foreach ($node->uses as $use) {
                array_pop($use->name->parts);
                $namespace             = isset($use->alias) ? $use->alias : end($use->name->parts);
                $this->use[$namespace] = ($prefix ? "{$prefix}\\" : "") . implode("\\", $use->name->parts);
            }
        }
    }

    public function leaveNode(Node $node)
    {
        $printer = new PrettyPrinter\Standard;

        if ($node instanceof Node\Stmt\ClassMethod) {
            $code = $printer->prettyPrint([$node]);
            preg_match("/function\s+([A-Za-z0-9_]+)/", $code, $match);
            $name = $match[1];

            // prepare method parser
            $traverser = new NodeTraverser();
            $parser    = (new ParserFactory)->create(ParserFactory::PREFER_PHP5);

            // visitor class method
            $methodVisitor = new MethodVisitor($this->parent);
            $methodVisitor->setUseStmt($this->use);
            $traverser->addVisitor($methodVisitor);

            // set class name
            $classname = $this->classname ?: "TEST";

            // set namespace
            $namespace = $this->namespace ? "namespace {$this->namespace};" : "";

            // parse it
            $stmts = $parser->parse("<?php {$namespace} class {$classname}{{$code}}");

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
