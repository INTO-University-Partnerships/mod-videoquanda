<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/mod_videoquanda_restore_controller.php';

/**
 * @see http://docs.moodle.org/dev/Restore_2.0_for_developers
 */
class mod_videoquanda_restore_test extends advanced_testcase {

    /**
     * @var integer
     */
    protected $_categoryid;

    /**
     * @var integer
     */
    protected $_userid;

    /**
     * @var integer
     */
    protected $_courseid;

    /**
     * @var mod_videoquanda_restore_controller
     */
    protected $_cut;

    /**
     * @var moodle_transaction
     */
    protected $_transaction;

    /**
     * setUp
     * @global moodle_database $DB
     */
    protected function setUp() {
        global $CFG, $DB;

        // copy the 'restoreme' directory to dataroot
        $src = __DIR__ . '/restoreme/';
        check_dir_exists($CFG->dataroot . '/temp/backup/');
        $dest = $CFG->dataroot . '/temp/backup/';
        shell_exec("cp -r {$src} {$dest}");

        // set parameters, create a course to restore into
        $folder = 'restoreme';
        $this->_categoryid = 1;
        $this->_userid = 2;
        $this->_courseid = restore_dbops::create_new_course('Restored course fullname', 'Restored course shortname', $this->_categoryid);

        // create an instance of the class under test
        $this->_transaction = $DB->start_delegated_transaction();
        $this->_cut = new mod_videoquanda_restore_controller(
            $folder,
            $this->_courseid,
            backup::INTERACTIVE_NO,
            backup::MODE_SAMESITE,
            $this->_userid,
            backup::TARGET_NEW_COURSE
        );

        $this->resetAfterTest(true);
    }

    /**
     * tests instantiation of a restore controller
     */
    public function test_restore_controller_instantiation() {
        $this->assertInstanceOf('restore_controller', $this->_cut);
    }

    /**
     * tests the plan has no missing modules
     */
    public function test_restore_plan_has_no_missing_modules() {
        $this->assertFalse($this->_cut->get_plan()->is_missing_modules());
    }

    /**
     * tests that the precheck returns true as expected
     */
    public function test_execute_precheck_returns_true() {
        $result = $this->_cut->execute_precheck();
        $this->assertTrue($result);
        $this->_cut->execute_plan();
        $this->_transaction->allow_commit();
    }

    /**
     * tests that executing the plan renames the destination course
     * @global moodle_database $DB
     */
    public function test_execute_plan_renames_destination_course() {
        global $DB;

        $before_courseid = (integer)$DB->get_field('course', 'id', array(
            'fullname' => 'Restored course fullname',
            'shortname' => 'Restored course shortname',
        ), MUST_EXIST);
        $this->assertGreaterThanOrEqual(1, $before_courseid);

        $this->_cut->execute_precheck();
        $this->_cut->execute_plan();
        $this->_transaction->allow_commit();

        $after_courseid = (integer)$DB->get_field('course', 'id', array(
            'fullname' => '002',
            'shortname' => '002',
        ), MUST_EXIST);
        $this->assertGreaterThanOrEqual(1, $after_courseid);

        $this->assertSame($this->_courseid, $before_courseid);
        $this->assertSame($before_courseid, $after_courseid);
    }

    /**
     * tests that executing the plan restores the module
     * @global moodle_database $DB
     */
    public function test_execute_plan_restores_module() {
        global $CFG, $DB;

        $this->_cut->execute_precheck();
        $this->_cut->execute_plan();
        $this->_transaction->allow_commit();

        $this->assertEquals(1, $DB->count_records('videoquanda'));
        $data = (array)$DB->get_record('videoquanda', array(), '*', MUST_EXIST);
        $this->assertSame('Videoquanda 001', $data['name']);
        $this->assertContains('Course home header', $data['header']);
        $this->assertContains('Course home footer', $data['footer']);
        $this->assertContains('course/view.php?id=' . $this->_courseid, $data['header']);
        $this->assertContains('course/view.php?id=' . $this->_courseid, $data['footer']);
        $this->assertSame('mp4;chrome_imf.mp4;', $data['url']);

        $this->assertFileExists($CFG->dataroot . '/into/mod_videoquanda/' . $data['id'] . '/chrome_imf.mp4');

        $this->assertEquals(0, $DB->count_records('videoquanda_questions'));
        $this->assertEquals(0, $DB->count_records('videoquanda_answers'));
    }

}
