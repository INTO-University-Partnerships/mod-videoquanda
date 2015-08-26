<?php

defined('MOODLE_INTERNAL') || die;

class backup_videoquanda_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {
        $videoquanda = new backup_nested_element('videoquanda', array('id'), array(
            'name',
            'url',
            'header',
            'footer',
            'timecreated',
            'timemodified',
        ));

        $videoquanda->set_source_table('videoquanda', array('id' => backup::VAR_ACTIVITYID));

        return $this->prepare_activity_structure($videoquanda);
    }

}

class backup_videoquanda_files_step extends backup_execution_step {

    /**
     * @global moodle_database $DB
     */
    protected function define_execution() {
        global $CFG, $DB;

        // create /into/mod_videoquanda/{instanceid} subdirectory
        $instanceid = $this->task->get_activityid();
        $basepath = $this->get_basepath() . '/into/mod_videoquanda/' . $instanceid . '/';
        check_dir_exists($basepath);

        // get the 'url' field
        $url = $DB->get_field('videoquanda', 'url', array('id' => $instanceid), MUST_EXIST);
        if (empty($url)) {
            return;
        }
        $videos = explode(';', $url);
        if (empty($videos)) {
            return;
        }

        // remove any entries in the 'url' field for which a file does not exist in moodledata
        $files_to_copy = array();
        foreach ($videos as $video) {
            $video = trim($video);
            if (empty($video) || !file_exists($CFG->dataroot . '/into/mod_videoquanda/' . $instanceid . '/' . $video)) {
                continue;
            }
            $files_to_copy[] = $video;
        }

        // no copying needs to be done if there are no files to copy
        if (empty($files_to_copy)) {
            return;
        }

        // copy all the video files to the base path
        foreach ($files_to_copy as $file_to_copy) {
            copy($CFG->dataroot . '/into/mod_videoquanda/' . $instanceid . '/' . $file_to_copy, $basepath . $file_to_copy);
        }
    }

}
