<?php

defined('MOODLE_INTERNAL') || die();

$controller = $app['controllers_factory'];

// view the given activity
$controller->get('/{cmid}', function ($cmid) use ($app) {
    global $DB, $CFG, $PAGE, $USER;

    // get course module id
    $cm = $DB->get_record('course_modules', array(
        'id' => $cmid,
    ), '*', MUST_EXIST);

    // get instance
    $instance = $DB->get_record($app['module_table'], array(
        'id' => $cm->instance,
    ), '*', MUST_EXIST);

    // get course
    $course = $DB->get_record('course', array(
        'id' => $cm->course,
    ), '*', MUST_EXIST);

    // require course login
    $app['require_course_login']($course, $cm);

    // get module context
    $context = context_module::instance($cm->id);

    // log it
    $app['course_module_viewed']($cm, $instance, $course, $context);

    // mark viewed
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

    // Add language strings to js
    $PAGE->requires->strings_for_js(array(
        'question_will_appear_at_seconds',
        'notify_empty_question',
        'notify_empty_answer',
        'answer',
        'answers',
        'confirm_delete_question',
        'confirm_delete_answer'
    ), $app['plugin']);

    // set heading and title
    $app['heading_and_title']($course->fullname, $instance->name);

    // Get urls from database to pass on to the twig template
    $videos = array();
    if (!empty($instance->url)) {
        $datavideos = explode(';', $instance->url);

        foreach ($app['accepted_file_types'] as $key => $type) {
            if (!empty($datavideos) && in_array($key, $datavideos)) {
                $index = array_search($key, $datavideos);
                $videos[] = array(
                    'file' => $CFG->wwwroot . SLUG . $app['url_generator']->generate('file', array(
                        'instanceid' => $cm->instance,
                        'file' => $datavideos[$index + 1]
                    )),
                    'type' => $key
                );
            }
        }
    }

    // Abort the app if there are no videos.
    if (empty($videos)) {
        $app->abort(404, 'There are no videos to play.');
    }

    // get module context
    $context = context_module::instance($cm->id);

    // render
    return $app['twig']->render('view.twig', array(
        'cm' => $cm,
        'instance' => $instance,
        'course' => $course,
        'videos' => $videos,
        'can_manage' => $app['has_capability']('moodle/course:manageactivities', $context),
        'is_guest' => isguestuser(),
        'userid' => $USER->id
    ));
})
    ->bind('view')
    ->assert('cmid', '\d+');

// view the given activity
$controller->get('/instance/{id}', function ($id) use ($app) {
    global $CFG, $DB;

    // get module id from modules table
    $moduleid = (integer)$DB->get_field('modules', 'id', array(
        'name' => $app['module_table'],
    ), MUST_EXIST);

    // get course module id
    $cmid = (integer)$DB->get_field('course_modules', 'id', array(
        'module' => $moduleid,
        'instance' => $id,
    ), MUST_EXIST);

    // redirect
    return $app->redirect($CFG->wwwroot . SLUG . $app['url_generator']->generate('view', array(
            'cmid' => $cmid,
        )));
})
    ->bind('byinstanceid')
    ->assert('id', '\d+');

// View uploaded files
$controller->get('/file/{instanceid}/{file}', function($instanceid, $file) use($app) {
    global $CFG;

    // require course login
    list($course, $cm) = $app['get_course_and_course_module']($instanceid);
    $app['require_course_login']($course, $cm);

    // Define the route to the file
    $fullfile = $CFG->dataroot . '/into/mod_videoquanda/' . $instanceid . '/' . $file;

    if (!file_exists($fullfile)) {
        $app->abort(404, get_string('storedfilecannotread', 'error'));
    }

    // close the session to avoid locking issues
    \core\session\manager::write_close();

    $splFileInfo = new SplFileInfo($fullfile);
    return $app->sendFile($splFileInfo);
})
    ->assert('instanceid','\d+')
    ->bind('file');

// return the controller
return $controller;
