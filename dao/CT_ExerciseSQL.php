<?php


namespace CT;


class CT_ExerciseSQL extends CT_Exercise  implements \JsonSerializable
{
    private $exercise_dbms;
    private $exercise_sql_type;
    private $exercise_database;
    private $exercise_solution;
    private $exercise_probe;
    private $exercise_onfly;

    const DBMS_MYSQL = 0;
    const DBMS_ORACLE = 1;
    const DBMS_SQLITE = 2;

    public function __construct($exercise_id = null)
    {
        $context = array();
        if (isset($exercise_id)) {
            $query = \CT\CT_DAO::getQuery('exerciseSQL', 'getById');
            $arr = array(':exercise_id' => $exercise_id);
            $context = $query['PDOX']->rowDie($query['sentence'], $arr);
        }
        \CT\CT_DAO::setObjectPropertiesFromArray($this, $context);
        $this->setExerciseParentProperties();
    }

    static function withId($exercise_id = null)
    {
        $exercise =new CT_ExerciseSQL();
        $context = array();
        if (isset($exercise_id)) {
            $query = \CT\CT_DAO::getQuery('exerciseSQL', 'getById');
            $arr = array(':exercise_id' => $exercise_id);
            $context = $query['PDOX']->rowDie($query['sentence'], $arr);
        }
        \CT\CT_DAO::setObjectPropertiesFromArray($exercise, $context);
        $exercise->setExerciseParentProperties();
          return $exercise;

    }

    //necessary to use json_encode with exerciseSQL objects
      public function jsonSerialize() {
        return [
            'exercise_id' => $this->getExerciseId(),
            'ct_id' => $this->getCtId(),
            'exercise_num' => $this->getExerciseNum(),
            'title' => $this->getTitle(),
            'type' => $this->getType(),
            'difficulty' => $this->getDifficulty(),
            'averageGradeUnderstability' => $this->getAverageGradeUnderstability(),
            'averageGradeDifficulty' => $this->getAverageGradeDifficulty(),
            'averageGradeTime' => $this->getAverageGradeTime(),
            'averageGrade' => $this->getAverageGrade(),
            'numberVotes' => $this->getNumberVotes(),
            'keywords' => $this->getKeywords(),
            'exercise_dbms' => $this->getExerciseDbms(),
            'exercise_sql_type' => $this->getExerciseSQLType(),
            'exercise_database' => $this->getExerciseDatabase(),
            'exercise_solution' => $this->getExerciseSolution(),
            'exercise_probe' => $this->getExerciseProbe(),
            'exercise_onfly' => $this->getExerciseOnfly(),
            'library' => $this->getLibrary(),
        ];
    }

    public function getConnection($dbUser = null, $dbPassword = null, $dbName = null) {
        $dbms = $this->getExerciseDbms();
        $connectionConfig = $this->getMain()->getTypeProperty('dbConnections', 'MYSQL')[$dbms];
        $dbUser = $dbUser ? $dbUser : $connectionConfig['dbUser'];
        $dbPassword = $dbPassword ? $dbPassword : $connectionConfig['dbPassword'];
        $dbName = $dbName ? $dbName : $this->getExerciseDatabase();

        $dbUser = $dbUser ? $dbUser : $connectionConfig['dbUser'];
        $dbPassword = $dbPassword ? $dbPassword : $connectionConfig['dbPassword'];
        $dbName = $dbName ? $dbName : $this->getExerciseDatabase();

        switch ($dbms)
        {
            case self::DBMS_MYSQL: //dsn mysql 'mysql:host=127.0.0.1;dbname=testdb'
                $dsn = "{$connectionConfig['dbDriver']}:host={$connectionConfig['dbHostName']};dbname={$dbName}";
                break;
            case self::DBMS_ORACLE: //dsn oracle 'oci:dbname=//localhost:1521/mydb'
                $dsn = "{$connectionConfig['dbDriver']}:dbname=//{$connectionConfig['dbHostName']}:{$connectionConfig['dbPort']}/{$connectionConfig['dbSID']}";
                break;
            case self::DBMS_SQLITE: //dsn sqlite currently only in memory.
                $dsn = "{$connectionConfig['dbDriver']}:{$connectionConfig['dbFile']}";
        }
        try {

            $connection =
                new \PDO(
                    $dsn,
                    $dbUser,
                    $dbPassword
                );
        }
        catch(\PDOException $e)
        {
            CT_DAO::debug($e->getMessage());
        }
        return $connection;
    }

    public function grade($answer) {
        $outputSolution = $this->getQueryResult();
        $outputAnswer =  $this->getQueryResult($answer->getAnswerTxt());
        //CT_DAO::debug(CT_Answer::getDiffWithSolution(print_r($outputSolution, true), print_r($outputAnswer, true)));

        $grade = $outputSolution === $outputAnswer ? 1 : 0;
        $answer->setAnswerSuccess($grade);
    }

    public function getQueryResult($answer = null) {
        $connection = $this->initTransaction();
        $queries = (isset($answer) ? $answer : $this->getExerciseSolution());
		foreach(explode(";", $queries) as $query) { // ; not accepted in Oracle driver.
			if($this->isQuery($query) && $resultQuery = $connection->prepare($query)) {
				$resultQuery->execute();
			}
		}

		if ($this->getExerciseType() == 'DML' || $this->getExerciseType() == 'DDL') {
			$query = explode(";", $this->getExerciseProbe())[0];
			if($resultQuery = $connection->prepare($query)) {
				$resultQuery->execute();
			}
		}
        $resultQueryArray = $resultQuery ? $resultQuery->fetchAll() : array();
        $resultQuery = null;
        $this->endTransaction($connection);
        return $resultQueryArray;
    }

    private function isQuery($query) {
		return strlen(trim($query)) > 1;
	}

    private function createOnflySchema(&$connection) {
        global $USER;
        $dbms = $this->getExerciseDbms();
        $connectionConfig = $this->getMain()->getTypeProperty('dbConnections', "MYSQL")[$dbms];
        if( array_key_exists('onFly', $connectionConfig)
            && is_array($onFly = $connectionConfig['onFly'])
            && array_key_exists('allowed', $onFly)
            && $onFly['allowed']
            && strlen(trim($this->getExerciseOnfly())) > 0)
        {
            $nameAndPassword = $onFly['userPrefix'] . $this->getNameAndPasswordSuffix();
            switch ($dbms) {
                case self::DBMS_ORACLE:
                    if (
                        array_key_exists('createIsolateUserProcedure', $onFly)
                        && strlen(trim($onFly['createIsolateUserProcedure'])) > 0
                    ) {
                        $createUserSentence =
                            "BEGIN "
                            . $onFly['createIsolateUserProcedure'] . "('"
                            . $nameAndPassword . "', '"
                            . $nameAndPassword
                            . "');"
                            . "END;";
                        if ($resultQuery = $connection->prepare($createUserSentence)) {
                            $resultQuery->execute();
                        }
                        $connection = $this->getConnection($nameAndPassword, $nameAndPassword);
                    }
                    $splitSQL = preg_split('~\([^)]*\)(*SKIP)(*F)|;~', $this->sanitize($this->getExerciseOnfly()));
                    $queryString = "";
                    foreach ($splitSQL as $sqlSentence) {
                        if (strlen(trim($sqlSentence)) > 0) {
                            $queryString .=
                                "     stmtOnFly := '" . $sqlSentence . "';\n"
                                . "     EXECUTE IMMEDIATE stmtOnFly;\n";
                        }
                    }
                    $onFlyQuery =
                        "DECLARE stmtOnFly LONG;\n"
                        . " BEGIN\n"
                        . $queryString
                        . " END;\n";
                    if ($resultQuery = $connection->prepare($onFlyQuery)) {
                        $resultQuery->execute();
                    }
                    break;
                case self::DBMS_MYSQL:
                    if (
                        array_key_exists('createIsolateUserProcedure', $onFly)
                        && strlen(trim($onFly['createIsolateUserProcedure'])) > 0
                    ) {
                        $createUserSentence =
                            "CALL " . $onFly['createIsolateUserProcedure'] . "('"
                            . $nameAndPassword . "', '"
                            . $nameAndPassword
                            . "')";
                        if ($resultQuery = $connection->prepare($createUserSentence)) {
                            $resultQuery->execute();
                        }
                        $databaseName = $nameAndPassword;
                        $connection = $this->getConnection($nameAndPassword, $nameAndPassword, $databaseName);
                    }

                    $connection->exec($this->getExerciseOnfly());
                    break;
                case self::DBMS_SQLITE:
                    $connection->exec($this->getExerciseOnfly());
            }
        }
    }

    private function dropOnflySchema(&$connection) {
        $dbms = $this->getExerciseDbms();
        $connectionConfig = $this->getMain()->getTypeProperty('dbConnections', 'MYSQL')[$dbms];
        if( array_key_exists('onFly', $connectionConfig)
            && is_array($onFly = $connectionConfig['onFly'])
            && array_key_exists('allowed', $onFly)
            && $onFly['allowed']
            && strlen(trim($this->getExerciseOnfly())) > 0
            && array_key_exists('dropIsolateUserProcedure', $onFly)
            && strlen(trim($onFly['dropIsolateUserProcedure'])) > 0
        )
        {
            $nameAndPassword = $onFly['userPrefix'] . $this->getNameAndPasswordSuffix();
            switch ($dbms) {
                case self::DBMS_ORACLE:
                    $dropUserSentence =
                        "BEGIN "
                        . $onFly['dropIsolateUserProcedure'] . " ('"
                        . $nameAndPassword
                        . "');"
                        . "END;";
                    break;
                case self::DBMS_MYSQL:
                    $dropUserSentence =
                        "CALL " . $onFly['dropIsolateUserProcedure'] . "('"
                        . $nameAndPassword
                        . "')";
            }
            if($resultQuery = $connection->prepare($dropUserSentence)) {
                $resultQuery->execute();
            }
        }
    }

    private function getNameAndPasswordSuffix() {
        return $_SESSION['lti']['user_key'];
    }

    private function initTransaction() {
        $connection = $this->getConnection();
        $this->createOnflySchema($connection);
        $connection->beginTransaction();
/*        if ($this->getExerciseType() == 'DDL') {
            $this->loadDDL($connection);
        }*/ // Previously, we did it in SQLite
        return $connection;
    }

    private function endTransaction(&$connection) {
        $connection->rollback();
        // Close statement & connection to drop user
        $connection = null;
        $connection = $this->getConnection();
        $this->dropOnflySchema($connection);
/*        if ($this->getExerciseType() == 'DDL') {
            $dbms = $this->getExerciseDbms();
            $connectionConfig = $this->getMain()->getTypeProperty('dbConnections')[$dbms];
            if(file_exists($connectionConfig['dbFile'])) unlink($connectionConfig['dbFile']);
        }*/ //, Previously we did it in SQLite
    }

    private function loadDDL($connection) {
        $sentences = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'ddl_databases'. DIRECTORY_SEPARATOR . 'world_ddl.sql');
        $connection->exec($sentences);
    }

    private function sanitize($stmt) {
        $sanitizedStmt = str_replace("'", "''", trim($stmt));
        return $sanitizedStmt;
    }

    public function getQueryTable(): string
    {
        $resultQueryString = '';
        if ($this->getExerciseSQLType() == 'SELECT') {
            $connection = $this->initTransaction();
            $resultQueryString = "<div class='table-results'><table>";
            $query = $this->getExerciseSolution();
            if($resultQuery = $connection->prepare($query)) {
                $resultQuery->execute();
                $resultQueryString .= $this->getQueryTableContent($resultQuery);
                $resultQueryString .= "</table></div>";
            }
            $resultQuery = null;
            $this->endTransaction($connection);
        }
        return $resultQueryString;
    }

    private function getQueryTableContent($resultQuery) {
        $resultQueryString = '';
        if (is_array($firstRow = $resultQuery->fetch(\PDO::FETCH_ASSOC))) {
            $resultQueryString .= $this->getHeaderQueryTable($firstRow);
            $resultQueryString .= $this->getBodyQueryTable($firstRow, $resultQuery);
        }
        return $resultQueryString;
    }

    private function getHeaderQueryTable($firstRow) {
        $columnNames = array_keys($firstRow);
        return $this->getQueryTableRow($columnNames, true);
    }

    private function getBodyQueryTable($firstRow, $resultQuery) {
        $tableBody = $this->getQueryTableRow(array_values($firstRow), false);
        while ($row = $resultQuery->fetch(\PDO::FETCH_NUM)) {
            $tableBody .= $this->getQueryTableRow($row, false);
        }
        return $tableBody;
    }

    private function getQueryTableRow($row, $header = false) {
        $tableRow = "<tr>";
        foreach ($row as $value) {
            $tableRow .= ( $header ? "<th>" : "<td>") . $value . ( $header ? "</th>" : "</td>");
        }
        $tableRow .= "</tr>";
        return $tableRow;
    }

    /**
     * @return mixed
     */
    public function getExerciseDbms()
    {
        return $this->exercise_dbms;
    }

    /**
     * @param mixed $exercise_dbms
     */
    public function setExerciseDbms($exercise_dbms)
    {
        $this->exercise_dbms = $exercise_dbms;
    }

    /**
     * @return mixed
     */
    public function getExerciseSQLType()
    {
        return $this->exercise_sql_type;
    }

    /**
     * @param mixed $exercise_type
     */
    public function setExerciseSQLType($exercise_sql_type)
    {
        $this->exercise_sql_type = $exercise_sql_type;
    }

    /**
     * @return mixed
     */
    public function getExerciseDatabase()
    {
        return $this->exercise_database;
    }

    /**
     * @param mixed $exercise_database
     */
    public function setExerciseDatabase($exercise_database)
    {
        $this->exercise_database = $exercise_database;
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
        $this->exercise_solution = $exercise_solution;
    }

    /**
     * @return mixed
     */
    public function getExerciseProbe()
    {
        return $this->exercise_probe;
    }

    /**
     * @param mixed $exercise_probe
     */
    public function setExerciseProbe($exercise_probe)
    {
        $this->exercise_probe = $exercise_probe;
    }

    /**
     * @return String
     */
    public function getExerciseOnfly()
    {
        return $this->exercise_onfly;
    }

    /**
     * @param String $exercise_onfly
     */
    public function setExerciseOnfly($exercise_onfly)
    {
        $this->exercise_onfly = $exercise_onfly;
    }

    public function save() {
        $isNew = $this->isNew();
        parent::save();
        $query = \CT\CT_DAO::getQuery('exerciseSQL', $isNew ? 'insert' : 'update');
        $arr = array(
            ':exercise_id' => $this->getExerciseId(),
            ':ct_id' => $this->getCtId(),
            ':exercise_dbms' => $this->getExerciseDbms(),
            ':exercise_sql_type' => $this->getExerciseSQLType(),
            ':exercise_database' => $this->getExerciseDatabase(),
            ':exercise_solution' => $this->getExerciseSolution(),
            ':exercise_probe' => $this->getExerciseProbe(),
            ':exercise_onfly' => $this->getExerciseOnfly(),

        );
        $query['PDOX']->queryDie($query['sentence'], $arr);

    }

}
