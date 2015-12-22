<?php

/**
 * @author michael_kuan <michael_kuan@hiiir.com>
 *
 * 一個簡單的分析器，可以分析folderA有在folderB用到什麼class(含namespace)
 */
class ClassAnalyzer
{
    private $fn       = null;
    private $fromPath = "";
    private $toPath   = "";
    private $basePath = "";
    private $switch   = false;

    /**
     * 建構子
     * @param array $config ["fromPath" => "分析的資料夾(folderA)", “toPath” => “目標的資料夾(folderB)”]
     */
    public function __construct(array $config = [])
    {
        $this->fn       = fopen("result-".date("YmdHis"), "wb");
        $this->fromPath = $config["fromPath"];
        $this->toPath   = $config["toPath"];

        if (!($this->getFromPath() || $this->getToPath())) {
            throw new Exception("No path found in config.", 1);
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
     * 設定是否取得每個用到的class行號(需要花比較久的時間)
     * @param boolean $lineSwitch
     */
    public function setShowLinesFlag($lineSwitch)
    {
        $this->switch = is_bool($lineSwitch) ? $lineSwitch : $this->switch;
    }

    /**
     * 結束後會做的動作
     */
    public function __destruct()
    {
        fclose($this->getResultResource());
    }

    /**
     * 開始分析
     * @return void
     */
    public function analyze()
    {
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
     * 取得是否顯示行數
     * @return [type] [description]
     */
    public function getShowLinesFlag()
    {
        return $this->switch;
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
        $files = glob("{$dir}/*");

        $this->iterDir($files, function ($filePath) use ($matchClassPath, &$resource) {
            $fileContent = file_get_contents($filePath);
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
     * @param  string  $filePath       目前分析的資料夾
     * @param  integer $line           目前在的行數
     * @return void
     */
    private function analyzeContent($fileContent, array $matchClassPath, array &$resource, $filePath)
    {
        $fn = $this->getResultResource();

        foreach ($matchClassPath as $key => $matchClass) {
            foreach ($matchClass as $matched) {
                $regex = "/".quotemeta("{$matched["namespace"]}\\{$matched["class"]}")."(;|,|\()/";
                if (strlen($matched["namespace"]) > 0 && strlen($matched["class"]) > 0 && preg_match($regex, $fileContent)) {
                    if ($this->getShowLinesFlag()) {
                        $lines = preg_grep($regex, explode("\n", $fileContent));
                        $lines = array_keys($lines);
                        $lines = array_map(function ($line) {return $line + 1;}, $lines);
                        $lines = array_reverse($lines);
                        $line  = array_pop($lines);
                    }

                    do {
                        $key      = str_replace($this->getBasePath(), "", $key);
                        $filePath = str_replace($this->getBasePath(), "", $filePath);
                        $pattern  = "{$matched["namespace"]}\\{$matched["class"]}";
                        $keymap   = [
                            "in"      => $filePath,
                            "from"    => $key,
                            "line"    => $line,
                            "pattern" => $pattern
                        ];
                        if (!in_array($keymap, $resource)) {
                            $content  = "find pattern: {$pattern}\n";
                            $content .= "find match class: {$matched["class"]}\n";
                            $content .= "in path: {$filePath}\n";
                            $content .= "from path: {$key}\n";
                            $content .= "find namespace: {$matched["namespace"]}\n";
                            $content .= empty($line) ? "\n\n" : "in lines: {$line}\n\n";
                            $resource[] = $keymap;
                            fwrite($fn, $content);

                            $line = isset($lines) ? array_pop($lines) : 0;
                        }
                    } while (!empty($line));
                }
            }
        }
    }

    /**
     * 取得folderA的class資料
     * @param  string $dir    folderA位置
     * @param  string $result 分析完畢的資料
     * @return void
     */
    private function getClassAndPath($dir, array &$result = [])
    {
        $files = glob("{$dir}/*");

        $this->iterDir($files, function ($filePath) use (&$result) {
            $fileContent = file_get_contents($filePath);
            if (preg_match("/class ([A-Za-z0-9_]+)/", $fileContent, $match) && isset($match[1])) {
                $result[$filePath] = is_array($result[$filePath]) ? [] : $result[$filePath];
                preg_match("/namespace (.*);/", $fileContent, $namespace);
                $result[$filePath][] = [
                    "class"     => $match[1],
                    "namespace" => isset($namespace[1]) ? $namespace[1] : "",
                ];
            }
        }, function ($filePath) use (&$result) {
            $this->getClassAndPath($filePath, $result);
        });
    }

    /**
     * 掃資料夾用
     * @param  array $files 檔案
     * @param  callable $cb1   如果是php
     * @param  callable $cb2   如果是資料夾
     * @return void
     */
    private function iterDir(array $files, callable $cb1, callable $cb2)
    {
        foreach ($files as $filePath) {
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
