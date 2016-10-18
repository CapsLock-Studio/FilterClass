FilterClass
--

Author: michael34435@capslock.tw

這是一個幫忙分析兩邊的資料夾用引用到的class，方便分析瞭解更動code之後QA需要重新測試什麼項目。


以下稱作引入的資料夾為folderA  
分析引入folderA的class所在資料夾為folderB

## 安裝
    composer install 

## 開關
目前提供了一個「顯示所在行數」的開關可以使用，關閉的話同樣的class資料不會再度在結果顯示，它會只顯示一次。

## 使用方式
    ./bin/analyze -f {folderA} -t {folderB} [-t {folderB-2}] [--dead-code]

## 參數說明
    -f          參照資料夾
    -t          目的資料夾
    --dead-code 沒有用到的程式

## 限制
因為主要做靜態分析，不會實際走code stack，所以有幾個狀態無法被解析

1. 帶在參數內的物件變數  
2. 透過function產生物件(工廠模式)，這部份可以透過return去解析但是狀況太多會有各種問題(call stack限制)
