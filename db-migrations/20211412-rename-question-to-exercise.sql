USE `tsugi`;

-- RENAMING TABLE
-- ct_question  ->  ct_exercise

alter table ct_question change question_id exercise_id varchar(50) not null;

alter table ct_question change question_num exercise_num int null;

alter table ct_question change question_must exercise_must varchar(50) null;

alter table ct_question change question_musnt exercise varchar(50) null;

rename table ct_question to ct_exercise;

-- RENAMING TABLE
-- ct_code_question  ->  ct_code_exercise

alter table ct_answer change question_id exercise_id varchar(50) not null;

alter table ct_code_question change question_id exercise_id varchar(50) not null;

alter table ct_code_question change question_language exercise_language int default 1 not null;

alter table ct_code_question change question_input_test exercise_input_test text null;

alter table ct_code_question change question_input_grade exercise_input_grade text null;

alter table ct_code_question change question_output_test exercise_output_test text null;

alter table ct_code_question change question_output_grade exercise_output_grade text null;

alter table ct_code_question change question_solution exercise_solution text null;

rename table ct_code_question to ct_code_exercise;

-- RENAMING TABLE
-- ct_sql_question  ->  ct_sql_exercise

alter table ct_sql_question change question_id exercise_id varchar(50) not null;

alter table ct_sql_question change question_dbms exercise_dbms tinyint default 0 not null;

alter table ct_sql_question change question_sql_type exercise_sql_type varchar(20) default 'SELECT' null;

alter table ct_sql_question change question_database exercise_database varchar(100) null;

alter table ct_sql_question change question_solution exercise_solution text null;

alter table ct_sql_question change question_probe exercise_probe text null;

alter table ct_sql_question change question_onfly exercise_onfly longtext null;

rename table ct_sql_question to ct_sql_exercise;


alter table ct_exercise add statement text null;

alter table ct_exercise add hint varchar(250) null;

alter table ct_exercise drop exercise_musnt;

<<<<<<< HEAD
alter table ct_exercise drop exercise_must;

alter table ct_exercise add library varchar(250) null;
=======
alter table ct_exercise drop exercise_must;
>>>>>>> a43afed2771babaa296a1ee02f2a33a7caa9f4b4
