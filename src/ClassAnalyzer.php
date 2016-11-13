<?php

namespace CapsLockStudio\FilterClass;

use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use CapsLockStudio\FilterClass\Visitor\Class_;

/**
 * @author michael34435 <michael34435@capslock.tw>
 *
 * 一個簡單的分析器，可以分析folderA有在folderB用到什麼class(含namespace)
 */
class ClassAnalyzer
{

    const REGEX = [
        "extends"   => "/class\s+([A-Za-z0-9]+)(\s+extends\s+([A-Za-z0-9]+))*/",
        "namespace" => "/namespace\s+([A-Za-z0-9\\\]+);/",
        "class"     => "/class\s+([A-Za-z0-9_]+)/",
        "function"  => "/(private|protected|public)*\s*(static)*\s*function\s+([A-Za-z0-9_]+)/",
    ];

    private $fn       = null;
    private $fromPath = "";
    private $toPath   = "";
    private $basePath = "";
    private $show     = false;
    private $report   = "";
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
        $this->fromPath = $config["fromPath"];
        $this->toPath   = $config["toPath"];

        $this->setResultResource();

        if (!is_dir($this->getFromPath())) {
            throw new Exception("Defined `fromPath` is not valid");
        }

        if ($this->getToPath() && !is_dir($this->getToPath())) {
            throw new Exception("Defined `toPath` is not valid");
        }

        fputs($this->fn, "[");
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
        $this->analyzeContainClass($this->getFromPath(), $collectedClass);

        if ($this->getToPath()) {
            $this->analyzeContainClass($this->getToPath(), $collectedClass);
        }

        foreach ($this->unused as $class => $method) {
            if (isset($this->used[$class])) {
                $this->unused[$class] = array_diff($this->unused[$class], $this->used[$class]);
            }
        }

        fputs($this->getResultResource(), "]");
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
                $pattern        = !empty($matched["namespace"]) ? "{$matched["namespace"]}\\{$matched["class"]}" : $matched["class"];
                $regexUse       = "/" . quotemeta($pattern) . "\s*(\s+as\s+(.+);|;|,|\()/";
                $regexGroupUse  = "/" . quotemeta($matched["namespace"]) . "\\\{([A-Za-z0-9_, ]+)\}/";
                $groupUseMatch  = preg_match_all($regexGroupUse, $fileContent, $matchedGroupUse);
                $extendsMatch   = @preg_match(self::REGEX["extends"], $fileContent, $matchedExtends);
                $classFound     = @preg_match($regexUse, $fileContent, $matchedUse);
                $matchUseClass  = isset($matchedUse[2]) ? $matchedUse[2] : $matched["class"];
                $matchedExtends = isset($matchedExtends[3]) ? $matchedExtends[3] : null;
                $groupUseFound  = false;

                foreach ($matchedGroupUse[1] as $groupMatch) {
                    $groupMatch = explode(",", $groupMatch);
                    foreach ($groupMatch as $group) {
                        $group = trim($group);
                        if ($group == $matched["class"]) {
                            $groupUseFound = true;
                        }
                    }
                }

                if ($classFound || $filePath === $matched["path"] || $matchedExtends === $matchUseClass || $groupUseFound) {
                    if ($groupUseFound) {
                        $used = isset($this->used[$pattern]) ? $this->used[$pattern] : [];
                    }

                    $fromPath = str_replace($this->getBasePath(), "", $fromPath);
                    $filePath = str_replace($this->getBasePath(), "", $filePath);
                    $keymap   = [
                        "in"      => $filePath,
                        "from"    => $fromPath,
                        "pattern" => $pattern
                    ];

                    if (!in_array($keymap, $resource)) {
                        $this->used = array_merge_recursive($code, $this->used);
                        $used    = isset($this->used[$pattern]) ? $this->used[$pattern] : [];
                        $methods = isset($code[$pattern]) ? $code[$pattern] : [];
                        $methods = array_unique($methods);
                        sort($methods);

                        $content = [];
                        $content["pattern"]   = $pattern;
                        $content["match"]     = $matched["class"];
                        $content["path"]      = $filePath;
                        $content["found"]     = $fromPath;
                        $content["namespace"] = $matched["namespace"];
                        $content["method"]    = $methods;

                        $unused = array_diff($matched["functions"], $used);
                        $this->unused[$pattern] = isset($this->unused[$pattern]) ? $this->unused[$pattern] : [];
                        $this->unused[$pattern] = $this->unused[$pattern] ? array_intersect($this->unused[$pattern], $unused) : $unused;

                        if ($resource) {
                            fputs($fn, ",");
                        }

                        $resource[] = $keymap;
                        fputs($fn, json_encode($content, JSON_PRETTY_PRINT));
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
     *
     * @see http://stackoverflow.com/questions/503871/best-way-to-automatically-remove-comments-from-php-code
     */
    private function getClassAndNamespaceFromFilePath($path)
    {
        $fileStr     = file_get_contents($path);
        $fileContent = "";

        $commentTokens   = [T_COMMENT];
        $commentTokens[] = defined('T_DOC_COMMENT') ? T_DOC_COMMENT : T_ML_COMMENT;

        $tokens = token_get_all($fileStr);

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (in_array($token[0], $commentTokens))
                    continue;

                $token = $token[1];
            }

            $fileContent .= $token;
        }

        if (preg_match(self::REGEX["class"], $fileContent, $match)) {
            preg_match(self::REGEX["namespace"], $fileContent, $namespace);
            preg_match_all(self::REGEX["function"], $fileContent, $functions);
            $type = [];
            foreach ($functions[3] as $key => $function) {
                $type[$function] = $functions[1][$key];
            }

            return [
                "path"      => $path,
                "class"     => $match[1],
                "namespace" => isset($namespace[1]) ? $namespace[1] : "",
                "functions" => $functions[3],
                "type"      => $type,
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

        // parser created
        $traverser = new NodeTraverser();
        $parser    = (new ParserFactory)->create(ParserFactory::PREFER_PHP5);
        $content   = php_strip_whitespace($path);
        if (preg_match(self::REGEX["extends"], $content, $match)) {
            // visitor class method
            $classVisitor = new Class_();
            $traverser->addVisitor($classVisitor);

            // parse it
            $stmts = $parser->parse($content);
            $traverser->traverse($stmts);

            // 取得所有「使用到」的class method
            $code = $classVisitor->getCode();
        }

        return $code;
    }

    /**
     * 取得目前正在處理的檔案
     * @return resource
     */
    private function setResultResource()
    {
        $this->fn = fopen("php://memory", "wb");
    }

    /**
     * 取得目前正在處理的檔案
     * @return resource
     */
    private function getResultResource()
    {
        return $this->fn;
    }
}
