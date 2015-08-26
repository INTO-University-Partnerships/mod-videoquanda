<?php

defined('MOODLE_INTERNAL') || die();

$app['course_module_instance_list_viewed'] = $app->protect(function (stdClass $course) {
    $event = \mod_videoquanda\event\course_module_instance_list_viewed::create(array(
        'context' => context_course::instance($course->id),
    ));
    $event->add_record_snapshot('course', $course);
    $event->trigger();
});
