<?php

defined('MOODLE_INTERNAL') || die();

$app['get_course_and_course_module'] = $app->protect(function ($instanceid) use ($app) {
    global $DB;

    // get module id from modules table
    $moduleid = (integer)$DB->get_field('modules', 'id', array(
        'name' => $app['module_table'],
    ), MUST_EXIST);

    // get course module
    $cm = $DB->get_record('course_modules', array(
        'module' => $moduleid,
        'instance' => $instanceid,
    ), '*', MUST_EXIST);

    // get course
    $course = $DB->get_record('course', array(
        'id' => $cm->course,
    ), '*', MUST_EXIST);

    return array($course, $cm);
});
