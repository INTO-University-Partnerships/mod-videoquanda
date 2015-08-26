<?php

defined('MOODLE_INTERNAL') || die;

require_once $CFG->dirroot . '/mod/videoquanda/backup/moodle2/backup_videoquanda_stepslib.php';

class backup_videoquanda_activity_task extends backup_activity_task {

    protected function define_my_settings() {
        // empty
    }

    protected function define_my_steps() {
        $this->add_step(new backup_videoquanda_activity_structure_step('videoquanda_structure', 'videoquanda.xml'));
        $this->add_step(new backup_videoquanda_files_step('videoquanda_files'));
    }

    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        // link to the list of pages
        $search="/(".$base."\/mod\/videoquanda\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@VIDEOQUANDAINDEX*$2@$', $content);

        // link to page view by moduleid
        $search="/(".$base."\/mod\/videoquanda\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@VIDEOQUANDAVIEWBYID*$2@$', $content);

        return $content;
    }

}
