<?php

require_once "../initTsugi.php";
global $translator;

if ($USER->instructor) {
    $result = array();
    $exercisePost = $_POST["exercise"];

    if (isset($exercisePost['title']) && trim($exercisePost['title']) != '' && isset($exercisePost['exercise_language']) && trim($exercisePost['exercise_language']) != '') {
        $exercisePost['author'] = $_SESSION["lti"]["user_displayname"];
        $exercisePost['owner'] = $_SESSION["lti"]["link_title"];
        $exercisePost['sessionLanguage'] = isset($_SESSION["lti"]["user_locale"]) ? $_SESSION["lti"]["user_locale"] : "en";
        $main = new \CT\CT_Main($_SESSION["ct_id"]);
        $exercise = $main->createExercise($exercisePost,strtolower($exercisePost['exercise_language']),$exercisePost["difficulty"]);
        $exercises = Array();
        array_push($exercises, $exercise);

        //save the exercise on the repository
        $result = $main->saveExercises($exercises);

        //map the returned exercise
        $object = json_decode($result);
        $exercise1 = \CT\CT_Test::mapObjectToCodeExercise($object);
        $exercise1->setCtId($_SESSION["ct_id"]);

        //Save the returned exercise on the db
        $exercise1->save();

        $_SESSION['success'] = $translator->trans('backend-messages.add.exercise.success');
    } else {

        $_SESSION['error'] = $translator->trans('backend-messages.add.exercise.failed');
    }
    $OUTPUT->buffer = true;
    header('Content-Type: application/json');

    echo json_encode($result, JSON_HEX_QUOT | JSON_HEX_TAG);

    exit;
} else {
    header('Location: ' . addSession('../student-home.php'));
}

