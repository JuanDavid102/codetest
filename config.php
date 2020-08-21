<?php

// Make this require relative to the parent of the current folder
// http://stackoverflow.com/questions/24753758

require_once dirname(__DIR__)."/config.php";
require 'vendor/autoload.php';

$CFG->CT_mainTypes = array(
    'sql',
    'programming',
);

$CFG->CT_TypeClasses = array(
    'sql' => \CT\CT_QuestionSQL::class,
    'programming' => \CT\CT_QuestionCode::class,
);

$CFG->twig = array(
    'viewsPath' => __DIR__."/views",
    'debug' => true,
    'cachePath' => __DIR__."/tmp",
);
