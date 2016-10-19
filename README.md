FilterClass
--

![](https://travis-ci.org/CapsLock-Studio/FilterClass.svg)
![](http://php7ready.timesplinter.ch/Codeception/Codeception/badge.svg)
![](https://img.shields.io/badge/license-MIT-blue.svg)


Author: michael34435@capslock.tw

這是一個幫忙分析兩邊的資料夾用引用到的class，方便分析瞭解更動code之後QA需要重新測試什麼項目。


以下稱作引入的資料夾為folderA  
分析引入folderA的class所在資料夾為folderB

## Composer安裝
    composer require "capslock-studio/filter-class"

## 安裝相依
    composer install

## 使用方式
    ./bin/analyze -t {folderB} [-t {folderB-2}] [-f {folderA}] [--dead-code]

## 建立使用捷徑
    ./install.sh

## 參數說明
    -f          參照資料夾
    -t          目的資料夾
    --dead-code 沒有用到的程式

## 限制
因為主要做靜態分析，不會實際走code stack，所以有幾個狀態無法被解析

1. 帶在參數內的物件變數  
2. 透過function產生物件(工廠模式)，這部份可以透過return去解析但是狀況太多會有各種問題(call stack限制)
