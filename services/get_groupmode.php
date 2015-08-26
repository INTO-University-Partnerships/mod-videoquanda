<?php

defined('MOODLE_INTERNAL') || die();

$app['get_groupmode'] = $app->protect(function ($courseid, $cmid) {
    $course_modinfo = get_fast_modinfo($courseid);
    $cm_info = $course_modinfo->cms[$cmid];
    $groupmode = (integer)groups_get_activity_groupmode($cm_info);
    if ($groupmode === VISIBLEGROUPS) {
        return NOGROUPS;
    }
    return $groupmode;
});
