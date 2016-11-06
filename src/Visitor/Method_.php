<?php

namespace FilterClass\Visitor;

use PhpParser\Node;
use FilterClass\Visitor;

class Method_ extends Visitor
{
    private $objectMap = [];

    public function leaveNode(Node $node)
    {
        $codeClass = "";
        $codeName  = "";
        $namespace = "";
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
            $printer = $this->getPrinter();
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
                $codeClass = !is_object($codeClass->name) && isset($this->objectMap[$codeClass->name]) ? $this->objectMap[$codeClass->name] : $codeClass->name;
            }

            if (is_object($codeClass) && $codeClass instanceof Node\Name) {
                $codeName  = implode("->", $codeClass->parts);
                $codeClass = "";
            }
        }

        if ($codeClass === "self" || $codeClass === "this" || $codeClass === "static") {
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

    private function assign($namespace, $codeClass, $codeName)
    {
        if (is_string($codeClass) && is_string($codeName) && $codeClass && $codeName) {
            $slice     = explode("\\", $namespace);
            $part      = array_shift($slice);
            $namespace = implode("\\", $slice);
            $prefix    = isset($this->use[$part ?: $codeClass]) ? $this->use[$part ?: $codeClass] : "";
            $namespace = $prefix && $namespace ? "{$prefix}\\{$namespace}" : ($prefix ? $prefix : ($namespace ? $namespace : $this->namespace));
            $codeClass = $namespace ? "{$namespace}\\{$codeClass}" : $codeClass;
            $codeClass = preg_replace("/[\\\]+/", "\\", $codeClass);
            $this->code[$codeClass]   = isset($this->code[$codeClass]) ? $this->code[$codeClass] : [];
            $this->code[$codeClass][] = $codeName;
            $this->code[$codeClass]   = array_unique($this->code[$codeClass]);
        }
    }
}
