<?php

use Mockery as m;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../lib.php';

class mod_videoquanda_lib_test extends advanced_testcase {

    /**
     * @var string
     */
    protected $_upload_path;

    /**
     * setUp
     */
    protected function setUp() {
        global $CFG;
        $this->_upload_path = $CFG->dataroot . '/into/mod_videoquanda';

        // reset after test
        $this->resetAfterTest();
    }

    /**
     * tearDown
     */
    public function tearDown() {
       // m::close();
    }

    /**
     * updating instance
     */
    public function test_videoquanda_update_instance(){
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id,
            'header' => array('text'=> '<p>This is some header text explaining how videoquanda works.</p>'),
            'footer' => array('text'=> '<p>This is the footer.</p>')
        ));
        $videoquanda->instance = $videoquanda->id;

        $this->assertEquals(1, $DB->count_records('videoquanda'));
        $now = time();
        $success = videoquanda_update_instance($videoquanda);
        $this->assertTrue($success);
        $this->assertEquals($now, $DB->get_field('videoquanda','timemodified', array('id' => $videoquanda->id)));

    }

    /**
     * deleting instance
     */
    public function test_videoquanda_delete_instance() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $videoquanda2 = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, 1, $now, $now + 1, 2, 'dummy text')
            ),
            'videoquanda_answers' => array(
                array('questionid', 'userid', 'timecreated', 'timemodified', 'text'),
                array(1, 1, $now, $now + 1, 'dummy answer.')
            )
        )));

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(2, $videoquanda2->id, 1, $now, $now + 1, 2, 'dummy text')
            ),
            'videoquanda_answers' => array(
                array('questionid', 'userid', 'timecreated', 'timemodified', 'text'),
                array(2, 1, $now, $now + 1, 'dummy answer.')
            )
        )));

        check_dir_exists($this->_upload_path . '/' . $videoquanda->id . '/1');
        copy(__DIR__ . '/video/video.mp4', $this->_upload_path . '/' . $videoquanda->id .'/video.mp4');

        $this->assertFileExists($this->_upload_path . '/' . $videoquanda->id . '/video.mp4');
        $this->assertEquals(1, $DB->count_records('videoquanda_questions', array('instanceid' => $videoquanda->id)));
        $this->assertEquals(1, $DB->count_records('videoquanda_answers', array('questionid' => 1)));
        $this->assertEquals(2, $DB->count_records('videoquanda_questions'));
        $this->assertEquals(2, $DB->count_records('videoquanda_answers'));

        $success = videoquanda_delete_instance($videoquanda->id);
        $this->assertTrue($success);
        $this->assertFileNotExists($this->_upload_path . '/' . $videoquanda->id . '/video.mp4');
        $this->assertFalse($DB->record_exists('videoquanda_questions', array('instanceid' => $videoquanda->id)));
        $this->assertFalse($DB->record_exists('videoquanda_answers', array('questionid' => 1)));
        $this->assertEquals(1, $DB->count_records('videoquanda_questions'));
        $this->assertEquals(1, $DB->count_records('videoquanda_answers'));
    }

    /**
     * tests the features that videoquanda supports
     */
    public function test_videoquanda_supports() {
        $features = array(
            FEATURE_COMPLETION_TRACKS_VIEWS,
            FEATURE_BACKUP_MOODLE2,
            FEATURE_GROUPS,
        );
        foreach ($features as $feature) {
            $this->assertTrue(plugin_supports('mod', 'videoquanda', $feature));
        }
    }

    /**
     * tests the feature that videoquanda does not support
     */
    public function test_videoquanda_not_supports() {
        $features = array(
            FEATURE_GRADE_HAS_GRADE,
            FEATURE_GRADE_OUTCOMES,
            FEATURE_ADVANCED_GRADING,
            FEATURE_CONTROLS_GRADE_VISIBILITY,
            FEATURE_PLAGIARISM,
            FEATURE_COMPLETION_HAS_RULES,
            FEATURE_NO_VIEW_LINK,
            FEATURE_IDNUMBER,
            FEATURE_GROUPINGS,
            FEATURE_MOD_ARCHETYPE,
            FEATURE_MOD_INTRO,
            FEATURE_MODEDIT_DEFAULT_COMPLETION,
            FEATURE_COMMENT,
            FEATURE_RATE,
            FEATURE_SHOW_DESCRIPTION,
        );
        foreach ($features as $feature) {
            $this->assertFalse(plugin_supports('mod', 'videoquanda', $feature));
        }
    }

}
