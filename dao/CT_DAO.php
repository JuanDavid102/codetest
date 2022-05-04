<?php
namespace CT;

class CT_DAO {

    private $PDOX;

    public function __construct() {
        global $PDOX;
        global $CFG;
        $this->PDOX = $PDOX;
        $this->p = $CFG->dbprefix;
    }

    public static function getConnection() {
        global $PDOX;
        global $CFG;
        return array('PDOX' => $PDOX, 'p' => $CFG->dbprefix);
    }

    public static function setObjectPropertiesFromArray(&$object, $arrayProperties) {
        if(is_array($arrayProperties)){
            foreach($arrayProperties as $k => $v) {
                if($k== 'keywords'){
                    if(!is_array($v)){
                        $v = preg_split('/\r\n|\r|\n/', $v);
                    }else{
                        $v = $v;
                    }
                }
                $function = array($object, 'set'.preg_replace('/[^\da-z]/i', '', mb_convert_case($k, MB_CASE_TITLE)));
                if($v=='')$v=null;
                if(is_callable($function)) call_user_func_array($function, array($v));
            }
        }

    }


    public static function setObjectPropertiesToArray($object) {
        $objArray = array();
        $obj = new \ReflectionObject($object);
        foreach ($obj->getProperties() as $property) {
            $method = new \ReflectionMethod($property->class, 'get'.preg_replace('/[^\da-z]/i', '', mb_convert_case($property->name, MB_CASE_TITLE)));
            $objArray[$property->name] = $method->invoke($object);
        }
        return $objArray;
    }

    public static function createObjectFromArray($class, $array) {
        $arrayObject = array();
        foreach($array as $element) {

                $object = new $class();
                 self::setObjectPropertiesFromArray($object, $element);

            array_push($arrayObject, $object);
        }

        return $arrayObject;
    }



    public static function getQuery($class, $name)
    {
        $connection = self::getConnection();
        $MainQueries = array(
            'getByCtId' => "SELECT * FROM {$connection['p']}ct_main WHERE ct_id = :ct_id",
            'getMain' => "SELECT ct_id FROM {$connection['p']}ct_main "
                . "WHERE context_id = :context_id AND link_id = :link_id",
            'getExercises' => "SELECT * FROM {$connection['p']}ct_exercise "
                . "WHERE ct_id = :ct_id "
                . "order by exercise_num",
            'getMainsFromContext' => "SELECT * FROM {$connection['p']}ct_main "
                . "WHERE context_id = :context_id "
                . "order by modified desc",
            'getResultUser' => "SELECT * FROM {$connection['p']}lti_result "
                . "WHERE user_id = :user_id AND link_id = :link_id",
            'getAnswersByUser' => "SELECT DISTINCT {$connection['p']}a.* "
                . "FROM {$connection['p']}ct_answer a join {$connection['p']}ct_exercise q USING (exercise_id) "
                . "WHERE q.ct_id = :ctId AND a.user_id = :userId;",
            'insert' => "INSERT INTO {$connection['p']}ct_main (user_id, context_id, link_id, modified) "
                . "VALUES (:userId, :contextId, :linkId, :currentTime)",
            'update' => "UPDATE {$connection['p']}ct_main set "
                . "`user_id` = :user_id, "
                . "`context_id` = :context_id, "
                . "`link_id` = :link_id, "
                . "`title` = :title, "
                . "`seen_splash` = :seen_splash, "
                . "`preloaded` = :preloaded, "
                . "`shuffle` = :shuffle, "
                . "`points` = :points, "
                . "`modified` = :modified "
                . "WHERE ct_id = :ctId",
            'delete' => "DELETE FROM {$connection['p']}ct_main WHERE ct_id = :mainId AND user_id = :userId",
            'codeExercisesExport' => "SELECT * FROM {$connection['p']}ct_code_exercise WHERE `exercise_id` IN (:exercises_in )",
            'sqlExercisesExport' => "SELECT * FROM {$connection['p']}ct_sql_exercise WHERE `exercise_id` IN ( :exercises_in )",
        );
        $ExerciseQueries = array(
            'insert' => "INSERT INTO {$connection['p']}ct_exercise "
                . "( `exercise_id`, `ct_id`, `exercise_num` , `title`, `statement`, `hint`, `ak_id` ) "
                . "VALUES ( :exercise_id, :ct_id, :exercise_num, :title, :statement, :hint, :akId)",
            'update' => "UPDATE {$connection['p']}ct_exercise set "
                . "`ct_id` = :ct_id, "
                . "`exercise_num` = :exercise_num, "
                . "`exercise_id` = :exercise_id "
                . "WHERE exercise_id = :exercise_id AND ct_id = :ct_id",
            'updateNum' => "UPDATE {$connection['p']}ct_exercise set "
                . "`ct_id` = :ct_id, "
                . "`exercise_num` = :exercise_num, "
                . "`exercise_id` = :exercise_id, "
                . "WHERE exercise_id = :exercise_id AND ct_id = :ct_id",
            'delete' => "DELETE FROM {$connection['p']}ct_exercise WHERE `exercise_id` = :exercise_id AND `ct_id` = :ct_id;",
            'exists' => "SELECT exercise_id as exerciseId FROM {$connection['p']}ct_exercise WHERE "
            . "exercise_id = :exercise_id AND ct_id = :ct_id;",
            'getAnswers' => "SELECT * FROM {$connection['p']}ct_answer "
                . "WHERE exercise_id = :exerciseId AND ct_id = :ctId",
            'fixUpExerciseNumbers' => "SET @exercise_num = 0; UPDATE {$connection['p']}ct_exercise "
                . "set exercise_num = (@exercise_num:=@exercise_num+1) "
                . "WHERE ct_id = :ctId ORDER BY exercise_num",
            'getNextExerciseNumber' => "SELECT MAX(exercise_num) as lastNum "
                . "FROM {$connection['p']}ct_exercise "
                . "WHERE ct_id = :ct_id",
            'findExercisesForImport' => "SELECT q.*, m.title as tooltitle, c.title as sitetitle "
                . "FROM {$connection['p']}ct_exercise q "
                . "join {$connection['p']}ct_main m on q.ct_id = m.ct_id "
                . "join {$connection['p']}lti_context c on m.context_id = c.context_id "
                . "WHERE m.user_id = :userId AND m.ct_id != :ct_id",
            'getById' => "SELECT * FROM {$connection['p']}ct_exercise "
                . "WHERE exercise_id = :exercise_id AND ct_id = :ct_id",
        );
        $ExerciseCodeQueries = array(
            'insert' => "INSERT INTO {$connection['p']}ct_code_exercise  "
                . "(`exercise_id`,  `ct_id`, `exercise_language`, `exercise_input_test`, `exercise_input_grade`, `exercise_output_test`, `exercise_output_grade`, `exercise_solution` ) "
                . "VALUES (:exercise_id, :ct_id, :exercise_language, :exercise_input_test, :exercise_input_grade, :exercise_output_test, :exercise_output_grade, :exercise_solution )",
            'update' => "UPDATE {$connection['p']}ct_code_exercise set "
                . "`exercise_language` = :exercise_language, "
                . " `ct_id` = :ct_id,"
                . "`exercise_input_test` = :exercise_input_test, "
                . "`exercise_input_grade` = :exercise_input_grade, "
                . "`exercise_output_test` = :exercise_output_test, "
                . "`exercise_output_grade` = :exercise_output_grade, "
                . "`exercise_solution` = :exercise_solution "
                . "WHERE exercise_id = :exercise_id",
            'getById' => "SELECT * FROM {$connection['p']}ct_code_exercise "
                . "WHERE exercise_id = :exercise_id",
        );
        $ExerciseSQLQueries = array(
            'insert' => "INSERT INTO {$connection['p']}ct_sql_exercise  "
                . "(`exercise_id`,  `ct_id`, `exercise_dbms`, `exercise_sql_type`, `exercise_database`, `exercise_solution`, `exercise_probe`, `exercise_onfly` ) "
                . "VALUES (:exercise_id, :ct_id, :exercise_dbms, :exercise_sql_type, :exercise_database, :exercise_solution, :exercise_probe, :exercise_onfly )",
            'update' => "UPDATE {$connection['p']}ct_sql_exercise set "
                . "`exercise_dbms` = :exercise_dbms, "
                . "`ct_id` = :ct_id,"
                . "`exercise_sql_type` = :exercise_sql_type, "
                . "`exercise_database` = :exercise_database, "
                . "`exercise_solution` = :exercise_solution, "
                . "`exercise_probe` = :exercise_probe, "
                . "`exercise_onfly` = :exercise_onfly "
                . "WHERE exercise_id = :exercise_id",
            'getById' => "SELECT * FROM {$connection['p']}ct_sql_exercise "
                . "WHERE exercise_id = :exercise_id",
        );
        $AnswerQueries = array(
            'getByAnswerId' => "SELECT * FROM {$connection['p']}ct_answer WHERE answer_id = :answer_id",
            'getByUserExercise' => "SELECT * FROM {$connection['p']}ct_answer "
                . "WHERE user_id = :userId AND exercise_id = :exerciseId AND ct_id = :ctId",
            'insert' => "INSERT INTO {$connection['p']}ct_answer "
                . "(`user_id`, `exercise_id`, `ct_id`, `answer_txt`, `answer_success`, `modified`, `answer_output`, `answer_language`) "
                . "VALUES (:userId, :exerciseId, :ctId, :answerTxt, :answerSuccess, :modified, :answerOutput, :answerLanguage)",
            'update' => "UPDATE {$connection['p']}ct_answer set "
                . "`user_id` = :userId, "
                . "`exercise_id` = :exerciseId, "
                . "`ct_id` = :ctId,"
                . "`answer_txt` = :answerTxt, "
                . "`answer_success` = :answerSuccess, "
                . "`modified` = :modified, "
                . "`answer_output` = :answerOutput, "
                . "`answer_language` = :answerLanguage "
                . "WHERE answer_id = :answer_id",
            'deleteOne' => "DELETE FROM {$connection['p']}ct_answer WHERE answer_id = :answerId;",
            'deleteFromExercises' => "DELETE FROM {$connection['p']}ct_answer "
                . "WHERE user_id = :userId AND exercise_id in (/exercisesId/)",
        );
        $UserQueries = array(
            'getByUserId' => "SELECT user_id, deleted, profile_id, displayname, email "
                . "FROM {$connection['p']}lti_user WHERE user_id = :user_id",
            'getUserRoles' => "SELECT role FROM {$connection['p']}lti_membership "
                . "WHERE context_id = :context_id AND user_id = :user_id",
            'findInstructors' => "SELECT {$connection['p']}lti_user.* "
                . "FROM {$connection['p']}lti_membership JOIN {$connection['p']}lti_user USING (user_id)"
                . "WHERE context_id = :context_id AND role = :role",
            'getUsersWithAnswers' => "SELECT DISTINCT {$connection['p']}lti_user.* "
                . "FROM {$connection['p']}ct_answer a join {$connection['p']}ct_exercise q USING (exercise_id) "
                . "JOIN {$connection['p']}lti_user USING (user_id)"
                . "WHERE q.ct_id = :ctId and a.ct_id = :ctId",
            'getAnswerForExercise' => "SELECT * FROM {$connection['p']}ct_answer "
                . "WHERE exercise_id = :exerciseId AND user_id = :userId AND ct_id = :ctId",
            'getMostRecentAnswerDate' => "SELECT max(a.modified) as modified "
                . "FROM {$connection['p']}ct_answer a "
                . "join {$connection['p']}ct_exercise q on a.exercise_id = q.exercise_id "
                . "WHERE a.user_id = :userId AND q.ct_id = :ctId",
            'getNumberExercisesAnswered' => "SELECT count(distinct a.user_id, a.exercise_id) as num_answered "
                . "FROM {$connection['p']}ct_answer a "
                . "join {$connection['p']}ct_exercise q on a.exercise_id = q.exercise_id "
                . "WHERE a.user_id = :userId AND q.ct_id = :ctId AND a.answer_txt is not null",
            'getGrade' => "SELECT * FROM {$connection['p']}ct_grade "
                . "WHERE ct_id = :ct_id AND user_id = :user_id",
            'getLtiContexts' => "SELECT DISTINCT lti.title as courseName, lti.context_id as ctxId "
                . "FROM {$connection['p']}ct_main m "
                . "JOIN {$connection['p']}lti_context lti on m.context_id = lti.context_id "
                . "WHERE m.user_id in ("
                    . "SELECT user_id "
                    . "FROM {$connection['p']}lti_user "
                    . "WHERE user_key = ("
                        . "SELECT user_key FROM {$connection['p']}lti_user "
                        . "WHERE user_id = :userId))",
        );
        $GradeQueries = array(
            'getByGradeId' => "SELECT * FROM {$connection['p']}ct_grade "
                . "WHERE grade_id = :grade_id",
            'count' => "SELECT COUNT(*) as count FROM {$connection['p']}ct_grade WHERE ct_id = :ctid",
            'gradesCtid' => "SELECT ct_id, grade FROM {$connection['p']}ct_grade WHERE ct_id = :ctid",
            'insert' => "INSERT INTO {$connection['p']}ct_grade "
                . "(ct_id, user_id, grade, modified) "
                . "VALUES (:ctId, :userId, :grade, :modified)",
            'update' => "UPDATE {$connection['p']}ct_grade "
                . "set user_id = :userId, ct_id = :ctId, grade = :grade, modified = :modified "
                . "WHERE grade_id = :gradeId",
        );
        $queries = array(
            'main' => $MainQueries,
            'exercise' => $ExerciseQueries,
            'exerciseCode' => $ExerciseCodeQueries,
            'exerciseSQL' => $ExerciseSQLQueries,
            'answer' => $AnswerQueries,
            'user' => $UserQueries,
            'grade' => $GradeQueries,
        );
        return array(
            'PDOX' => $connection['PDOX'],
            'sentence' => $queries[$class][$name],
        );
    }

    public static function debug($string)
    {
        global $USER, $CFG;
        $displayedName = "";
        if($CFG->CT_log['debug']) {
            $fileLog = $CFG->CT_log['filePath'];
            if(is_object($USER) && isset($USER->id)){
                $user = new CT_User($USER->id);
                $displayedName = $user->getDisplayname();
            }
            error_log("******************" . $displayedName . "******************************************", 3, $fileLog);
            error_log(addslashes($string), 3, $fileLog);
            error_log("--------", 3, $fileLog);
        }

    }

}
