FilterClass
--

![](https://travis-ci.org/CapsLock-Studio/FilterClass.svg)
[![Coverage Status](https://coveralls.io/repos/github/CapsLock-Studio/FilterClass/badge.svg?branch=master)](https://coveralls.io/github/CapsLock-Studio/FilterClass?branch=master)
![](http://php7ready.timesplinter.ch/Codeception/Codeception/badge.svg)
![](https://img.shields.io/badge/license-MIT-blue.svg)


Author: michael34435@capslock.tw

This package will help you to analyze two folders and find out function you do not use.

You have to assign two folders.  
The first folder we called `folderA` that it is your based folder.  
The second folder we called `folderB` which you want to analyze.  

## Install package from Composer
```sh
composer require global "capslock-studio/filter-class"
```

## Usage
```sh
./bin/analyze [-t {folderB}] [-t {folderB-2}] [-f {folderA}] [--dead-code]

-f          folderA
-t          folderB
--dead-code Functions you do not use
```

## Limitation(Knowing Issue)
1. Object in function parameter  
2. Factory mode
3. Same function name in both parent and child class
