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
        $this->fn       = fopen("result1", "wb");
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
    private function analyzeContainClass($dir, $matchClassPath, &$resource = [])
    {
        $fn = $this->getResultResource();


        $files = glob("{$dir}/*.php");
        $dirs = glob("{$dir}/*", GLOB_ONLYDIR);

        foreach ($files as $filePath) {
            $fileContent = file_get_contents($filePath);
            if ($this->getShowLinesFlag()) {
                foreach (explode("\n", $fileContent) as $line => $content) {
                    $this->analyzeContent($content, $matchClassPath, $resource, $filePath, ($line + 1));
                }
            } else {
                $this->analyzeContent($fileContent, $matchClassPath, $resource, $filePath);
            }
        }

        foreach ($dirs as $dirValue) {
            $this->analyzeContainClass($dirValue, $matchClassPath, $resource);
        }
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
    private function analyzeContent($fileContent, $matchClassPath, &$resource, $filePath, $line = 0)
    {
        $fn = $this->getResultResource();

        foreach ($matchClassPath as $key => $matchClass) {
            foreach ($matchClass as $matched) {
                if (strlen($matched["namespace"]) > 1 && strlen($matched["class"]) > 1 && preg_match("/".quotemeta("{$matched["namespace"]}\\{$matched["class"]}")."/", $fileContent)) {
                    if (!in_array(["in" => $filePath, "from" => $key], $resource)) {
                        $key      = str_replace($this->getBasePath(), "", $key);
                        $filePath = str_replace($this->getBasePath(), "", $filePath);
                        $content  = "find pattern:{$matched["namespace"]}\\{$matched["class"]}\n";
                        $content .= "find match class: {$matched["class"]}\n";
                        $content .= "in path: {$filePath}\n";
                        $content .= "from path: {$key}\n";
                        $content .= "find namespace: {$matched["namespace"]}\n";
                        $content .= empty($line) ? "\n\n" : "in lines: {$line}\n\n";
                        fwrite($fn, $content);
                    }
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
    private function getClassAndPath($dir, &$result = [])
    {
        $files = glob("{$dir}/*.php");
        $dirs  = glob("{$dir}/*", GLOB_ONLYDIR);

        foreach ($files as $filePath) {
            $fileContent = file_get_contents($filePath);
            if (preg_match("/class ([A-Za-z0-9_]+)/", $fileContent, $match) && isset($match[1])) {
                $result[$filePath] = is_array($result[$filePath]) ? [] : $result[$filePath];
                preg_match("/namespace (.*);/", $fileContent, $mnamespace);
                $result[$filePath][] = [
                    "class"     => $match[1],
                    "namespace" => isset($mnamespace[1]) ? $mnamespace[1] : "",
                ];
            }
        }

        foreach ($dirs as $dirValue) {
            $this->getClassAndPath($dirValue, $result, $matchClass);
        }
    }
}
