<?php

defined('MOODLE_INTERNAL') || die();

/**
 * removes the uploaded files directory
 * @return boolean
 */
function xmldb_videoquanda_uninstall() {
    global $CFG;
    remove_dir($CFG->dataroot . '/into/mod_videoquanda');
    return true;
}
