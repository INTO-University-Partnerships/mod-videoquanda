<?php

defined('MOODLE_INTERNAL') || die();

/**
 * @global moodle_database $DB
 * @param object $obj
 * @param mod_videoquanda_mod_form $mform
 * @return integer
 */
function videoquanda_add_instance($obj, mod_videoquanda_mod_form $mform = null) {
    global $DB;

    $obj->timecreated = $obj->timemodified = time();
    // Header and footer get posted as an Array instead of a string so converting it in a string.
    $obj->header = !empty($obj->header['text']) ? $obj->header['text'] : '';
    $obj->footer = !empty($obj->footer['text']) ? $obj->footer['text'] : '';
    $obj->id = $DB->insert_record('videoquanda', $obj);

    return $obj->id;
}

/**
 * @global moodle_database $DB
 * @param object $obj
 * @param mod_videoquanda_mod_form $mform
 * @return boolean
 */
function videoquanda_update_instance($obj, mod_videoquanda_mod_form $mform = null) {
    global $DB;

    $obj->id = $obj->instance;
    $obj->timemodified = time();

    // Header and footer get posted as an Array instead of a string so converting it in a string.
    $obj->header = !empty($obj->header['text']) ? $obj->header['text'] : '';
    $obj->footer = !empty($obj->footer['text']) ? $obj->footer['text'] : '';
    $success = $DB->update_record('videoquanda', $obj);

    return $success;
}

/**
 * @global moodle_database $DB
 * @param integer $id
 * @return boolean
 */
function videoquanda_delete_instance($id) {
    global $DB, $CFG;
    $questions = $DB->get_records_sql("SELECT id FROM {videoquanda_questions} WHERE instanceid = :instanceid", array('instanceid' => $id));
    foreach($questions as $question) {
        $DB->delete_records('videoquanda_answers', array('questionid' => $question->id));
    }
    $DB->delete_records('videoquanda_questions', array('instanceid' => $id));
    $success = $DB->delete_records('videoquanda', array('id' => $id));
    remove_dir($CFG->dataroot . '/into/mod_videoquanda/' . $id);
    return $success;
}

/**
 * @param string $feature
 * @return boolean
 */
function videoquanda_supports($feature) {
    $support = array(
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_ADVANCED_GRADING => false,
        FEATURE_CONTROLS_GRADE_VISIBILITY => false,
        FEATURE_PLAGIARISM => false,
        FEATURE_COMPLETION_HAS_RULES => false,
        FEATURE_NO_VIEW_LINK => false,
        FEATURE_IDNUMBER => false,
        FEATURE_GROUPS => true,
        FEATURE_GROUPINGS => false,
        FEATURE_MOD_ARCHETYPE => false,
        FEATURE_MOD_INTRO => false,
        FEATURE_MODEDIT_DEFAULT_COMPLETION => false,
        FEATURE_COMMENT => false,
        FEATURE_RATE => false,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_SHOW_DESCRIPTION => false,
    );
    if (!array_key_exists($feature, $support)) {
        return null;
    }
    return $support[$feature];
}