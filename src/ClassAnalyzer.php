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

    private $fn             = null;
    private $show           = false;
    private $fromPath       = "";
    private $toPath         = "";
    private $basePath       = "";
    private $report         = "";
    private $total          = 0;
    private $unused         = [];
    private $used           = [];
    private $lines          = [];
    private $mapping        = [];
    private $collectedClass = [];

    /**
     * 建構子
     * @param array $config ["fromPath" => "分析的資料夾(folderA)", “toPath” => “目標的資料夾(folderB)”]
     */
    public function __construct(array $config = [])
    {
        $templateConfig = ["fromPath" => "", "toPath" => ""];
        $config         = array_merge($templateConfig, $config);
        $this->fromPath = $config["fromPath"];
        $this->toPath   = $config["toPath"] ?: [$this->fromPath];
        $this->toPath   = is_array($this->toPath) ? $this->toPath : [$this->toPath];

        $this->fn = fopen("php://memory", "wb");

        if (!is_dir($this->fromPath)) {
            throw new Exception("Defined `fromPath` is not valid");
        }

        foreach ($this->toPath as $path) {
            if (!is_dir($path)) {
                throw new Exception("Defined `toPath` is not valid");
            }
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
        $fn = $this->fn;
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
        $this->getClassAndPath($this->getFromPath(), $this->collectedClass);
        $this->analyzeContainClass($this->getFromPath());

        foreach ($this->getToPath() as $path) {
            $this->analyzeContainClass($path);
        }

        foreach ($this->unused as $class => $method) {
            if (isset($this->used[$class])) {
                $this->unused[$class] = array_diff($this->unused[$class], $this->used[$class]);
            }
        }

        fputs($this->fn, "]");
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
        $fn          = $this->fn;
        $analyzer    = new CodeAnalyzer($filePath);
        $fileContent = $analyzer->getTrimedCode();
        $code        = $analyzer->getCode();
        $lines       = $analyzer->getLines();
        $this->lines = array_merge($lines, $this->lines);
        foreach ($this->collectedClass as $fromPath => $matchClass) {
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
}
