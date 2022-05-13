<?php

// The SQL to uninstall this tool
$DATABASE_UNINSTALL = array(
    array( "{$CFG->dbprefix}ct_grade",
        "drop table if exists {$CFG->dbprefix}ct_grade"),
    array( "{$CFG->dbprefix}ct_sql_exercise",
        "drop table if exists {$CFG->dbprefix}ct_sql_exercise"),
    array( "{$CFG->dbprefix}ct_code_exercise",
        "drop table if exists {$CFG->dbprefix}ct_code_exercise"),
    array( "{$CFG->dbprefix}ct_answer",
        "drop table if exists {$CFG->dbprefix}ct_answer"),
    array( "{$CFG->dbprefix}ct_exercise",
        "drop table if exists {$CFG->dbprefix}ct_exercise"),
    array( "{$CFG->dbprefix}ct_main",
        "drop table if exists {$CFG->dbprefix}ct_main"),
);

// The SQL to create the tables if they don't exist
$DATABASE_INSTALL = array(
    array( "{$CFG->dbprefix}ct_main",
        "create table {$CFG->dbprefix}ct_main (
    ct_id       INTEGER NOT NULL AUTO_INCREMENT,
    user_id     INTEGER NOT NULL,
    context_id  INTEGER NOT NULL,
	link_id     INTEGER NOT NULL,
	title       VARCHAR(255) NULL,
	seen_splash BOOL NOT NULL DEFAULT 0,
	preloaded BOOL NOT NULL DEFAULT 0,
	shuffle BOOL NOT NULL DEFAULT 0,
	points      FLOAT NOT NULL DEFAULT 100,
    modified    datetime NULL,

    PRIMARY KEY(ct_id)

) ENGINE = InnoDB DEFAULT CHARSET=utf8"),

    array("{$CFG->dbprefix}ct_exercise",
        "create table {$CFG->dbprefix}ct_exercise (
    exercise_id   VARCHAR(50) NOT NULL,
    ct_id         INTEGER NOT NULL,
    ak_id         VARCHAR(50) NULL,
    exercise_num  INTEGER NULL,
    title         VARCHAR (50) NOT NULL,
    statement     TEXT NULL,
    hint          VARCHAR (50) NULL,
    library       varchar (250) NULL,

    CONSTRAINT `{$CFG->dbprefix}ct_ibfk_6`
        FOREIGN KEY (`ct_id`)
        REFERENCES `{$CFG->dbprefix}ct_main` (`ct_id`)
        ON DELETE CASCADE,
    PRIMARY KEY(exercise_id, ct_id)

) ENGINE = InnoDB DEFAULT CHARSET=utf8"),

    array( "{$CFG->dbprefix}ct_answer",
        "create table {$CFG->dbprefix}ct_answer (
    answer_id    INTEGER NOT NULL AUTO_INCREMENT,
    user_id      INTEGER NOT NULL,
    exercise_id  VARCHAR(50) NOT NULL,
    answer_output  VARCHAR(250) NULL,
    ct_id        INTEGER NOT NULL,
    answer_txt   TEXT NULL,
    answer_success BOOL NOT NULL DEFAULT 0,
    modified     datetime NULL,

    CONSTRAINT `{$CFG->dbprefix}ct_ibfk_7`
        FOREIGN KEY (`exercise_id` )
        REFERENCES `{$CFG->dbprefix}ct_exercise` (`exercise_id`)
        ON DELETE CASCADE,

    CONSTRAINT `{$CFG->dbprefix}ct_ibfk_8`
        FOREIGN KEY (`ct_id`)
        REFERENCES `{$CFG->dbprefix}ct_main` (`ct_id`)
        ON DELETE CASCADE,

    UNIQUE (user_id, exercise_id, ct_id),
    PRIMARY KEY(answer_id)

) ENGINE = InnoDB DEFAULT CHARSET=utf8"),

    array( "{$CFG->dbprefix}ct_code_exercise",
        "create table {$CFG->dbprefix}ct_code_exercise (
    exercise_id VARCHAR(50) NOT NULL,
    ct_id INT(11) NOT NULL,
    exercise_language VARCHAR(20) NOT NULL,
    exercise_input_test TEXT NULL DEFAULT NULL,
    exercise_input_grade TEXT NULL DEFAULT NULL,
    exercise_output_test TEXT NULL DEFAULT NULL,
    exercise_output_grade TEXT NULL DEFAULT NULL,
    exercise_solution TEXT NULL DEFAULT NULL,

  PRIMARY KEY (exercise_id, ct_id),
  CONSTRAINT `{$CFG->dbprefix}ct_ibfk_3`
    FOREIGN KEY (`exercise_id`, `ct_id`)
    REFERENCES `{$CFG->dbprefix}ct_exercise` (`exercise_id`, `ct_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB DEFAULT CHARACTER SET = utf8"),

    array( "{$CFG->dbprefix}ct_sql_exercise",
        "create table {$CFG->dbprefix}ct_sql_exercise (
    exercise_id VARCHAR(50) NOT NULL,
    ct_id INT(11) NOT NULL,
    exercise_dbms TINYINT NOT NULL DEFAULT 0,
    exercise_sql_type VARCHAR(20) NULL DEFAULT 'SELECT',
    exercise_database VARCHAR(100) NULL DEFAULT NULL,
    exercise_solution TEXT NULL DEFAULT NULL,
    exercise_probe TEXT NULL DEFAULT NULL,
    exercise_onfly LONGTEXT NULL DEFAULT NULL,

  PRIMARY KEY (exercise_id, ct_id),
  CONSTRAINT `{$CFG->dbprefix}ct_ibfk_4`
    FOREIGN KEY (`exercise_id`, `ct_id`)
    REFERENCES `{$CFG->dbprefix}ct_exercise` (`exercise_id`, `ct_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB DEFAULT CHARACTER SET = utf8"),

    array( "{$CFG->dbprefix}ct_grade",
        "create table {$CFG->dbprefix}ct_grade (
    grade_id        INTEGER NOT NULL AUTO_INCREMENT,
    ct_id           INTEGER NOT NULL,
    user_id         INTEGER NOT NULL,
    grade           FLOAT NOT NULL DEFAULT 0,
	modified        datetime NULL,

    CONSTRAINT `{$CFG->dbprefix}ct_ibfk_5`
        FOREIGN KEY (`ct_id`)
        REFERENCES `{$CFG->dbprefix}ct_main` (`ct_id`)
        ON DELETE CASCADE,

    PRIMARY KEY(grade_id)

) ENGINE = InnoDB DEFAULT CHARSET=utf8"),
);

$DATABASE_UPGRADE = function($oldversion) {
    global $CFG, $PDOX;

    // Add splash column
    if (!$PDOX->columnExists('seen_splash', "{$CFG->dbprefix}ct_main")) {
        $sql = "ALTER TABLE {$CFG->dbprefix}ct_main ADD seen_splash BOOL NOT NULL DEFAULT 0";
        echo("Upgrading: " . $sql . "<br/>\n");
        error_log("Upgrading: " . $sql);
        $q = $PDOX->queryDie($sql);
    }

    // Remove splash table
    if($PDOX->describe("{$CFG->dbprefix}ct_splash")) {
        $sql = "DROP TABLE {$CFG->dbprefix}ct_splash;";
        echo("Upgrading: " . $sql . "<br/>\n");
        error_log("Upgrading: " . $sql);
        $q = $PDOX->queryDie($sql);
    }

    // Add points column
    if (!$PDOX->columnExists('points', "{$CFG->dbprefix}ct_main")) {
        $sql = "ALTER TABLE {$CFG->dbprefix}ct_main ADD points FLOAT NOT NULL DEFAULT 100";
        echo("Upgrading: " . $sql . "<br/>\n");
        error_log("Upgrading: " . $sql);
        $q = $PDOX->queryDie($sql);
    }

    // Add title column
    if (!$PDOX->columnExists('title', "{$CFG->dbprefix}ct_main")) {
        $sql = "ALTER TABLE {$CFG->dbprefix}ct_main ADD title VARCHAR(255) NULL";
        echo("Upgrading: " . $sql . "<br/>\n");
        error_log("Upgrading: " . $sql);
        $q = $PDOX->queryDie($sql);
    }

    // Add onfly column in exercise_sql
    if (!$PDOX->columnExists('exercise_onfly', "{$CFG->dbprefix}ct_sql_exercise")) {
        $sql = "ALTER TABLE {$CFG->dbprefix}ct_sql_exercise ADD exercise_onfly LONGTEXT NULL DEFAULT NULL";
        echo("Upgrading: " . $sql . "<br/>\n");
        error_log("Upgrading: " . $sql);
        $q = $PDOX->queryDie($sql);
    }

    // Add answer_language column to allow student select the language of the answer
    if (!$PDOX->columnExists('answer_language', "{$CFG->dbprefix}ct_answer")) {
        $sql = "ALTER TABLE {$CFG->dbprefix}ct_answer ADD answer_language INTEGER NULL DEFAULT NULL";
        echo("Upgrading: " . $sql . "<br/>\n");
        error_log("Upgrading: " . $sql);
        $q = $PDOX->queryDie($sql);
    }

    return '202012201622';
};
