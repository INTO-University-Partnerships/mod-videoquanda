<?php

use Mockery as m;

// use the Client and Request classes
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpFoundation\Request;

defined('MOODLE_INTERNAL') || die();

class mod_videoquanda_web_test extends advanced_testcase {

    /**
     * @var Silex\Application
     */
    protected $_app;

    protected $_upload_path;

    /**
     * setUp
     */
    public function setUp() {

        global $CFG;
        $this->_upload_path = $CFG->dataroot . '/into/mod_videoquanda';

        if (!defined('SLUG')) {
            define('SLUG', '');
        }

        // create Silex app
        $this->_app = require __DIR__ . '/../app.php';
        $this->_app['debug'] = true;
        $this->_app['exception_handler']->disable();

        // add middleware to work around Moodle expecting non-empty $_GET or $_POST
        $this->_app->before(function (Request $request) {
            if (empty($_GET) && 'GET' == $request->getMethod()) {
                $_GET = $request->query->all();
            }
            if (empty($_POST) && 'POST' == $request->getMethod()) {
                $_POST = $request->request->all();
            }
        });

        // reset the database after each test
        $this->resetAfterTest();
    }

    /**
     * tearDown
     */
    public function tearDown() {
        $_GET = array();
        $_POST = array();
    }

    /**
     * tests a non-existent route
     */
    public function test_non_existent_route() {
        $client = new Client($this->_app);
        $client->request('GET', '/does_not_exist');

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
        $this->assertContains('No route found for &quot;GET /does_not_exist&quot;', $client->getResponse()->getContent());
    }

    /**
     * tests the instances route that shows all activity instances (i.e. course modules) in a certain course
     * @global moodle_database $DB
     */
    public function test_instances_route() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // create a handful of modules within the course
        foreach (range(1, 5) as $i) {
            $module = $this->getDataGenerator()->create_module('videoquanda', array(
                'course' => $course->id,
            ));
        }

        // login the user
        $this->setUser($user);

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/instances/' . $course->id);
        $this->assertTrue($client->getResponse()->isOk());

        // check the page content
        foreach (range(1, 5) as $i) {
            $this->assertContains('Videoquanda ' . $i, $client->getResponse()->getContent());
        }
        $this->assertNotContains('Videoquanda 6', $client->getResponse()->getContent());
    }

    /**
     * tests the 'byinstanceid' route that lets you view a videoquanda by instance id (as opposed to course module id)
     */
    public function test_byinstanceid_route() {
        global $CFG;
        $client = new Client($this->_app);
        $course = $this->getDataGenerator()->create_course();
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id,
        ));
        $client->request('GET', '/instance/' . $videoquanda->id);
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('view', array(
            'cmid' => $videoquanda->cmid,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));
    }

    /**
     * tests the 'view' route that lets you view a videoquanda by course module id
     * @global moodle_database $DB
     */
    public function test_view_route() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'name' => 'Videoquanda activity name',
            'course' => $course->id,
            'url' => 'mp4;video.mp4',
            'header' => array('text'=> '<p>This is some header text explaining how videoquanda works.</p>'),
            'footer' => array('text'=> '<p>This is the footer.</p>')
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', "/" . $videoquanda->cmid);
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertRegExp('/<div class="source" data-src=".*video.mp4" data-video-type="mp4">/', $client->getResponse()->getContent());
        $this->assertContains('<h2>Videoquanda activity name</h2>', $client->getResponse()->getContent());
        $this->assertContains('This is some header text explaining how videoquanda works.', $client->getResponse()->getContent());
        $this->assertContains('This is the footer.', $client->getResponse()->getContent());
    }
    
    /**
     * test view rout for not logged in user
     */
    public function test_view_route_not_logged_in(){
        // create a course
        $course = $this->getDataGenerator()->create_course();
        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $client = new Client($this->_app);
        $client->request('GET', "/" . $videoquanda->id);
        $this->assertEquals(500, $client->getResponse()->getStatusCode());

    }

    /**
     * test view route without a video element
     */
    public function test_view_no_video_route() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', "/" . $videoquanda->cmid);
        $this->assertEquals(404, $client->getResponse()->getStatusCode());
        $this->assertNotRegExp('/<div class="source" data-src=".*video.mp4" data-video-type="mp4">/', $client->getResponse()->getContent());
    }

    /**
     * tests route to non existing file (not found or not exist)
     */
    public function test_non_existing_file_route() {
        global $DB;

        // Create user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('GET', '/file/' . $videoquanda->id . '/video.mp4');

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
        $this->assertContains(get_string('storedfilecannotread', 'error'), $client->getResponse()->getContent());
    }

    /**
     * test file route for file type that is not valid
     */
    public function test_file_wrong_file_type_route() {
        global $DB;

        // Create user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('GET', '/file/' . $videoquanda->id . '/video.mov');

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
        $this->assertContains(get_string('storedfilecannotread', 'error'), $client->getResponse()->getContent());
    }

    /**
     * tests a route to an existing file
     */
    public function test_file_route() {
        global $DB, $CFG;

        // Create user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        check_dir_exists($this->_upload_path . '/' . $videoquanda->id);
        copy(__DIR__ . '/video/video.mp4', $this->_upload_path . '/' . $videoquanda->id .'/video.mp4');

        $client = new Client($this->_app);
        $client->request('GET', '/file/' . $videoquanda->id . '/video.mp4');

        $this->assertEquals('video/mp4', $client->getResponse()->headers->get('Content-Type'));
        $this->assertFileExists($this->_upload_path . '/' . $videoquanda->id . '/video.mp4');
    }

}
