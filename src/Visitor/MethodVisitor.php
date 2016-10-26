<?php

namespace FilterClass\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;

class MethodVisitor extends NodeVisitorAbstract
{
    private $code      = [];
    private $classname = "";
    private $namespace = "";
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

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespace = $node->name;
        }
    }

    public function leaveNode(Node $node)
    {
        $codeClass = "";
        $codeName  = "";
        $namespace = [];
        $useThis   = false;
        if ($node instanceof Node\Expr\Assign) {
            $var  = $node->var;
            $expr = $node->expr;
            if ($expr instanceof Node\Expr\New_) {
                $exprAry = $expr->class->parts;
                if (is_array($exprAry)) {
                    $expr      = array_pop($exprAry);
                    $namespace = implode("\\", $exprAry);
                    $this->objectMap[$var->name] = $expr;
                }
            }
        }

        if (!$node instanceof Node\Scalar\EncapsedStringPart) {
            $printer = new PrettyPrinter\Standard;
            $code    = $printer->prettyPrint([$node]);
            if (preg_match("/^([A-Za-z0-9]+)\:\:([A-Za-z0-9]+)\s*\(/", $code, $match)) {
                $codeClassAry = explode("\\", $match[1]);
                $codeClass    = array_pop($codeClassAry);
                $namespace    = implode("\\", $codeClassAry);
                $codeName     = $match[2];
            }
        }

        if ($node instanceof Node\Expr\StaticCall) {
            $codeClass = $node->class;
            if ($codeClass instanceof Node\Name) {
                $codeClassAry = $codeClass->parts;
                $codeClass    = array_pop($codeClassAry);
                $namespace    = implode("\\", $codeClassAry);
            }

            if ($codeClass instanceof Node\Expr\Variable) {
                $codeClass = $codeClass->name;
                $codeClass = isset($this->objectMap[$codeClass]) ? $this->objectMap[$codeClass] : $codeClass;
            }
        }

        if ($node instanceof Node\Expr\MethodCall) {
            $codeClass = $node->var;
            if (isset($codeClass->name)) {
                $codeClass = isset($this->objectMap[$codeClass->name]) ? $this->objectMap[$codeClass->name] : $codeClass->name;
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
        $this->assign($namespace, $codeClass, $codeName);
        if ($useThis) {
            $this->assign($namespace, $this->parent, $codeName);
        }
    }

    public function getCode()
    {
        return $this->code;
    }

    private function assign($namespace, $codeClass, $codeName)
    {
        if (is_string($codeClass) && is_string($codeName)) {
            $namespace = $namespace ? $namespace : $this->namespace;
            $codeClass = $namespace ? "{$namespace}\\{$codeClass}" : $codeClass;
            $this->code[$codeClass]   = isset($this->code[$codeClass]) ? $this->code[$codeClass] : [];
            $this->code[$codeClass][] = $codeName;
            $this->code[$codeClass]   = array_unique($this->code[$codeClass]);
        }
    }
}
