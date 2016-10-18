<?php

namespace FilterClass\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;

class MethodVisitor extends NodeVisitorAbstract
{
    private $code      = [];
    private $classname = "";
    private $parent    = "";
    private $objectMap = [];

    public function __construct($parent)
    {
        $this->parent = $parent;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            $this->classname = $node->name;
        }
    }

    public function leaveNode(Node $node)
    {
        $codeClass = "";
        $codeName  = "";
        $useThis   = false;
        if ($node instanceof Node\Expr\Assign) {
            $var  = $node->var;
            $expr = $node->expr;
            if ($expr instanceof Node\Expr\New_) {
                $expr = $expr->class->parts;
                $expr = array_pop($expr);
                $this->objectMap[$var->name] = $expr;
            }
        }


        if (!$node instanceof Node\Scalar\EncapsedStringPart) {
            $printer = new PrettyPrinter\Standard;
            $code    = $printer->prettyPrint([$node]);
            if (preg_match("/^([A-Za-z0-9]+)\:\:([A-Za-z0-9]+)\s*\(/", $code, $match)) {
                $codeClass = explode("\\", $match[1]);
                $codeClass = end($codeClass);
                $codeName  = $match[2];
            }
        }

        if ($node instanceof Node\Expr\StaticCall) {
            $codeClass = $node->class;
            if ($codeClass instanceof Node\Name) {
                $codeClass = array_pop($codeClass->parts);
            } elseif ($codeClass instanceof Node\Expr\Variable) {
                $codeClass = $codeClass->name;
                $codeClass = isset($this->objectMap[$codeClass]) ? $this->objectMap[$codeClass] : $codeClass;
            }
        }

        if ($node instanceof Node\Expr\MethodCall) {
            $codeClass = $node->var;
            if (isset($codeClass->name)) {
                $codeClass = isset($this->objectMap[$codeClass->name]) ? $this->objectMap[$codeClass->name] : $codeClass->name;
            } else {
                $codeClass = "";
            }
        }

        if ($codeClass === "self" || $codeClass === "this") {
            $codeClass = $this->classname;
            $useThis   = true;
        }



        if ($codeClass === "parent") {
            $codeClass = $this->parent;
        }

        $codeName = isset($node->name) ? $node->name : $codeName;
        $this->assign($codeClass, $codeName);
        if ($useThis) {
            $this->assign($this->parent, $codeName);
        }
    }

    public function getCode()
    {
        return $this->code;
    }

    private function assign($codeClass, $codeName)
    {
        if ($codeClass) {
            $this->code[$codeClass]   = isset($this->code[$codeClass]) ? $this->code[$codeClass] : [];
            $this->code[$codeClass][] = $codeName;
            $this->code[$codeClass]   = array_unique($this->code[$codeClass]);
        }
    }
}
