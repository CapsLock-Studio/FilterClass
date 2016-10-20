FilterClass
--

![](https://travis-ci.org/CapsLock-Studio/FilterClass.svg)
![](http://php7ready.timesplinter.ch/Codeception/Codeception/badge.svg)
![](https://img.shields.io/badge/license-MIT-blue.svg)


Author: michael34435@capslock.tw

This package will help you to analyze two folders and find out function you do not use.

You have to assign two folders.
The first folder we called `folderA` that it is your based folder.
The second folder we called `folderB` which you want to analyze.

## Composer Install
    composer require "capslock-studio/filter-class"

## You can clone this package independently
    composer install

## Usage
```
./bin/analyze [-t {folderB}] [-t {folderB-2}] [-f {folderA}] [--dead-code]

-f          folderA
-t          folderB
--dead-code Functions you do not use
```

## Create symbolic
    ./install.sh

## Limitation
1. Object in function parameter  
2. Factory mode
