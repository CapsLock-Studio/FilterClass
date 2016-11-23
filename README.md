FilterClass
--

![](https://travis-ci.org/CapsLock-Studio/FilterClass.svg)
[![Coverage Status](https://coveralls.io/repos/github/CapsLock-Studio/FilterClass/badge.svg?branch=master)](https://coveralls.io/github/CapsLock-Studio/FilterClass?branch=master)
[![Code Climate](https://codeclimate.com/github/CapsLock-Studio/FilterClass/badges/gpa.svg)](https://codeclimate.com/github/CapsLock-Studio/FilterClass)
[![Issue Count](https://codeclimate.com/github/CapsLock-Studio/FilterClass/badges/issue_count.svg)](https://codeclimate.com/github/CapsLock-Studio/FilterClass)
![](http://php7ready.timesplinter.ch/Codeception/Codeception/badge.svg)
![](https://img.shields.io/badge/license-MIT-blue.svg)


Author: michael34435@capslock.tw

This package will help you to analyze two folders and find out function you do not use(DoD).

You have to assign two folders.  
The first folder we called `folderA` that it is your based folder.  
The second folder we called `folderB` which you want to analyze.  

## Composer
```sh
composer global require "capslock-studio/filter-class"

ln -s $HOME/.composer/vendor/bin/php-filter-class /usr/local/bin/php-filter-class
```

## Usage
```sh
php-filter-class [-t {folderB}] [-t {folderB-2}] [-f {folderA}]

-f    folderA
-t    folderB
```

## Limitation(Knowing Issue)
1. Object in function parameter  
2. Factory mode
3. Same function name in both parent and child class
