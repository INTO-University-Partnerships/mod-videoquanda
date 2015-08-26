<?php

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/videoquanda/backup/moodle2/restore_videoquanda_stepslib.php';

class restore_videoquanda_activity_task extends restore_activity_task {

    protected function define_my_settings() {
        // empty
    }

    protected function define_my_steps() {
        $this->add_step(new restore_videoquanda_activity_structure_step('videoquanda_structure', 'videoquanda.xml'));
        $this->add_step(new restore_videoquanda_files_step('videoquanda_files'));
    }

    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('videoquanda', array('header', 'footer'), 'videoquanda');

        return $contents;
    }

    static public function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('VIDEOQUANDAVIEWBYID', '/mod/videoquanda/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('VIDEOQUANDAINDEX', '/mod/videoquanda/index.php?id=$1', 'course');

        return $rules;
    }

}
