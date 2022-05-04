<?php

namespace CT;

class CT_ExerciseCode extends CT_Exercise
{
    private $exercise_language;
    private $exercise_input_test;
    private $exercise_input_grade;
    private $exercise_output_test;
    private $exercise_output_grade;
    private $exercise_solution;
    private $recalculateOutputs = false;

    public function __construct($exercise_id = null)
    {
        $context = array();
        if (isset($exercise_id)) {
            $query = \CT\CT_DAO::getQuery('exerciseCode', 'getById');
            $arr = array(':exercise_id' => $exercise_id);
            $context = $query['PDOX']->rowDie($query['sentence'], $arr);
        }
        \CT\CT_DAO::setObjectPropertiesFromArray($this, $context);
        $this->setExerciseParentProperties();
    }


   // necessary to use json_encode with exerciseCode objects
    public function jsonSerialize() {
        return [
            'exercise_id' => $this->getExerciseId(),
            'ct_id' => $this->getCtId(),
            'exercise_num' => $this->getExerciseNum(),
            'title' => $this->getTitle(),
            'statement' => $this->getStatement(),
            'hint' => $this->getHint(),
            'difficulty' => $this->getDifficulty(),
            'averageGradeUnderstability' => $this->getAverageGradeUnderstability(),
            'averageGradeDifficulty' => $this->getAverageGradeDifficulty(),
            'averageGradeTime' => $this->getAverageGradeTime(),
            'averageGrade' => $this->getAverageGrade(),
            'numberVotes' => $this->getNumberVotes(),
            'keywords' => $this->getKeywords(),
            'exercise_language' => $this->getExerciseLanguage(),
            'exercise_input_test' => $this->getExerciseInputTest(),
            'exercise_input_grade' => $this->getExerciseInputGrade(),
            'exercise_output_test' => $this->getExerciseOutputTest(),
            'exercise_output_grade' => $this->getExerciseOutputGrade(),
            'exercise_solution' => $this->getExerciseSolution()

        ] + parent::jsonSerialize();
    }

    /**
     * @return mixed
     */
    public function getExerciseLanguage()
    {
        return $this->exercise_language;
    }

    /**
     * @param mixed $exercise_language
     */
    public function setExerciseLanguage($exercise_language)
    {
        $this->exercise_language = $exercise_language;
    }

    /**
     * @return mixed
     */
    public function getExerciseInputTest()
    {
        return $this->exercise_input_test;
    }

    /**
     * @param mixed $exercise_input_test
     */
    public function setExerciseInputTest($exercise_input_test)
    {
        if($this->exercise_input_test != $exercise_input_test) $this->recalculateOutputs = true;
        $this->exercise_input_test = $exercise_input_test;
    }

    /**
     * @return mixed
     */
    public function getExerciseInputGrade()
    {
        return $this->exercise_input_grade;
    }

    /**
     * @param mixed $exercise_input_grade
     */
    public function setExerciseInputGrade($exercise_input_grade)
    {
        if($this->exercise_input_grade != $exercise_input_grade) $this->recalculateOutputs = true;
        $this->exercise_input_grade = $exercise_input_grade;
    }

    /**
     * @return mixed
     */
    public function getExerciseOutputTest()
    {
        return $this->exercise_output_test;
    }

    /**
     * @param mixed $exercise_output_test
     */
    public function setExerciseOutputTest($exercise_output_test)
    {
        $this->exercise_output_test = $exercise_output_test;
    }

    /**
     * @return mixed
     */
    public function getExerciseOutputGrade()
    {
        return $this->exercise_output_grade;
    }

    /**
     * @param mixed $exercise_output_grade
     */
    public function setExerciseOutputGrade($exercise_output_grade)
    {
        $this->exercise_output_grade = $exercise_output_grade;
    }

    /**
     * @return mixed
     */
    public function getExerciseSolution()
    {
        return $this->exercise_solution;
    }

    /**
     * @param mixed $exercise_solution
     */
    public function setExerciseSolution($exercise_solution)
    {
        if($this->exercise_solution != $exercise_solution) $this->recalculateOutputs = true;
        $this->exercise_solution = $exercise_solution;
    }

    public function setOutputs()
    {
        $this->setExerciseOutputTest($this->getOutputFromCode(
            $this->getExerciseSolution(),
            $this->getExerciseLanguage(),
            $this->getExerciseInputTest()
        ));

        $this->setExerciseOutputGrade($this->getOutputFromCode(
            $this->getExerciseSolution(),
            $this->getExerciseLanguage(),
            $this->getExerciseInputGrade()
        ));
    }

    /**
     * @param CT_Answer $answer
     */
    function grade($answer) {
        global $translator;
        $outputSolution = $this->getExerciseOutputGrade();
//        var_dump($outputSolution);
//        var_dump("SA");
        $outputAnswer =  $this->getOutputFromCode(
            $answer->getAnswerTxt(), $answer->getAnswerLanguage(), $this->getExerciseInputGrade()
        );
        //CT_DAO::debug(CT_Answer::getDiffWithSolution($outputAnswer, $outputSolution));

        $grade = ($outputSolution == $outputAnswer);
        // TODO mejorar el usage
        if(!$grade) {
			$outputAnswer =  $this->getOutputFromCode(
				$answer->getAnswerTxt(), $answer->getAnswerLanguage(), $this->getExerciseInputTest()
			);
			/* $diff = CT_Answer::getDiffWithSolution($outputAnswer, $this->getExerciseOutputTest());
            $_SESSION['error'] = "Below, it shows the differences between output expected and output obtained\n<pre>" . htmlentities($diff) . "</pre>"; */
        }
        $answer->setAnswerSuccess($grade);
    }

    function getOutputFromCode($answerCode, $language, $input) {
        $tmpfile = tmpfile();
        fwrite($tmpfile, $answerCode);
        try {
            $output = $this->launchCode($tmpfile, $language, $input);
        } catch (\Exception $e) {
            // TODO return exception
            $output = 'Timeout';
        }
        return($output);
    }

    function launchCode($file, $language, $input) {
        global $CFG;
        $main = $this->getMain();
        $languages = $main->getProperty('codeLanguages');
        $timeout = $main->getProperty('timeout') + time();
        $languageName = $languages[$language]['name'];
        $fileExtension = $languages[$language]['ext'];

        $pathFile = stream_get_meta_data($file)['uri'];
        rename($pathFile, "$pathFile.$fileExtension");

        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
            2 => array("pipe", "w") // stderr is a file to write to
        );

        $cwd = sys_get_temp_dir(); // '/tmp';
        $env = array();

        $output = $error = "";

        // Descomentando las siguientes líneas se pueden permitir diferentes casos de prueba
        // separándolos por un EOL
        // $inputs = explode(PHP_EOL, trim($input));

        //foreach ($inputs as $inputLine) {

        $command = $languages[$language]['command'] . " $pathFile.$fileExtension";
        // $input after command like parameters
        $stdin = array_key_exists('stdin', $languages[$language])
            &&
            $languages[$language]['stdin'];
        if(!$stdin) $command .= " " . $input;

        // Run shell command
        $process = proc_open($command, $descriptorspec, $pipes, $cwd, $env);

        if (is_resource($process)) {
            // $pipes now looks like this:
            // 0 => writeable handle connected to child stdin
            // 1 => readable handle connected to child stdout
            // Any error output will be appended to /tmp/error-output.txt

            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            // $input through stdin.
            fwrite($pipes[0], $input); //fwrite($pipes[0], $inputLine); //para varios casos de prueba
            fclose($pipes[0]);
            do {
                $write = null;
                $exceptions = null;
                $timeleft = $timeout - time();

                if ($timeleft <= 0) {
                    self::terminate_process_with_children($process, $pipes, true);
                    throw new \Exception("command timeout", 1012);
                }

                $read = array($pipes[1],$pipes[2]);
                stream_select($read, $write, $exceptions, $timeleft);

                if (!empty($read)) {
                    $output .= fread($pipes[1], 20);
                    $error .= fread($pipes[2], 20);
                }

                $output_exists = (!feof($pipes[1]) || !feof($pipes[2]));
            } while ($output_exists && $timeleft > 0);

            if ($timeleft <= 0) {
                self::terminate_process_with_children($process, $pipes, true);
                throw new \Exception("command timeout", 1013);
            }

            // $output .= trim(stream_get_contents($pipes[1])) . "\n";
            $output = trim($output) . "\n";
            self::terminate_process_with_children($process, $pipes);
        }
        //} // cierra el foreach que permite varios casos de prueba
        // remove code file
        unlink("$pathFile.$fileExtension");
        return $output;
    }

    private static function terminate_process_with_children(&$process, &$pipes, $timeout = false) {
        $status = proc_get_status($process);
        if($status['running'] == true) { //process ran too long, kill it
            //close all pipes that are still open
            fclose($pipes[1]); //stdout
            fclose($pipes[2]); //stderr
            //get the parent pid of the process we want to kill
            $ppid = $status['pid'];
            //use ps to get all the children of this process, and kill them
            $pids = preg_split('/\s+/', `ps -o pid --no-heading --ppid $ppid`);
            foreach($pids as $pid) {
                if(is_numeric($pid)) {
                    CT_DAO::debug("Killing $pid\n");
                    posix_kill($pid, 9); //9 is the SIGKILL signal
                }
            }
            if($timeout) posix_kill(intval($ppid), 9);
            proc_close($process);
        }
    }

    public function save() {
        $isNew = $this->isNew();
        parent::save();

        /*
            This was changing the value for the field "getExerciseOutputTest",
            right now it's used to save the expected output for the student
        */
        //if ($this->recalculateOutputs) $this->setOutputs();
        $query = \CT\CT_DAO::getQuery('exerciseCode', $isNew ? 'insert' : 'update');
        $arr = array(
            ':exercise_id' => $this->getExerciseId(),
            ':ct_id' => $this->getCtId(),
            ':exercise_language' => $this->getExerciseLanguage(),
            ':exercise_input_test' => $this->getExerciseInputTest(),
            ':exercise_input_grade' => $this->getExerciseInputGrade(),
            ':exercise_output_test' => $this->getExerciseOutputTest(),
            ':exercise_output_grade' => $this->getExerciseOutputGrade(),
            ':exercise_solution' => $this->getExerciseSolution(),
        );
        $query['PDOX']->queryDie($query['sentence'], $arr);
    }
}
