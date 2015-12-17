<?php

include "ClassAnalyzer.php";

$f = new ClassAnalyzer(
    [
        'fromPath' => '/Users/michael/Documents/hiiir/mall-dev/vendor',
        'toPath' => '/Users/michael/Documents/hiiir/mall-dev/app'
    ]
);
$f->setShowLinesFlag(true);
$f->analyze();
