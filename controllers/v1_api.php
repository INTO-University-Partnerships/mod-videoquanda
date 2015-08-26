<?php

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

defined('MOODLE_INTERNAL') || die();

$controller = $app['controllers_factory'];

$is_allowed = function(Request $request) use ($app){
    if (!empty($request) && $request->get('instanceid')) {
        // require course login
        list($course, $cm) = $app['get_course_and_course_module']($request->get('instanceid'));
        $app['require_course_login']($course, $cm);
    } else {
        $app->abort(403, 'You are not allowed to access this content.');
    }
};

/**
 * Handling CRUD operations for questions
 */

// Function to get all questions for this instance
$controller->get('/{instanceid}/questions', function($instanceid) use ($app) {
    global $DB, $USER;
    try {
        list($course, $cm) = $app['get_course_and_course_module']($instanceid);
        $context = context_module::instance($cm->id);
        $can_manage = $app['has_capability']('moodle/course:manageactivities', $context);

        // get groupmode for this instance
        $groupmode = $app['get_groupmode']($course->id, $cm->id);

        $unique_id = $DB->sql_concat('vqq.id', "'_'", "COALESCE(vqa.id, 0)");
        $username1 = $DB->sql_concat('u1.firstname', "' '", 'u1.lastname');
        $username2 = $DB->sql_concat('u2.firstname', "' '", 'u2.lastname');

        $group_sql = '';
        $group_where = '';
        $group_params = [];

        // when groupmode is set and not logged in as admin
        if ($groupmode === SEPARATEGROUPS && !$can_manage) {
            // subquery for joining to groups
            $group_sql = <<<SQL
            LEFT JOIN (
                SELECT g.courseid, gm1.userid
                FROM {groups} g
                INNER JOIN {groups_members} gm1 ON gm1.groupid = g.id
                INNER JOIN {groups_members} gm2 ON gm2.groupid = g.id AND gm2.userid = :userid1
                GROUP BY g.courseid, gm1.userid
            ) g ON g.userid = vqq.userid
                AND g.courseid = vq.course
SQL;
            $group_where = 'AND (g.userid IS NOT NULL OR vqq.userid = :userid2)';
            $group_params = [
                'userid1' => $USER->id,
                'userid2' => $USER->id,
            ];
        }

        // main query
        $sql = <<<SQL
            SELECT
                {$unique_id} AS unique_id, vqq.id, vqq.userid, vqq.timecreated, vqq.timemodified, vqq.seconds, vqq.text,
                {$username1} AS username, vqa.id AS a_id, vqa.userid AS a_userid, vqa.timecreated AS a_timecreated,
                vqa.timemodified AS a_timemodified, vqa.text AS a_text, {$username2} AS a_username
            FROM {videoquanda_questions} vqq
            INNER JOIN {videoquanda} vq
                ON vqq.instanceid = vq.id
            INNER JOIN {user} u1
                ON vqq.userid = u1.id
                AND u1.deleted = 0
            {$group_sql}
            LEFT JOIN {videoquanda_answers} vqa
                ON vqa.questionid = vqq.id
            LEFT JOIN {user} u2
                ON vqa.userid = u2.id
                AND u2.deleted = 0
            WHERE vq.id = :instanceid
            {$group_where}
SQL;
        $result = $DB->get_records_sql($sql, array_merge(['instanceid' => $instanceid], $group_params));

        $questions = array();
        if (!empty($result)) {
            $ids = array();
            foreach ($result as $key => $value) {

                // Check if question has been added to array
                if (!in_array($value->id, $ids)) {
                    $ids[] = $value->id;

                    $questions[$value->id] = array(
                        'id' => $value->id,
                        'userid' => $value->userid,
                        'timecreated' => date("d F Y H:i:s", $value->timecreated),
                        'timemodified' => date("d F Y H:i:s", $value->timemodified),
                        'seconds' => $value->seconds,
                        'text' => format_text($value->text, FORMAT_HTML),
                        'username' => $value->username,
                        'answers' => array()
                    );
                }

                // Check if question has answers
                if (!empty($value->a_id)) {
                    // Add answer to questions 'answers' array
                    $questions[$value->id]['answers'][] = array(
                        'id' => $value->a_id,
                        'questionid' => $value->id,
                        'userid' => $value->a_userid,
                        'timecreated' => date("d F Y H:i:s", $value->a_timecreated),
                        'timemodified' => date("d F Y H:i:s", $value->a_timemodified),
                        'text' => format_text($value->a_text, FORMAT_HTML),
                        'username' => $value->a_username
                    );
                }
            }
        }
        return $app->json($questions);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('message' => 'No questions found.'), 404);
    } catch (Exception $e) {
        return $app->json(array('message' => $e->getMessage()), 500);
    }
})
    ->assert('instanceid','\d+');

// Function to get all questions for a specific second
/*$controller->get('/{instanceid}/questions/{seconds}', function(Request $request, $instanceid, $seconds) use ($app) {
    global $DB;
    try {
        $username = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
        $sql = "SELECT q.id, q.userid, q.timecreated, q.timemodified, q.seconds, q.text, COUNT(a.id) AS no_of_answers, {$username} AS username
                    FROM {videoquanda_questions} AS q
                    LEFT JOIN {videoquanda_answers} AS a ON a.questionid = q.id
                    INNER JOIN {user} AS u ON u.id = q.userid AND u.deleted = 0
                    WHERE instanceid = :instanceid
                    AND q.seconds = :seconds
                    AND q.timemodified >= :timemodified
                    GROUP BY q.id, u.firstname, u.lastname
                    ORDER BY q.seconds";
        $questions = $DB->get_records_sql($sql, array(
            'instanceid' => $instanceid,
            'seconds' => $seconds,
            'timemodified' => (integer)$request->get('timemodified')
        ));
        return $app->json($questions);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('message' => 'No questions found for {$seconds}.'), 404);
    } catch (Exception $e) {
        return $app->json(array('error' => $e->getMessage()), 500);
    }

})
    ->assert('instanceid','\d+')
    ->assert('seconds','\d+');*/

// Function to post a question
$controller->post('/{instanceid}/questions', function(Request $request, $instanceid) use ($app) {
    global $DB, $USER;

    // ensure the user isn't the guest user
    if (isguestuser()) {
        return $app->json(get_string('jsonapi:submitquestionasguestdenied', $app['plugin']), 400);
    }

    try {
        $time = time();
        $content = json_decode($request->getContent());
        $fields = array(
            'instanceid' => $instanceid,
            'userid' => $USER->id,
            'timecreated' => $time,
            'timemodified' => $time
        );
        $question = (object) array_merge((array)$content, $fields);
        $question->text = strip_tags($question->text);
        $id = $DB->insert_record('videoquanda_questions', $question);
        return $app->json(array('message' => 'Question posted successfully.', 'id' => $id), 201);
    } catch (Exception $e) {
        return $app->json(array('error' => $e->getMessage()), 500);
    }
})
    ->assert('instanceid','\d+');

// Function to change a question
$controller->put('/{instanceid}/questions/{questionid}', function(Request $request, $instanceid, $questionid) use ($app) {
    global $DB, $USER;
    try {
        // get module context
        list($course, $cm) = $app['get_course_and_course_module']($instanceid);
        $context = context_module::instance($cm->id);
        $existing = $DB->get_record('videoquanda_questions', array('id' => $questionid), '*', MUST_EXIST);
        // Check if question is posted by current logged in user
        if (!$app['has_capability']('moodle/course:manageactivities', $context) && $USER->id !== $existing->userid) {
            return $app->json(array('message' => 'You are not allowed to update this question.'), 405);
        }
        $question = json_decode($request->getContent());
        $question->id = $questionid;
        $question->timemodified = time();
        $question->text = strip_tags($question->text);
        $question = (object)array_merge((array)$existing, (array)$question);
        $DB->update_record('videoquanda_questions', $question);
        return $app->json(array('message' => 'Question updated', 'id' => $questionid), 200);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('message' => 'Question could not be found'), 404);
    } catch (Exception $e) {
        return $app->json(array('error' => $e->getMessage()), 500);
    }
})
    ->assert('instanceid', '\d+')
    ->assert('questionid', '\d+');

// Function to delete a question (check that there are no answers)
$controller->delete('/{instanceid}/questions/{questionid}', function(Request $request, $instanceid, $questionid) use ($app) {
    global $DB, $USER;
    try {
        // get module context
        list($course, $cm) = $app['get_course_and_course_module']($instanceid);
        $context = context_module::instance($cm->id);
        $existing = $DB->get_record('videoquanda_questions', array('id' => $questionid), '*', MUST_EXIST);
        // Check capabilities and owner rights to this question
        if (!$app['has_capability']('moodle/course:manageactivities', $context) && $existing->userid != $USER->id) {
            return $app->json(array('message' => 'You are not allowed to delete this question.'), 405);
        }
        // Check if question has answers, in which case the question should not be deleted
        if ($DB->record_exists('videoquanda_answers', array('questionid' => $questionid)) && !$app['has_capability']('moodle/course:manageactivities', $context)) {
            return $app->json(array('message' => 'You are not allowed to delete this question.'), 405);
        }
        $DB->delete_records('videoquanda_questions', array('id' => $questionid));
        return $app->json(array('message' => 'Question deleted successfully.'), 204);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('message' => 'Question could not be found.'), 404);
    } catch (Exception $e) {
        return $app->json(array('error' => $e->getMessage()), 500);
    }
})
    ->assert('instanceid', '\d+')
    ->assert('questionid', '\d+');

/**
 * Handling CRUD operations for answers
 */

// Function to get all answers for a specific question
/*$controller->get('/{instanceid}/questions/{questionid}/answers', function(Request $request, $questionid) use ($app) {
    global $DB;
    try {
        $username = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
        $sql = "SELECT a.id, a.questionid, a.userid, a.timecreated, a.timemodified, a.text, {$username} AS username
                    FROM {videoquanda_answers} AS a
                    INNER JOIN {user} AS u ON u.id = a.userid AND u.deleted = 0
                    WHERE a.questionid = :questionid
                    AND a.timemodified >= :timemodified
                    ORDER BY timemodified DESC";
        $answers = $DB->get_records_sql($sql, array(
            'questionid' => $questionid,
            'timemodified' => (integer)$request->get('timemodified')
        ));
        return $app->json($answers);
    }  catch (dml_missing_record_exception $e) {
        return $app->json(array('message' => 'No answers.'), 404);
    } catch (Exception $e) {
        return $app->json(array('error' => $e->getMessage()), 500);
    }
})
    ->assert('instanceid', '\d+')
    ->assert('questionid', '\d+');*/


// Function to post an answer
$controller->post('/{instanceid}/questions/{questionid}/answers', function(Request $request, $questionid) use ($app) {
    global $DB, $USER;

    // ensure the user isn't the guest user
    if (isguestuser()) {
        return $app->json(get_string('jsonapi:submitanswerasguestdenied', $app['plugin']), 400);
    }

    try {
        $existing = $DB->get_record('videoquanda_questions', array('id' => $questionid), '*', MUST_EXIST);
        $time = time();
        $content = json_decode($request->getContent());
        $fields = array(
            'questionid' => $questionid,
            'userid' => $USER->id,
            'timecreated' => $time,
            'timemodified' => $time
        );
        $answer = (object) array_merge((array)$content, $fields);
        $answer->text = strip_tags($answer->text);
        $id = $DB->insert_record('videoquanda_answers', $answer);
        return $app->json(array('message' => 'Answer posted successfully.', 'id' => $id), 201);
    }  catch (dml_missing_record_exception $e) {
        return $app->json(array('message' => 'Question does not exist. So not posting answer.'), 404);
    } catch (Exception $e) {
        return $app->json(array('error' => $e->getMessage()), 500);
    }
})
    ->assert('instanceid','\d+')
    ->assert('questionid','\d+');

// Function to change and answer
$controller->put('/{instanceid}/questions/{questionid}/answers/{answerid}', function(Request $request, $instanceid, $answerid) use ($app) {
    global $DB, $USER;
    try {
        // get module context
        list($course, $cm) = $app['get_course_and_course_module']($instanceid);
        $context = context_module::instance($cm->id);
        $existing = $DB->get_record('videoquanda_answers', array('id' => $answerid), '*', MUST_EXIST);
        // Check capabilities and owner rights to this answer
        if (!$app['has_capability']('moodle/course:manageactivities', $context) && $USER->id !== $existing->userid) {
            return $app->json(array('message' => 'You are not allowed to update this answer.'), 405);
        }
        $answer = json_decode($request->getContent());
        $answer->id = $answerid;
        $answer->timemodified = time();
        $answer->text = strip_tags($answer->text);
        $answer = (object)array_merge((array)$existing, (array)$answer);
        $DB->update_record('videoquanda_answers', $answer);
        return $app->json(array('message' => 'Answer updated.'), 200);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('message' => 'Answer could not be found.'), 404);
    } catch (Exception $e) {
        return $app->json(array('error' => $e->getMessage()), 500);
    }
})
    ->assert('instanceid', '\d+')
    ->assert('questionid', '\d+')
    ->assert('answerid', '\d+');

// Function to delete an answer
$controller->delete('/{instanceid}/questions/{questionid}/answers/{answerid}', function($instanceid, $answerid) use ($app) {
    global $DB, $USER;
    try {
        // get module context
        list($course, $cm) = $app['get_course_and_course_module']($instanceid);
        $context = context_module::instance($cm->id);
        $existing = $DB->get_record('videoquanda_answers', array('id' => $answerid), '*', MUST_EXIST);
        // Check capabilities and owner rights to this answer
        if (!$app['has_capability']('moodle/course:manageactivities', $context) && $USER->id !== $existing->userid) {
            return $app->json(array('message' => 'You are not allowed to delete this answer.'), 405);
        }
        $DB->delete_records('videoquanda_answers', array('id' => $answerid));
        return $app->json(array('message' => 'Answer deleted successfully.'), 204);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('message' => 'Answer could not be found.'), 404);
    } catch (Exception $e) {
        return $app->json(array('error' => $e->getMessage()));
    }
})
    ->assert('instanceid', '\d+')
    ->assert('questionid', '\d+')
    ->assert('answerid', '\d+');

$controller->before($is_allowed);

return $controller;