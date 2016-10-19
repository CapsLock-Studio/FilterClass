<?php

namespace FilterClass;

use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use FilterClass\Visitor\ClassVisitor;
use FilterClass\Visitor\MethodVisitor;
use FilterClass\Exception\FilterClassException;

/**
 * @author michael_kuan <michael34435@capslock.tw>
 *
 * 一個簡單的分析器，可以分析folderA有在folderB用到什麼class(含namespace)
 */
class ClassAnalyzer
{

    const REGEX = [
        "extends"   => "/class\s+([A-Za-z0-9]+)(\s+extends\s+([A-Za-z0-9]+))*/",
        "namespace" => "/namespace\s+(.*);/",
        "class"     => "/class\s+([A-Za-z0-9_]+)/",
        "function"  => "/function\s+([A-Za-z0-9_]+)/",
    ];

    private $fn       = null;
    private $fromPath = "";
    private $toPath   = "";
    private $basePath = "";
    private $show     = false;
    private $report   = "";
    private $buffer   = [];
    private $unused   = [];
    private $used     = [];

    /**
     * 建構子
     * @param array $config ["fromPath" => "分析的資料夾(folderA)", “toPath” => “目標的資料夾(folderB)”]
     */
    public function __construct(array $config = [])
    {
        $templateConfig = ["fromPath" => "", "toPath" => ""];
        $config         = array_merge($templateConfig, $config);
        $this->fn       = fopen("php://memory", "wb");
        $this->fromPath = $config["fromPath"];
        $this->toPath   = $config["toPath"];

        if (!($this->getFromPath() || $this->getToPath())) {
            throw new FilterClassException("No path found in config.", 1);
        }
    }

    /**
     * 設定母目錄
     * @param string $basePath
     */
    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * 結束後會做的動作
     */
    public function __destruct()
    {
        $fn = $this->getResultResource();
        if ($this->getShowOutputAfterCreatedFlag()) {
            rewind($fn);
            echo stream_get_contents($fn);
        }

        fclose($fn);
    }

    /**
     * 開始分析
     * @return void
     */
    public function analyze()
    {
        $collectedClass = [];
        $this->getClassAndPath($this->getFromPath(), $collectedClass);
        $this->analyzeContainClass($this->getToPath(), $collectedClass);
    }

    /**
     * 取得母目錄
     * @return string
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * 取得folderA
     * @return string
     */
    public function getFromPath()
    {
        return $this->fromPath;
    }

    /**
     * 設定結束後是否顯示
     *
     * @param boolean
     */
    public function setShowOutputAfterCreatedFlag($show)
    {
        $this->show = is_bool($show) ? $show : $this->show;
    }

    /**
     * 取得設定後是否顯示的flag
     */
    public function getShowOutputAfterCreatedFlag()
    {
        return $this->show;
    }

    /**
     * 取得folderB
     * @return string
     */
    public function getToPath()
    {
        return $this->toPath;
    }

    /**
     * 取得目前正在處理的檔案
     * @return resource
     */
    public function getResultResource()
    {
        return $this->fn;
    }

    /**
     * 取得沒有用到的function
     *
     * @return array
     */
    public function getUnusedCode()
    {
        return $this->unused;
    }

    /**
     * 分析folderB含有的class
     * @param  string $dir            來源資料夾
     * @param  array  $matchClassPath folderA的class資料
     * @param  array  $resource       folderB的class資料
     * @return void
     */
    private function analyzeContainClass($dir, $matchClassPath, array &$resource = [])
    {
        $this->iterDir("{$dir}/*", function ($filePath) use ($matchClassPath, &$resource) {
            $fileContent = php_strip_whitespace($filePath);
            $this->analyzeContent($fileContent, $matchClassPath, $resource, $filePath);
        }, function ($filePath) use ($matchClassPath, &$resource) {
            $this->analyzeContainClass($filePath, $matchClassPath, $resource);
        });
    }

    /**
     * 分析內容
     * @param  string  $fileContent    檔案內容
     * @param  array   $matchClassPath folderA的class資料
     * @param  array   $resource       folderB的class資料
     * @param  string  $filePath       目前分析的資料
     * @return void
     */
    private function analyzeContent($fileContent, array $matchClassPath, array &$resource, $filePath)
    {
        $fn   = $this->getResultResource();
        $code = $this->getCode($filePath);
        foreach ($matchClassPath as $fromPath => $matchClass) {
            foreach ($matchClass as $matched) {
                $pattern        = "{$matched["namespace"]}\\{$matched["class"]}";
                $regexUse       = "/" . quotemeta($pattern) . "\s*(\s+as\s+(.+);|;|,|\()/";
                $extendsMatch   = @preg_match(self::REGEX["extends"], $fileContent, $matchedExtends);
                $classFound     = @preg_match($regexUse, $fileContent, $matchedUse);
                $matchUseClass  = isset($matchedUse[2]) ? $matchedUse[2] : $matched["class"];
                $matchedExtends = isset($matchedExtends[3]) ? $matchedExtends[3] : null;
                if ($classFound || $filePath === $matched["path"] || $matchedExtends === $matchUseClass) {
                    $fromPath = str_replace($this->getBasePath(), "", $fromPath);
                    $filePath = str_replace($this->getBasePath(), "", $filePath);
                    $keymap   = [
                        "in"      => $filePath,
                        "from"    => $fromPath,
                        "pattern" => $pattern
                    ];

                    if (!in_array($keymap, $resource)) {
                        $this->used = array_merge_recursive($code, $this->used);

                        $used     = isset($this->used[$matched["class"]]) ? $this->used[$matched["class"]] : [];
                        $methods  = isset($code[$matched["class"]]) ? $code[$matched["class"]] : [];
                        $content  = "find pattern: {$pattern}\n";
                        $content .= "find match class: {$matched["class"]}\n";
                        $content .= "in path: {$filePath}\n";
                        $content .= "from path: {$fromPath}\n";
                        $content .= "find namespace: {$matched["namespace"]}\n";
                        $content .= "find method: " . json_encode($methods) . "\n\n";
                        $unused   = array_diff($matched["functions"], $used);
                        $this->unused[$matched["class"]] = isset($this->unused[$matched["class"]]) ? $this->unused[$matched["class"]] : [];
                        $this->unused[$matched["class"]] = $this->unused[$matched["class"]] ? array_intersect($this->unused[$matched["class"]], $unused) : $unused;

                        $resource[] = $keymap;
                        fputs($fn, $content);
                    }
                }
            }
        }
    }

    /**
     * 取得folderA的class資料
     * @param  string $dir     folderA位置
     * @param  string $filemap 分析完畢的資料
     * @return void
     */
    private function getClassAndPath($dir, array &$filemap = [])
    {
        $this->iterDir("{$dir}/*", function ($filePath) use (&$filemap) {
            $tempClassAndNamespace = $this->getClassAndNamespaceFromFilePath($filePath);
            if ($tempClassAndNamespace) {
                $filemap[$filePath]   = isset($filemap[$filePath]) ? $filemap[$filePath] : [];
                $filemap[$filePath][] = $tempClassAndNamespace;
            }
        }, function ($filePath) use (&$filemap) {
            $this->getClassAndPath($filePath, $filemap);
        });
    }

    /**
     * 取得class跟namespace
     *
     * @param string $path 路徑
     * @return array
     */
    private function getClassAndNamespaceFromFilePath($path)
    {
        $fileContent = file_get_contents($path);
        if (preg_match(self::REGEX["class"], $fileContent, $match)) {
            preg_match(self::REGEX["namespace"], $fileContent, $namespace);
            preg_match_all(self::REGEX["function"], $fileContent, $functions);
            return [
                "path"      => $path,
                "class"     => $match[1],
                "namespace" => isset($namespace[1]) ? $namespace[1] : "",
                "functions" => $functions[1],
            ];
        }

        return [];
    }

    /**
     * 掃資料夾用
     * @param  string   $glob 檔案
     * @param  callable $cb1   如果是php
     * @param  callable $cb2   如果是資料夾
     * @return void
     */
    private function iterDir($glob, callable $cb1, callable $cb2)
    {
        $files = glob($glob);
        foreach ($files as $filePath) {
            $filePath  = realpath($filePath);
            $extension = explode(".", $filePath);
            $extension = end($extension);
            if ($extension === "php" && is_file($filePath)) {
                $cb1($filePath);
            } elseif (is_dir($filePath)) {
                $cb2($filePath);
            }
        }
    }

    private function getCode($path)
    {
        $code = [];

        // 判斷是不是可以從buffer取得
        if (isset($this->buffer[$path])) {
            $code = $this->buffer[$path];
        } else {
            // parser created
            $traverser = new NodeTraverser();
            $parser    = (new ParserFactory)->create(ParserFactory::PREFER_PHP5);

            $content = php_strip_whitespace($path);

            if (preg_match(self::REGEX["extends"], $content, $match)) {
                $classname = $match[1];
                $parent    = isset($match[3]) ? $match[3] : "";

                // visitor class method
                $classVisitor = new ClassVisitor();
                $classVisitor->setClassName($classname);
                $classVisitor->setParent($parent);
                $traverser->addVisitor($classVisitor);

                // parse it
                $stmts = $parser->parse($content);
                $traverser->traverse($stmts);

                // 取得所有「使用到」的class method
                $code = $classVisitor->getCode();

                // 加入已經處理的buffer
                $this->buffer[$path] = $code;
            }
        }

        return $code;
    }
}
