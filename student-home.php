<?php
require_once('initTsugi.php');
include('views/dao/menu.php');

$SetID = $_SESSION["ct_id"];
$main = new \CT\CT_Main($_SESSION["ct_id"]);
$toolTitle = $main->getTitle() ? $main->getTitle() : "Code Test";

$exercises = $main->getExercises();
$totalExercises = count($exercises);
$currentExerciseNumber = isset($_GET['exerciseNum']) ? $_GET['exerciseNum'] : 1;

if($totalExercises > 0){
    
    $firstExerciseId = $exercises[$currentExerciseNumber - 1]->getAkId();
    $exerciseTestsResponse = $REST_CLIENT_REPO->getClient()->request('GET', "api/exercises/$firstExerciseId/tests");
    $testsResponseObj = $exerciseTestsResponse->toArray();
    $testsObj = sizeof($testsResponseObj) > 0 ? $testsResponseObj[0] : null;
}

$user = new \CT\CT_User($USER->id);

echo $twig->render('pages/student-view.php.twig', array(
    'OUTPUT' => $OUTPUT,
    'help' => $help(),
    'menu' => $menu,
    'user' => $user,
    'exercises' => $exercises,
    'testsObj' => $testsObj,
    'totalExercises' => $totalExercises,
    'currentExerciseNumber' => $currentExerciseNumber,
    'exerciseNum' => $currentExerciseNumber,
    'main' => $main,
    'validatorService' => $validatorService,
    'CFG' => $CFG,
));

