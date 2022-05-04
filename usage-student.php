<?php
require_once('initTsugi.php');

include('views/dao/menu.php');
$main = new \CT\CT_Main($_SESSION["ct_id"]);
$exercises = $main->getExercises();
$students = \CT\CT_User::getUsersWithAnswers($_SESSION["ct_id"]);
//Los usuarios no tienen respuestas, porque las respuestas no se envían

$studentAndDate = array();
foreach($students as $student) {
    $studentAndDate[$student->getUserId()] = new DateTime($student->getMostRecentAnswerDate($_SESSION["ct_id"]));
}
// Sort students by mostRecentDate desc
arsort($studentAndDate);

$totalExercises = count($exercises);
// $students = [new \CT\CT_User($USER->id)];

$usages = CT\CT_Usage::getUsages($exercises, $students);

echo $twig->render('usage/usage-student.php.twig', array(
    'OUTPUT' => $OUTPUT,
    'help' => $help(),
    'menu' => $menu,
    'exercises' => $exercises,
    'totalExercises' => $totalExercises,
    'students' => $main->getStudentsOrderedByDate(), // $students,
    'usages' => $usages,
));
