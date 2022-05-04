<?php
require_once('initTsugi.php');
include('views/dao/menu.php');

if (!$USER->instructor) {
    header('Location: ' . addSession('../student-home.php'));
    exit;
}

$main = new \CT\CT_Main($_SESSION["ct_id"]);
$owner = $_SESSION["lti"]["user_displayname"];
$language = array_keys($_GET, 'language') ? $_GET['language'] : "PHP";
$newExercise = new CT\CT_ExerciseCode();

echo $twig->render('pages/exercise-creation.php.twig', array(
    'main' => $main,
    'type' => $language,
    'owner' => $owner,
    'newExercise' => $newExercise,
    'OUTPUT' => $OUTPUT,
    'CFG' => $CFG,
    'menu' => $menu,
    'validatorService' => $validatorService,
    'help' => $help(),
));

