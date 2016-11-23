<?php

namespace CapsLockStudio\FilterClass;

use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use CapsLockStudio\FilterClass\Visitor\Class_;

class CodeAnalyzer
{

    private $code  = [];
    private $lines = [];
    private $path;

    public function __construct($path)
    {
        $this->path = $path;
        $traverser  = new NodeTraverser();
        $parser     = (new ParserFactory)->create(ParserFactory::PREFER_PHP5);
        $content    = $this->getTrimedCode();
        if (preg_match(ClassAnalyzer::REGEX["extends"], $content)) {
            // visitor class method
            $classVisitor = new Class_();
            $traverser->addVisitor($classVisitor);

            // parse it
            $stmts = $parser->parse($content);
            $traverser->traverse($stmts);

            // 取得所有「使用到」的class method
            $this->code = $classVisitor->getCode();

            $this->lines = $classVisitor->getLines();
        }
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getLines()
    {
        return $this->lines;
    }

    /**
     * 取得去掉註解的code
     *
     * @param string $path PATH
     *
     * @return string
     * @see http://stackoverflow.com/questions/503871/best-way-to-automatically-remove-comments-from-php-code
     */
    public function getTrimedCode()
    {
        $fileStr     = file_get_contents($this->path);
        $fileContent = "";

        $commentTokens   = [T_COMMENT];
        $commentTokens[] = defined("T_DOC_COMMENT") ? T_DOC_COMMENT : T_ML_COMMENT;

        $tokens = token_get_all($fileStr);

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (in_array($token[0], $commentTokens))
                    continue;

                $token = $token[1];
            }

            $fileContent .= $token;
        }

        return $fileContent;
    }
}
