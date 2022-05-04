<?php

require_once "../initTsugi.php";
global $translator;

$exerciseId = $_POST["exerciseId"];
$understandabilityScore = $_POST['rateUnderstandability'];
$difficultyScore = $_POST['rateDifficulty'];
$timeScore = $_POST['rateTime'];

$user = new \CT\CT_User($USER->id);
$usage = \CT\CT_Usage::constructValues($exerciseId, $user, $understandabilityScore, $difficultyScore, $timeScore);

$code = $usage->save();
$resulta = array();
/*
 TODO: With the current logic there's a bug:
    If the student answers the question and then,
    when the usage modal is shown, the student refreshes the page,
    That question will never have the usage info for that user,
    because the modal will not show again after the system detects that a previous answer exists,
    to fix this the modal should show unless there's already an usage for that exerciseId-userId-ctId
*/

if ($code == 200) {
    $_SESSION['success'] = $translator->trans('backend-messages.usage.send.success');
} else {
    $_SESSION['error'] = $translator->trans('backend-messages.usage.send.error');
}

$result["flashmessage"] = $OUTPUT->flashMessages();
exit;
