<?php
require_once('initTsugi.php');
include('views/dao/menu.php');

if (!$USER->instructor) {
    header('Location: ' . addSession('../student-home.php'));
    exit;
}

$main = new \CT\CT_Main($_SESSION["ct_id"]);
$language = array_keys($_GET, 'language') ? $_GET['language'] : "PHP";
$newExercise = new CT\CT_Exercise();

echo $twig->render('pages/exercise-creation.php.twig', array(
    'main' => $main,
    'type' => $language,
    'newExercise' => $newExercise,
    'OUTPUT' => $OUTPUT,
    'CFG' => $CFG,
    'menu' => $menu,
    'help' => $help(),
));
