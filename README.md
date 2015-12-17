ClassAnalyzer
--

Author: michael_kuan@hiiir.com

這是一個幫忙分析兩邊的資料夾用引用到的class，方便分析瞭解更動code之後QA需要重新測試什麼項目。


以下稱作引入的資料夾為folderA  
分析引入folderA的class所在資料夾為folderB


## 限制
受分析的folderA class必須要有namespace供folderB使用，不然無法使用(這樣會方便識別為一個package)。

## 開關
目前提供了一個「顯示所在行數」的開關可以使用，關閉的話同樣的class資料不會再度在結果顯示，它會只顯示一次。

## 使用方式
    // 使用ClassAnalyzer
    include "ClassAnalyzer.php";

    $analyzer = new ClassAnalyzer(
        [
            // folderA位置
            'fromPath' => '/Users/michael/Documents/hiiir/mall-dev/vendor',

            // folderB位置
            'toPath' => '/Users/michael/Documents/hiiir/mall-dev/app'
        ]
    );

    // 開啟所在行數的開關
    $analyzer->setShowLinesFlag(true);

    // 設定folderB的母目錄(產出結果的時候會過濾掉多餘字串)
    $analyzer->setBasePath('/Users/michael/Documents/hiiir/mall-dev/app');

    // 開始分析
    $analyzer->analyze();
