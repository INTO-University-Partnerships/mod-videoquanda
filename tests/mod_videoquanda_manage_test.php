<?php

// use the Client and Request classes
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpFoundation\Request;

defined('MOODLE_INTERNAL') || die();

class mod_videoquanda_manage_test extends advanced_testcase {

    /**
     * @var Silex\Application
     */
    protected $_app;

    /**
     * setUp
     */
    public function setUp() {
        if (!defined('SLUG')) {
            define('SLUG', '');
        }
        if (!defined('SILEX_WEB_TEST')) {
            define('SILEX_WEB_TEST', true);
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
        $client->request('GET', '/addvideo/does_not_exist');

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
        $this->assertContains('No route found for &quot;GET /addvideo/does_not_exist&quot;', $client->getResponse()->getContent());
    }

    /**
     * test existing manage video route
     */
    public function test_existing_manage_route_with_capability() {

        $user = $this->getDataGenerator()->create_user();
        role_assign(1, $user->id, context_system::instance());

        $this->setUser($user);

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $client = new Client($this->_app);
        $crawler = $client->request('GET', '/manage/' . $videoquanda->cmid);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals(3, $crawler->filter('input[type="file"]')->count());
    }

    /**
     * test manage route when not logged in.
     */
    public function test_existing_manage_route_without_capability() {

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $client = new Client($this->_app);
        $client->request('GET', '/manage/' . $videoquanda->cmid);

        $this->assertEquals(500, $client->getResponse()->getStatusCode());
    }

    /**
     * test post
     */
    public function test_post_video_via_form() {
        global $CFG;

        $user = $this->getDataGenerator()->create_user();
        role_assign(1, $user->id, context_system::instance());

        $this->setUser($user);

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id,
            'url' => 'mp4;video.mp4;webm;video.mp4'
        ));

        $this->_app['accepted_file_types'] = array(
            'mp4' => array('accept' => array('video/mp4')),
            'ogv' => array('accept' => array('video/ogg', 'application/ogg')),
            'webm' => array('accept' => array('video/webm')),
        );

        $client = new Client($this->_app);
        $crawler = $client->request('GET', '/manage/' . $videoquanda->cmid);
        $this->assertTrue($client->getResponse()->isOk());

        copy(__DIR__ . '/video/video.mp4', '/tmp/video.mp4');

        $uploadedfile = new Symfony\Component\HttpFoundation\File\UploadedFile(
            '/tmp/video.mp4',
            'video.mp4',
            'video/mp4'
        );

        $form = $crawler->selectButton(get_string('save_and_return', 'videoquanda'))->form();
        $client->submit($form, array(
            'form[mp4]' => $uploadedfile
        ));

        $this->assertFileExists('/tmp/video.mp4');
        $this->assertTrue($client->getResponse()->isRedirect($CFG->wwwroot . '/course/modedit.php?update=' . $videoquanda->cmid));
    }

    /**
     * test post wrong videofiles
     */
    public function test_post_wrong_file_types(){
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

        $client = new Client($this->_app);
        $client->request('GET', "/manage/" . $videoquanda->cmid);

        // Todo: Mock videofile?
    }
    // Post video files (test videotypes)
    // Delete video files

}
 