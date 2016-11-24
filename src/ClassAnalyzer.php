<?php

namespace CapsLockStudio\FilterClass;

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

    private $unused         = [];
    private $used           = [];
    private $lines          = [];
    private $collectedClass = [];
    private $total          = 0;
    private $config         = [
        "toPath"   => [],
        "fromPath" => "",
        "basePath" => "",
    ];

    /**
     * 建構子
     * @param array $config ["fromPath" => "分析的資料夾(folderA)", “toPath” => “目標的資料夾(folderB)”]
     */
    public function __construct(array $config = [])
    {
        $templateConfig = ["fromPath" => "", "toPath" => "", "basePath" => ""];
        $config         = array_merge($templateConfig, $config);
        $this->fromPath = $config["fromPath"];
        $this->toPath   = $config["toPath"];
        $this->basePath = $config["basePath"];

        if (!is_dir($this->fromPath)) {
            throw new Exception("Defined `fromPath` is not valid");
        }

        foreach ($this->toPath as $path) {
            if (!is_dir($path)) {
                throw new Exception("Defined `toPath` is not valid");
            }
        }
    }

    /**
     * 開始分析
     * @return void
     */
    public function analyze()
    {
        $this->getClassAndPath($this->fromPath, $this->collectedClass);
        $this->analyzeContainClass($this->fromPath);

        foreach ($this->toPath as $path) {
            $this->analyzeContainClass($path);
        }

        $unusedClass = array_keys($this->unused);
        foreach ($unusedClass as $class) {
            if (isset($this->used[$class])) {
                $this->unused[$class] = array_diff($this->unused[$class], $this->used[$class]);
            }
        }
    }

    /**
     * magic function __get
     * @param string $name key
     * @return mixed
     */
    public function __get($name)
    {
        return array_key_exists($name, $this->config) ? $this->config[$name] : false;
    }

    /**
     * magic function ref: $this->config
     * @param string $name  key
     * @param mixed  $value value
     */
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->config)) {
            $type = gettype($this->config[$name]);
            if ($type === "array") {
                $value              = is_array($value) ? $value : [$value];
                $this->config[$name] = array_merge($this->config[$name], $value);
            } else {
                $this->config[$name] = $value;
            }
        }
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
     * 取得行數比
     *
     * @return array
     */
    public function getLines()
    {
        return $this->lines;
    }

    /**
     * 取得總數量
     * @return int
     */
    public function getTotal()
    {
        foreach ($this->collectedClass as $map) {
            foreach ($map as $data) {
                $pattern      = "{$data["namespace"]}\\{$data["class"]}";
                $this->total += isset($this->lines[$pattern]) ? array_sum($this->lines[$pattern]) : 0;
            }
        }

        return $this->total;
    }

    /**
     * 分析folderB含有的class
     * @param  string $dir            來源資料夾
     * @param  array  $matchClassPath folderA的class資料
     * @param  array  $resource       folderB的class資料
     * @return void
     */
    private function analyzeContainClass($dir, array &$resource = [])
    {
        $this->iterDir("{$dir}/*", function ($filePath) use (&$resource) {
            $this->analyzeContent($filePath, $resource);
        }, function ($filePath) use (&$resource) {
            $this->analyzeContainClass($filePath, $resource);
        });
    }

    /**
     * 分析內容
     * @param  string  $filePath    檔案內容
     * @param  array   $matchClassPath folderA的class資料
     * @param  array   $resource       folderB的class資料
     * @return void
     */
    private function analyzeContent($filePath, array &$resource)
    {
        $analyzer    = new CodeAnalyzer($filePath);
        $fileContent = $analyzer->getTrimedCode();
        $code        = $analyzer->getCode();
        $lines       = $analyzer->getLines();
        $this->lines = array_merge($lines, $this->lines);
        foreach ($this->collectedClass as $fromPath => $matchClass) {
            foreach ($matchClass as $matched) {
                $pattern       = $matched["namespace"] ? "{$matched["namespace"]}\\{$matched["class"]}" : $matched["class"];
                $regexUse      = "/" . quotemeta($pattern) . "\s*(\s+as\s+(.+);|;|,|\()/";
                $regexGroupUse = "/" . quotemeta($matched["namespace"]) . "\\\{([A-Za-z0-9_, ]+)\}/";

                preg_match_all($regexGroupUse, $fileContent, $matchedGroupUse);
                @preg_match(self::REGEX["extends"], $fileContent, $matchedExtends);

                $classFound     = @preg_match($regexUse, $fileContent, $matchedUse);
                $matchUseClass  = isset($matchedUse[2]) ? $matchedUse[2] : $matched["class"];
                $matchedExtends = isset($matchedExtends[3]) ? $matchedExtends[3] : null;
                $groupUseFound  = $this->isGroupMatched($matchedGroupUse[1], $matched["class"]);

                $condition = $classFound || $filePath === $matched["path"] || $matchedExtends === $matchUseClass || $groupUseFound;

                $fromPath = str_replace($this->basePath, "", $fromPath);
                $filePath = str_replace($this->basePath, "", $filePath);
                $keymap   = [
                    "in"      => $filePath,
                    "from"    => $fromPath,
                    "pattern" => $pattern
                ];

                if ($condition && !in_array($keymap, $resource)) {
                    $this->used = array_merge_recursive($code, $this->used);
                    $used       = isset($this->used[$pattern]) ? $this->used[$pattern] : [];
                    $unused     = array_diff($matched["functions"], $used);
                    $this->unused[$pattern] = isset($this->unused[$pattern]) ? $this->unused[$pattern] : [];
                    $this->unused[$pattern] = $this->unused[$pattern] ? array_intersect($this->unused[$pattern], $unused) : $unused;

                    $resource[] = $keymap;
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
        $fileContent = (new CodeAnalyzer($path))->getTrimedCode();
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

    /**
     * PHP7 group
     * @param array  $matchedGroupUse GROUP USE
     * @param string $class           使用到的class
     *
     * @return boolean
     */
    private function isGroupMatched(array $matchedGroupUse, $class)
    {
        foreach ($matchedGroupUse as $groupMatch) {
            $groupMatch = preg_split("/,/", $groupMatch, PREG_SPLIT_NO_EMPTY);
            if (in_array($class, $groupMatch)) {
                return true;
            }
        }

        return false;
    }
}
