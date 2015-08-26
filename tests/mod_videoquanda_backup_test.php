<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/mod_videoquanda_backup_controller.php';

/**
 * @see http://docs.moodle.org/dev/Backup_2.0_for_developers
 */
class mod_videoquanda_backup_test extends advanced_testcase {

    /**
     * @var integer
     */
    protected $_cmid;

    /**
     * @var integer
     */
    protected $_userid;

    /**
     * @var object
     */
    protected $_course;

    /**
     * @var object
     */
    protected $_course_module;

    /**
     * @var integer
     */
    protected $_t0;

    /**
     * @var mod_videoquanda_backup_controller
     */
    protected $_cut;

    /**
     * setUp
     */
    protected function setUp() {
        global $CFG;
        $CFG->keeptempdirectoriesonbackup = true;

        // record initial time
        $this->_t0 = time();

        // create course and some course modules (of which we're testing the last)
        $this->_course = $this->getDataGenerator()->create_course();
        foreach (array('forum', 'forum', 'videoquanda', 'videoquanda') as $module) {
            $this->getDataGenerator()->create_module($module, array(
                'course' => $this->_course->id,
            ));
        }
        $this->_course_module = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $this->_course->id,
            'url' => 'mp4;video.mp4;',
            'header' => array(
                'format' => FORMAT_HTML,
                'text' => '<p>My lovely header</p>'
            ),
            'footer' => array(
                'format' => FORMAT_HTML,
                'text' => '<p>My lovely footer</p>'
            ),
        ));

        // put the video.mp4 file in its right place in moodledata
        check_dir_exists($CFG->dataroot . '/into/mod_videoquanda/' . $this->_course_module->id);
        copy(__DIR__ . '/video/video.mp4', $CFG->dataroot . '/into/mod_videoquanda/' . $this->_course_module->id . '/video.mp4');

        // set the course module id and the user id
        $this->_cmid = $this->_course_module->cmid;
        $this->_userid = 2;

        // create an instance of the class under test
        $this->_cut = new mod_videoquanda_backup_controller(
            backup::TYPE_1ACTIVITY,
            $this->_cmid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $this->_userid
        );

        $this->resetAfterTest(true);
    }

    /**
     * tests instantiation of a backup controller
     */
    public function test_backup_controller_instantiation() {
        $this->assertInstanceOf('backup_controller', $this->_cut);
    }

    /**
     * tests executing a plan creates a single directory in dataroot in /temp/backup
     */
    public function test_execute_plan_creates_one_directory() {
        global $CFG;
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $this->assertCount(0, $child_directories);
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $this->assertCount(1, $child_directories);
    }

    /**
     * tests the backupid corresponds to a directory in dataroot in /temp/backup
     */
    public function test_get_backupid_matches_directory() {
        global $CFG;
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $this->assertCount(1, $child_directories);
        $this->assertEquals($child_directories[0], $this->_cut->get_backupid());
    }

    /**
     * tests executing a plan creates a single course module subdirectory in dataroot in /temp/backup/{backupid}/activities/videoquanda_{cmid}
     */
    public function test_execute_plan_creates_videoquanda_subdirectory() {
        global $CFG;
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $dir = $CFG->dataroot . '/temp/backup/' . $child_directories[0] . '/activities/videoquanda_' . $this->_course_module->cmid;
        $this->assertFileExists($dir);
    }

    /**
     * tests executing a plan for a videoquanda course module creates a module.xml file
     */
    public function test_execute_plan_creates_module_xml() {
        global $CFG;
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $file = $CFG->dataroot . '/temp/backup/' . $child_directories[0] . '/activities/videoquanda_' . $this->_course_module->cmid . '/module.xml';
        $this->assertFileExists($file);
    }

    /**
     * tests executing a plan for a videoquanda course module creates a videoquanda.xml file
     */
    public function test_execute_plan_creates_videoquanda_xml() {
        global $CFG;
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $file = $CFG->dataroot . '/temp/backup/' . $child_directories[0] . '/activities/videoquanda_' . $this->_course_module->cmid . '/videoquanda.xml';
        $this->assertFileExists($file);
    }

    /**
     * tests executing a plan for a videoquanda course module creates a videoquanda.xml file with the expected content
     */
    public function test_execute_plan_creates_expected_videoquanda_xml_content() {
        global $CFG;
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $file = $CFG->dataroot . '/temp/backup/' . $child_directories[0] . '/activities/videoquanda_' . $this->_course_module->cmid . '/videoquanda.xml';
        $xml = simplexml_load_file($file);
        $this->assertEquals($this->_course_module->id, $xml['id']);
        $this->assertSame($this->_course_module->cmid, (integer)$xml['moduleid']);
        $this->assertEquals('videoquanda', $xml['modulename']);
        $this->assertEquals($this->_course_module->name, $xml->videoquanda->name);
        $this->assertGreaterThanOrEqual($this->_t0, (integer)$xml->videoquanda->timecreated);
        $this->assertLessThanOrEqual(time(), (integer)$xml->videoquanda->timecreated);
        $this->assertGreaterThanOrEqual($this->_t0, (integer)$xml->videoquanda->timemodified);
        $this->assertLessThanOrEqual(time(), (integer)$xml->videoquanda->timemodified);
        $this->assertEquals('mp4;video.mp4;', $xml->videoquanda->url);
        $this->assertEquals('<p>My lovely header</p>', $xml->videoquanda->header);
        $this->assertEquals('<p>My lovely footer</p>', $xml->videoquanda->footer);
    }

    /**
     * test that video files get copied into the backup
     */
    public function test_execute_plan_creates_video_files() {
        global $CFG;
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $this->assertFileExists($CFG->dataroot . '/temp/backup/' . $child_directories[0] . '/into/mod_videoquanda/' . $this->_course_module->id . '/video.mp4');
    }

    /**
     * tests encoding content links encodes the /mod/videoquanda/index.php URL
     */
    public function test_encode_content_links_encodes_mod_videoquanda_index_url() {
        global $CFG;
        $link = $CFG->wwwroot . '/mod/videoquanda/index.php?id=123';
        $content = '<p>hello</p><a href="' . $link . '">click here</a><p>world</p>';
        $result = backup_videoquanda_activity_task::encode_content_links($content);
        $encoded_link = '$@VIDEOQUANDAINDEX*123@$';
        $this->assertSame('<p>hello</p><a href="' . $encoded_link . '">click here</a><p>world</p>', $result);
    }

    /**
     * tests encoding content links encodes the /mod/videoquanda/view.php URL
     */
    public function test_encode_content_links_encodes_mod_videoquanda_view_url() {
        global $CFG;
        $link = $CFG->wwwroot . '/mod/videoquanda/view.php?id=123';
        $content = '<p>hello</p><a href="' . $link . '">click here</a><p>world</p>';
        $result = backup_videoquanda_activity_task::encode_content_links($content);
        $encoded_link = '$@VIDEOQUANDAVIEWBYID*123@$';
        $this->assertSame('<p>hello</p><a href="' . $encoded_link . '">click here</a><p>world</p>', $result);
    }

    /**
     * returns an array of directories within the given directory (not recursively)
     * @param string $dir
     * @return array
     */
    protected static function _get_child_directories($dir) {
        $retval = array();
        $ignore = array('.', '..');
        if ($handle = opendir($dir)) {
            while (false !== ($entry = readdir($handle))) {
                if (is_dir($dir . '/' . $entry) && !in_array($entry, $ignore)) {
                    $retval[] = $entry;
                }
            }
            closedir($handle);
        }
        return $retval;
    }

}
