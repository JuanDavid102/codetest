<?php


namespace CT;

use \Tsugi\Core\Result;

class CT_Main implements \JsonSerializable
{
    private $ct_id;
    private $user_id;
    private $context_id;
    private $link_id;
    private $title;
    private $seen_splash;
    private $preloaded;
    private $shuffle;
    private $points;
    private $modified;
    private $exercises;

    public function __construct($ct_id = null)
    {
        $context = array();
        if (isset($ct_id)) {
            $query = \CT\CT_DAO::getQuery('main', 'getByCtId');
            $arr = array(':ct_id' => $ct_id);
            $context = $query['PDOX']->rowDie($query['sentence'], $arr);
        }
        \CT\CT_DAO::setObjectPropertiesFromArray($this, $context);
    }

    public static function getMainFromContext($context_id, $link_id, $user_id = null, $current_time = null) {
        $object = self::getMain($context_id, $link_id);
        if (!$object->getCtId()) {
            $object = self::createMain($user_id, $context_id, $link_id, $current_time);
        }
        return $object;
    }

    public static function getMainsFromContext($context_id) {
        $query = \CT\CT_DAO::getQuery('main', 'getMainsFromContext');
        $arr = array(':context_id' => $context_id);
        return \CT\CT_DAO::createObjectFromArray(self::class, $query['PDOX']->allRowsDie($query['sentence'], $arr));
    }

    public static function getMain($context_id, $link_id) {
        $query = \CT\CT_DAO::getQuery('main','getMain');
        $arr = array(':context_id' => $context_id, ':link_id' => $link_id);
        $context = $query['PDOX']->rowDie($query['sentence'], $arr);
        return new self($context['ct_id']);
    }

    public static function createMain($user_id, $context_id, $link_id, $current_time) {
        $query = \CT\CT_DAO::getQuery('main','insert');
        $arr = array(':userId' => $user_id, ':contextId' => $context_id, ':linkId' => $link_id, ':currentTime' => $current_time);
        $query['PDOX']->queryDie($query['sentence'], $arr);
        return new self($query['PDOX']->lastInsertId());
    }

    //Save test on the repo
    function saveTest($tests) {
        global $REST_CLIENT_REPO;
        $url = "api/tests/createTest/";

        $response = $REST_CLIENT_REPO->getClient()->request('GET', $url, [
            'json' => $tests
        ]);

        $responseContent = $response->getContent();
        return $responseContent;
    }

    //Save exercise on the repo
    function saveExercises($exercises) {
        global $REST_CLIENT_REPO;

        $saveExerciseRequest = $REST_CLIENT_REPO->
                                getClient()->
                                request('POST','api/exercises/createExercise', [
                                    'json' => $exercises
                                ]);

        return $saveExerciseRequest->getContent();
    }

    //Create exercise object
    function createExercise($context, $language, $difficulty) {
        global $CFG;
        if(is_array($context)) {
            $class = $CFG->ExerciseProperty['class'];
            $exercise = new $class();
            \CT\CT_DAO::setObjectPropertiesFromArray($exercise, $context);
                $array = self::getLanguages();
                foreach ( $array as $k => $v){
                 if($v['name'] == $language){

                      $exercise->setExerciseLanguage(strtolower($language));
                 }
                }
            $exercise->setDifficulty($difficulty);
        }
        $exercise->setCtId($this->getCtId());
        return $exercise;
    }

     function importExercise($context) {
        global $CFG;
        if(is_array($context)) {
            $class = $CFG->ExerciseProperty['class'];
            $exercise = new $class();
            \CT\CT_DAO::setObjectPropertiesFromArray($exercise, $context);
        }
        $exercise->setCtId($this->getCtId());
        return $exercise;
    }

    public static function getTypes(){
        global $CFG;
        return $CFG->programmingLanguajes;

    }

    function getLanguages() {
        global $CFG;
        return $CFG->ExerciseProperty['codeLanguages'];
    }

    function getProperty($property) {
        global $CFG;
        return $CFG->ExerciseProperty[$property];
    }

    /**
     * @return \CT\CT_Exercise[] $exercises
     */
    function getExercises() {
        if (!is_array($this->exercises)) {
            $this->exercises = array();
            $query = \CT\CT_DAO::getQuery('main', 'getExercises');
            $arr = array(':ct_id' => $this->getCtId());
            $exercises = $query['PDOX']->allRowsDie($query['sentence'], $arr);
            $this->exercises = \CT\CT_DAO::createObjectFromArray(\CT\CT_Exercise::class, $exercises);
        }
        return $this->exercises;
    }

    function getExercisesForImport() {
        if(!is_array($this->exercises)) {
            $this->exercises = array();
            $query = \CT\CT_DAO::getQuery('main', 'getExercises');
            $arr = array(':ct_id' => $this->getCtId());
            $exercises = $query['PDOX']->allRowsDie($query['sentence'], $arr);
            $this->exercises = \CT\CT_DAO::createObjectFromArray(\CT\CT_Exercise::class, $exercises);
        }
        return $this->exercises;
    }

    function getTest() {
        // TODO Crear array de objetos Code o SQL según corresponda
        // a través de JOIN con la tabla correspondiente
        if (!is_array($this->exercises)) {
            $this->exercises = array();
            $query = \CT\CT_DAO::getQuery('main', 'getExercises');
            $arr = array(':ctId' => $this->getCtId());
            $exercises = $query['PDOX']->allRowsDie($query['sentence'], $arr);
            $this->exercises = \CT\CT_DAO::createObjectFromArray(\CT\CT_Exercise::class, $exercises);
        }
        return $this->exercises;

        $response = \CT\CT_Exercise::findExercisesForImport();
        $exercises = array();
        foreach ($response as $exercise) {
            $CTExercise = new CT_Exercise();
            $CTExercise->setExerciseId($exercise->id);
            $CTExercise->setTitle($exercise->title);
            $CTExercise->setDifficulty($exercise->difficulty);
            array_push($exercises, $CTExercise);
        }
        return $exercises;
    }

    public function getUserGrade($userId)
    {
        // Get result record for user
        $query = \CT\CT_DAO::getQuery('main','getResultUser');
        $arr = array(':user_id' => $userId, ':link_id' => $this->getLinkId());
        $row = $query['PDOX']->rowDie($query['sentence'], $arr);
        return $row;
    }

    public function getUserGradeValue($userId)
    {
        $grade = $this->getUserGrade($userId)['grade'];
        if(is_null($grade)) {
            $this->gradeUser($userId);
            $grade = $this->getUserGrade($userId)['grade'];
        }
        $value = $this->getPoints() * $grade;
        return $value;
    }

    public function getGradesCount(){
        $query = \CT\CT_DAO::getQuery('grade','count');
        $arr = array(':ctid' => $this->getCtId());
        $context = $query['PDOX']->rowDie($query['sentence'], $arr);
        return $context['count'];
    }

    public function getGradesCtId(){
        $query = \CT\CT_DAO::getQuery('grade','gradesCtid');
        $arr = array(':ctid' => $this->getCtId());
        return \CT\CT_DAO::createObjectFromArray(CT_Grade::class, $query['PDOX']->allRowsDie($query['sentence'], $arr));

    }

    public function gradeUser($userId, $grade = null)
    {
        global $translator;
        if(is_null($grade)) {
            $corrects = 0;
            $totalExercises = count($this->getExercises());
            foreach ($this->getAnswersByUser($userId) as $answer) {
                if($answer->getAnswerSuccess()) $corrects++;
            }
            $grade = $corrects * $this->getPoints() / $totalExercises;
        }
        $student = new \CT\CT_User($userId);
        $currentGrade = $student->getGrade($this->getCtId());
        $currentGrade->setCtId($this->getCtId());
        $currentGrade->setUserId($student->getUserId());
        $currentGrade->setGrade($grade);
        $currentGrade->save();

        $_SESSION['success'] = $translator->trans('backend-messages.grade.saved.success');

        // Calculate percentage and post
        $percentage = ($grade * 1.0) / $this->getPoints();

        $row = $this->getUserGrade($userId);

        Result::gradeSendStatic($percentage, $row);
    }


    function getStudentsOrderedByDate() {
        $studentsUnordered = \CT\CT_User::getUsersWithAnswers($this->getCtId());
        $studentAndDate = array();
        foreach($studentsUnordered as $student) {
            $studentAndDate[$student->getMostRecentAnswerDate($this->getCtId())] = $student;
        }
        // Sort students by mostRecentDate desc
        krsort($studentAndDate);
        $students = array();
        $index = 0;
        foreach ($studentAndDate as $date => $user) {
            $mostRecentDate = new \DateTime($date);
            $students[$index]['user'] = $user;
            $students[$index]['isInstructor'] = $user->isInstructor($this->getContextId());
            if (!$students[$index]['isInstructor']) {
                $students[$index]['mostRecentDate'] = $mostRecentDate;
                $students[$index]['formattedMostRecentDate'] = $mostRecentDate->format("m/d/y") . " | " . $mostRecentDate->format("h:i A");
                $students[$index]['numberAnswered'] = $user->getNumberExercisesAnswered($this->getCtId());
                $students[$index]['grade'] = $user->getGrade($this->getCtId())->getGrade();
            }
            $index++;
        }
        return $students;
    }

    private function getAnswersByUser($userId)
    {
        $query = \CT\CT_DAO::getQuery('main', 'getAnswersByUser');
        $arr = array(':ctId' => $this->getCtId(), ':userId' => $userId);
        return \CT\CT_DAO::createObjectFromArray(CT_Answer::class, $query['PDOX']->allRowsDie($query['sentence'], $arr));
    }


    /**
     * @return mixed
     */
    public function getCtId()
    {
        return $this->ct_id;
    }

    /**
     * @param mixed $ct_id
     */
    public function setCtId($ct_id)
    {
        $this->ct_id = $ct_id;
    }

    /**
     * @return mixed
     */
    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * @param mixed $user_id
     */
    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }

    /**
     * @return mixed
     */
    public function getContextId()
    {
        return $this->context_id;
    }

    /**
     * @param mixed $context_id
     */
    public function setContextId($context_id)
    {
        $this->context_id = $context_id;
    }

    /**
     * @return mixed
     */
    public function getLinkId()
    {
        return $this->link_id;
    }

    /**
     * @param mixed $link_id
     */
    public function setLinkId($link_id)
    {
        $this->link_id = $link_id;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param mixed $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return mixed
     */
    public function getSeenSplash()
    {
        return $this->seen_splash;
    }

    /**
     * @param mixed $seen_splash
     */
    public function setSeenSplash($seen_splash)
    {
        $this->seen_splash = $seen_splash;
    }

    /**
     * @return mixed
     */
    public function getPreloaded()
    {
        return $this->preloaded;
    }

    /**
     * @param mixed $preloaded
     */
    public function setPreloaded($preloaded)
    {
        $this->preloaded = $preloaded;
    }

    /**
     * @return mixed
     */
    public function getShuffle()
    {
        return $this->shuffle;
    }

    /**
     * @param mixed $shuffle
     */
    public function setShuffle($shuffle)
    {
        $this->shuffle = $shuffle;
    }

    /**
     * @return mixed
     */
    public function getPoints()
    {
        return $this->points;
    }

    /**
     * @param mixed $points
     */
    public function setPoints($points)
    {
        $this->points = $points;
    }

    /**
     * @return mixed
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * @param mixed $modified
     */
    public function setModified($modified)
    {
        $this->modified = $modified;
    }

    public function save() {
        $query = \CT\CT_DAO::getQuery('main','update');
        $arr = array(
            ':user_id' => $this->getUserId(),
            ':context_id' => $this->getContextId(),
            ':link_id' => $this->getLinkId(),
            ':title' => $this->getTitle(),
            ':seen_splash' => $this->getSeenSplash(),
            ':preloaded' => $this->getPreloaded(),
            ':shuffle' => $this->getShuffle(),
            ':points' => $this->getPoints(),
            ':modified' => $this->getModified(),
            ':ctId' => $this->getCtId()
        );
        $query['PDOX']->queryDie($query['sentence'], $arr);
    }

    //necessary to use json_encode with exercise objects
    public function jsonSerialize() {
        return [
            'user_id' => $this->getUserId(),
            'context_id' => $this->getContextId(),
            'link_id' => $this->getLinkId(),
            'title' => $this->getTitle(),
            'seen_splash' => $this->getSeenSplash(),
            'preloaded' => $this->getPreloaded(),
            'shuffle' => $this->getShuffle(),
            'points' => $this->getPoints(),
            'modified' => $this->getModified(),
            'ctId' => $this->getCtId()
        ];
    }

    function delete($user_id) {
        $query = \CT\CT_DAO::getQuery('main','delete');
        $arr = array(':mainId' => $this->getCtId(), ':userId' => $user_id);
        $query['PDOX']->queryDie($query['sentence'], $arr);
    }
}
