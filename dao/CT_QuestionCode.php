<?php

namespace CT;

class CT_QuestionCode extends CT_Question
{
    private $question_language;
    private $question_input_test;
    private $question_input_grade;
    private $question_output_test;
    private $question_output_grade;
    private $question_solution;
    private $recalculateOutputs = false;

    public function __construct($question_id = null)
    {
        $context = array();
        if (isset($question_id)) {
            $query = \CT\CT_DAO::getQuery('questionCode', 'getById');
            $arr = array(':question_id' => $question_id);
            $context = $query['PDOX']->rowDie($query['sentence'], $arr);
        }
        \CT\CT_DAO::setObjectPropertiesFromArray($this, $context);
        $this->setQuestionParentProperties();
    }

    /**
     * @return mixed
     */
    public function getQuestionLanguage()
    {
        return $this->question_language;
    }

    /**
     * @param mixed $question_language
     */
    public function setQuestionLanguage($question_language)
    {
        $this->question_language = $question_language;
    }

    /**
     * @return mixed
     */
    public function getQuestionInputTest()
    {
        return $this->question_input_test;
    }

    /**
     * @param mixed $question_input_test
     */
    public function setQuestionInputTest($question_input_test)
    {
        if($this->question_input_test != $question_input_test) $this->recalculateOutputs = true;
        $this->question_input_test = $question_input_test;
    }

    /**
     * @return mixed
     */
    public function getQuestionInputGrade()
    {
        return $this->question_input_grade;
    }

    /**
     * @param mixed $question_input_grade
     */
    public function setQuestionInputGrade($question_input_grade)
    {
        if($this->question_input_grade != $question_input_grade) $this->recalculateOutputs = true;
        $this->question_input_grade = $question_input_grade;
    }

    /**
     * @return mixed
     */
    public function getQuestionOutputTest()
    {
        return $this->question_output_test;
    }

    /**
     * @param mixed $question_output_test
     */
    public function setQuestionOutputTest($question_output_test)
    {
        $this->question_output_test = $question_output_test;
    }

    /**
     * @return mixed
     */
    public function getQuestionOutputGrade()
    {
        return $this->question_output_grade;
    }

    /**
     * @param mixed $question_output_grade
     */
    public function setQuestionOutputGrade($question_output_grade)
    {
        $this->question_output_grade = $question_output_grade;
    }

    /**
     * @return mixed
     */
    public function getQuestionSolution()
    {
        return $this->question_solution;
    }

    /**
     * @param mixed $question_solution
     */
    public function setQuestionSolution($question_solution)
    {
        if($this->question_solution != $question_solution) $this->recalculateOutputs = true;
        $this->question_solution = $question_solution;
    }

    public function setOutputs()
    {
        $this->setQuestionOutputTest($this->getOutputFromCode(
            $this->getQuestionSolution(),
            $this->getQuestionLanguage(),
            $this->getQuestionInputTest()
        ));

        $this->setQuestionOutputGrade($this->getOutputFromCode(
            $this->getQuestionSolution(),
            $this->getQuestionLanguage(),
            $this->getQuestionInputGrade()
        ));
    }

    /**
     * @param CT_Answer $answer
     */
    function grade($answer) {
        $outputSolution = $this->getQuestionOutputGrade();
        $outputAnswer =  $this->getOutputFromCode(
            $answer->getAnswerTxt(), $answer->getAnswerLanguage(), $this->getQuestionInputGrade()
        );
        CT_DAO::debug(CT_Answer::getDiffWithSolution($outputAnswer, $outputSolution));

        $grade = ($outputSolution == $outputAnswer);
        // TODO mejorar el feedback
        if(!$grade) {
            similar_text($outputSolution, $outputAnswer, $percentageCorrect);
            $_SESSION['error'] = "La salida de tu código coincide en un " . round($percentageCorrect) . "% de la correcta";
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
        $languages = $main->getTypeProperty('codeLanguages');
        $timeout = $main->getTypeProperty('timeout') + time();
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
        if ($this->recalculateOutputs) $this->setOutputs();
        $query = \CT\CT_DAO::getQuery('questionCode', $isNew ? 'insert' : 'update');
        $arr = array(
            ':question_id' => $this->getQuestionId(),
            ':question_language' => $this->getQuestionLanguage(),
            ':question_input_test' => $this->getQuestionInputTest(),
            ':question_input_grade' => $this->getQuestionInputGrade(),
            ':question_output_test' => $this->getQuestionOutputTest(),
            ':question_output_grade' => $this->getQuestionOutputGrade(),
            ':question_solution' => $this->getQuestionSolution(),
        );
        $query['PDOX']->queryDie($query['sentence'], $arr);
    }
}
