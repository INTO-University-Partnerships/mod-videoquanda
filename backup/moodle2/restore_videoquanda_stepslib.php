<?php

defined('MOODLE_INTERNAL') || die;

class restore_videoquanda_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {
        $paths = array();
        $paths[] = new restore_path_element('videoquanda', '/activity/videoquanda');
        return $this->prepare_activity_structure($paths);
    }

    protected function process_videoquanda($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timecreated = $data->timemodified = time();

        $newitemid = $DB->insert_record('videoquanda', $data);
        $this->apply_activity_instance($newitemid);
    }

}

class restore_videoquanda_files_step extends restore_execution_step {

    /**
     * @global moodle_database $DB
     */
    protected function define_execution() {
        global $CFG, $DB;

        // create /into/mod_videoquanda/{instanceid} subdirectory
        $oldinstanceid = $this->task->get_old_activityid();
        $newinstanceid = $this->task->get_activityid();
        $basepath = $this->get_basepath() . '/into/mod_videoquanda/' . $oldinstanceid . '/';

        // get the 'url' field
        $url = $DB->get_field('videoquanda', 'url', array('id' => $newinstanceid), MUST_EXIST);
        if (empty($url)) {
            return;
        }
        $videos = explode(';', $url);
        if (empty($videos)) {
            return;
        }

        // remove any entries in the 'url' field for which a file does not exist in the base path
        $files_to_copy = array();
        foreach ($videos as $video) {
            $video = trim($video);
            if (empty($video) || !file_exists($basepath . $video)) {
                continue;
            }
            $files_to_copy[] = $video;
        }

        // no copying needs to be done if there are no files to copy
        if (empty($files_to_copy)) {
            return;
        }

        // copy all the video files to dataroot
        check_dir_exists($CFG->dataroot . '/into/mod_videoquanda/' . $newinstanceid . '/');
        foreach ($files_to_copy as $file_to_copy) {
            copy($basepath . $file_to_copy, $CFG->dataroot . '/into/mod_videoquanda/' . $newinstanceid . '/' . $file_to_copy);
        }
    }

}
