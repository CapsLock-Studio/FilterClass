<?php

namespace CapsLockStudio\FilterClass;

use PhpParser\Node;
use PhpParser\PrettyPrinter;
use PhpParser\NodeVisitorAbstract;

class Visitor extends NodeVisitorAbstract
{

    protected $code      = [];
    protected $use       = [];
    protected $classname = "";
    protected $namespace = "";
    protected $parent    = "";

    protected function getPrinter()
    {
        return new PrettyPrinter\Standard;
    }

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

    public function getCode()
    {
        return $this->code;
    }

    public function setUseStmt($use)
    {
        $this->use = $use;
    }
}
