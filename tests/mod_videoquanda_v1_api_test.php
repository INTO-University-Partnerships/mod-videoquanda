<?php

use Functional as F;

// use the Client and Request classes
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpFoundation\Request;

defined('MOODLE_INTERNAL') || die();

class mod_videoquanda_v1_api_test extends advanced_testcase {

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
     * test post a question
     */
    public function test_post_question() {
        global $DB;

        // Create user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        $question = array(
            'seconds' => '2',
            'text' => 'dummy text'
        );

        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/' . $videoquanda->id . '/questions', array(), array(), array(), json_encode($question));

        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $this->assertEquals(1, $DB->count_records('videoquanda_questions', array('instanceid' => $videoquanda->id)));
    }

    /**
     * tests trying to post a question as a guest is not permitted
     * @global moodle_database $DB
     */
    public function test_post_question_as_guest() {
        global $DB;

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        // set the instance of the 'guest' enrolment plugin to enabled
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, array(
            'courseid' => $course->id,
            'enrol' => 'guest',
        ));

        // login as guest
        $this->setGuestUser();

        // create a dummy question to post
        $question = array(
            'seconds' => '2',
            'text' => 'dummy text'
        );

        // try to post a question
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/' . $videoquanda->id . '/questions', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), json_encode($question));
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(get_string('jsonapi:submitquestionasguestdenied', $this->_app['plugin']), json_decode($client->getResponse()->getContent()));
        $this->assertEquals(0, $DB->count_records('videoquanda_questions', array('instanceid' => $videoquanda->id)));
    }

    /**
     * test for sanitizing posted input
     */
    public function test_post_question_not_allowed_content() {
        global $DB;

        // Create user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        $question = array(
            'seconds' => '2',
            'text' => '<script>alert("This content is not allowed!")</script>'
        );

        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/' . $videoquanda->id . '/questions', array(), array(), array(), json_encode($question));

        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $this->assertEquals(1, $DB->count_records('videoquanda_questions', array('instanceid' => $videoquanda->id)));
        $this->assertEquals('alert("This content is not allowed!")', $DB->get_field('videoquanda_questions', 'text', array('instanceid' => $videoquanda->id)));
    }

    /**
     * tests a VideoQuanda module when the questioner has a sitewide role
     * should return all results
     */
    public function test_all_questions_and_answers_route_when_questioner_has_sitewide_role() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        $this->getDataGenerator()->role_assign($roleid, $user->id);

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, $user->id, $now, $now + 1, 2, 'dummy text')
            )
        )));

        $this->setUser($user);
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $videoquanda->id . '/questions');
        $json = $client->getResponse()->getContent();
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $array = (array) json_decode($json);
        $this->assertCount(1, $array);
    }


    /**
     * tests a VideoQuanda module when the questioner is an admin
     * should return all results
     */
    public function test_all_questions_and_answers_route_when_questioner_is_admin() {
        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrolment
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, 2, $now, $now + 1, 2, 'dummy text'),
                array(2, $videoquanda->id, $user->id, $now, $now + 1, 2, 'dummy text')
            )
        )));

        $this->setAdminUser();
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $videoquanda->id . '/questions');
        $json = $client->getResponse()->getContent();
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $array = (array) json_decode($json);
        $this->assertCount(2, $array);
    }

    /**
     * tests  a VideoQuanda module with no group mode
     */
    public function test_all_questions_and_answers_route_when_not_in_groupmode() {
        global $DB;

        // create a course
        $course = $this->getDataGenerator()->create_course([
            'groupmode'      => NOGROUPS,
            'groupmodeforce' => true,
        ]);

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        // create users and groups
        list($user1a, $user1b, $user2a, $user2b, $user3a) = F\map([1, 2, 3, 4, 5], function ($_) use ($course) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
            return $user;
        });
        list($group1, $group2) = F\map([1, 2], function ($_) use ($course) {
            return $this->getDataGenerator()->create_group([
                'courseid' => $course->id,
            ]);
        });

        // assign group membership
        $this->getDataGenerator()->create_group_member([
            'userid' => $user1a->id,
            'groupid' => $group1->id,
        ]);
        $this->getDataGenerator()->create_group_member([
            'userid' => $user1b->id,
            'groupid' => $group1->id,
        ]);
        $this->getDataGenerator()->create_group_member([
            'userid' => $user2a->id,
            'groupid' => $group2->id,
        ]);
        $this->getDataGenerator()->create_group_member([
            'userid' => $user2b->id,
            'groupid' => $group2->id,
        ]);

        // rebuild course cache
        rebuild_course_cache($course->id);

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, $user1a->id, $now, $now + 1, 2, 'dummy text'),
                array(2, $videoquanda->id, $user2a->id, $now, $now + 1, 2, 'dummy text, question 2')
            ),
            'videoquanda_answers' => array(
                array('questionid', 'userid', 'timecreated', 'timemodified', 'text'),
                array(1, $user1a->id, $now, $now + 1, 'dummy answer 1.'),
                array(1, $user1b->id, $now, $now + 1, 'dummy answer 2.'),
                array(2, $user2a->id, $now, $now + 1, 'dummy answer 1, question 2')
            )
        )));

        // login the user
        $this->setUser($user1a);

        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $videoquanda->id . '/questions');
        $json = $client->getResponse()->getContent();
        $array = json_decode($json);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertJson($json);

        $this->assertEquals(2, $DB->count_records('videoquanda_questions', array('instanceid' => $videoquanda->id)));
        $this->assertEquals(2, $DB->count_records('videoquanda_answers', array('questionid' => 1)));
        $this->assertEquals(1, $DB->count_records('videoquanda_answers', array('questionid' => 2)));

        $this->assertEquals(2, count((array)$array));
        $this->assertEquals(2, count((array)$array->{'1'}->answers));
        $this->assertEquals(1, count((array)$array->{'2'}->answers));
    }

    /**
     * tests  a VideoQuanda module with a group mode of SEPARATEGROUPS
     * should not show all questions and answers
     */
    public function test_all_questions_and_answers_route_when_in_groupmode() {
        global $DB;

        // create a course
        $course = $this->getDataGenerator()->create_course([
            'groupmode'      => SEPARATEGROUPS,
            'groupmodeforce' => true,
        ]);

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        // create users and groups
        list($user1a, $user1b, $user2a, $user2b, $user3a) = F\map([1, 2, 3, 4, 5], function ($_) use ($course) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
            return $user;
        });
        list($group1, $group2) = F\map([1, 2], function ($_) use ($course) {
            return $this->getDataGenerator()->create_group([
                'courseid' => $course->id,
            ]);
        });

        // assign group membership
        $this->getDataGenerator()->create_group_member([
            'userid' => $user1a->id,
            'groupid' => $group1->id,
        ]);
        $this->getDataGenerator()->create_group_member([
            'userid' => $user1b->id,
            'groupid' => $group1->id,
        ]);
        $this->getDataGenerator()->create_group_member([
            'userid' => $user2a->id,
            'groupid' => $group2->id,
        ]);
        $this->getDataGenerator()->create_group_member([
            'userid' => $user2b->id,
            'groupid' => $group2->id,
        ]);

        // rebuild course cache
        rebuild_course_cache($course->id);

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, $user1a->id, $now, $now + 1, 2, 'dummy text'),
                array(2, $videoquanda->id, $user2a->id, $now, $now + 1, 2, 'dummy text, question 2'),
                array(3, $videoquanda->id, $user3a->id, $now, $now + 1, 2, 'dummy text, question 3')
            ),
            'videoquanda_answers' => array(
                array('questionid', 'userid', 'timecreated', 'timemodified', 'text'),
                array(1, $user1a->id, $now, $now + 1, 'dummy answer 1.'),
                array(1, $user1b->id, $now, $now + 1, 'dummy answer 2.'),
                array(2, $user2a->id, $now, $now + 1, 'dummy answer 1, question 2')
            )
        )));

        // login the user
        $this->setUser($user2b);

        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $videoquanda->id . '/questions');
        $json = $client->getResponse()->getContent();
        $array = (array) json_decode($json);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertJson($json);

        $this->assertEquals(3, $DB->count_records('videoquanda_questions', array('instanceid' => $videoquanda->id)));
        $this->assertEquals(2, $DB->count_records('videoquanda_answers', array('questionid' => 1)));
        $this->assertEquals(1, $DB->count_records('videoquanda_answers', array('questionid' => 2)));

        $this->assertEquals(1, count($array));
        $q = current($array);
        $this->assertEquals(2, $q->id);
        $this->assertEquals(1, count($q->answers));

        // test with user 3 (should be able to see their own question only)
        $this->setUser($user3a);
        $client->request('GET', '/api/v1/' . $videoquanda->id . '/questions');
        $array = (array) json_decode($client->getResponse()->getContent());
        $this->assertEquals(1, count($array));
        $q = current($array);
        $this->assertEquals(3, $q->id);
    }

    /**
     * tests a VideoQuanda module when the questioner has a sitewide role, with separate groups
     * should return all results
     */
    public function test_all_questions_and_answers_route_when_in_groupmode_with_sitewide_role() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        $this->getDataGenerator()->role_assign($roleid, $user->id);

        // create a course
        $course = $this->getDataGenerator()->create_course([
            'groupmode' => SEPARATEGROUPS,
            'groupmodeforce' => true,
        ]);

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified', 'seconds', 'text'),
                array(1, $videoquanda->id, $user->id, $now, $now + 1, 2, 'dummy text')
            )
        )));

        $this->setUser($user);
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $videoquanda->id . '/questions');
        $json = $client->getResponse()->getContent();
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $array = (array) json_decode($json);
        $this->assertCount(1, $array);
    }

    /**
     * tests  a VideoQuanda module with a group mode of SEPARATEGROUPS but logged in as admin
     * should return all results
     */
    public function test_all_questions_and_answers_route_when_in_groupmode_as_admin() {
        global $DB;

        // create a course
        $course = $this->getDataGenerator()->create_course([
            'groupmode'      => SEPARATEGROUPS,
            'groupmodeforce' => true,
        ]);

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        // create users and groups
        list($user1a, $user1b, $user2a, $user2b, $user3a) = F\map([1, 2, 3, 4, 5], function ($_) use ($course) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
            return $user;
        });
        list($group1, $group2) = F\map([1, 2], function ($_) use ($course) {
            return $this->getDataGenerator()->create_group([
                'courseid' => $course->id,
            ]);
        });

        // assign group membership
        $this->getDataGenerator()->create_group_member([
            'userid' => $user1a->id,
            'groupid' => $group1->id,
        ]);
        $this->getDataGenerator()->create_group_member([
            'userid' => $user1b->id,
            'groupid' => $group1->id,
        ]);
        $this->getDataGenerator()->create_group_member([
            'userid' => $user2a->id,
            'groupid' => $group2->id,
        ]);
        $this->getDataGenerator()->create_group_member([
            'userid' => $user2b->id,
            'groupid' => $group2->id,
        ]);

        // rebuild course cache
        rebuild_course_cache($course->id);

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, $user1a->id, $now, $now + 1, 2, 'dummy text'),
                array(2, $videoquanda->id, $user2a->id, $now, $now + 1, 2, 'dummy text, question 2'),
                array(3, $videoquanda->id, 2, $now, $now + 1, 2, 'dummy text, question 3') //  created by the admin user
            ),
            'videoquanda_answers' => array(
                array('questionid', 'userid', 'timecreated', 'timemodified', 'text'),
                array(1, $user1a->id, $now, $now + 1, 'dummy answer 1.'),
                array(1, $user1a->id, $now, $now + 1, 'dummy answer 2.'),
                array(2, $user2a->id, $now, $now + 1, 'dummy answer 1, question 2')
            )
        )));

        $this->setAdminUser();

        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $videoquanda->id . '/questions');
        $json = $client->getResponse()->getContent();
        $array = json_decode($json);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertJson($json);

        $this->assertEquals(3, $DB->count_records('videoquanda_questions', array('instanceid' => $videoquanda->id)));
        $this->assertEquals(2, $DB->count_records('videoquanda_answers', array('questionid' => 1)));
        $this->assertEquals(1, $DB->count_records('videoquanda_answers', array('questionid' => 2)));

        $this->assertEquals(3, count((array)$array));
        $this->assertEquals(2, count((array)$array->{'1'}->answers));
        $this->assertEquals(1, count((array)$array->{'2'}->answers));
    }

    /**
     * test to get all questions for specific time
     */
    /*public function test_all_questions_for_given_seconds() {
        global $DB;

        // Create user
        $user = $this->getDataGenerator()->create_user(array(
            'firstname' => 'Test',
            'lastname' => 'User'
        ));

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $time = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array($videoquanda->id, $user->id, $time, $time, 10, 'dummy text'),
                array($videoquanda->id, $user->id, $time, $time, 10, 'second question')
            )
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $videoquanda->id . '/questions/10');

        $expectedJSON = json_encode(array(
            '1' => array(
                'id' => '1',
                'userid' => '3',
                'timecreated' => "$time",
                'timemodified' => "$time",
                'seconds' => '10',
                'text' => 'dummy text',
                'no_of_answers' => '0',
                'username' => 'Test User'
            ),
            '2' => array(
                'id' => '2',
                'userid' => '3',
                'timecreated' => "$time",
                'timemodified' => "$time",
                'seconds' => '10',
                'text' => 'second question',
                'no_of_answers' => '0',
                'username' => 'Test User'
            )
        ));

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('content-type'));
        $this->assertEquals(2, $DB->count_records('videoquanda_questions', array('instanceid' => $videoquanda->id)));
        $this->assertJsonStringEqualsJsonString($expectedJSON, $client->getResponse()->getContent());
    }*/

    /**
     * test to change a non existing question
     */
    public function test_changing_non_existing_question() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $question = array(
            'text' => 'I have updated my question.'
        );

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/' . $videoquanda->id . '/questions/2', array(), array(), array(), json_encode($question));

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
        $this->assertEquals(0, $DB->count_records('videoquanda_questions', array('instanceid' => $videoquanda->id)));
    }

    /**
     * test changing existing question
     */
    public function test_changing_question() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $time = mktime(9, 0, 0, 11, 7, 2013);

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, $user->id, $time, $time, 2, 'dummy text')
            )
        )));

        $question = array(
            'text' => 'I have updated my question.',
            'timemodified' => time()
        );

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/' . $videoquanda->id . '/questions/1', array(), array(), array(), json_encode($question));

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertGreaterThan($time, $DB->get_field('videoquanda_questions', 'timemodified', array('id' => 1)));
    }

    /**
     * test changing question from other user
     */
    public function test_changing_question_from_other_user() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $time = mktime(9, 0, 0, 11, 7, 2013);

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, 99, $time, $time, 2, 'dummy text')
            )
        )));

        $question = array(
            'text' => 'I have updated my question.',
            'timemodified' => time()
        );

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/' . $videoquanda->id . '/questions/1', array(), array(), array(), json_encode($question));

        $this->assertEquals(405, $client->getResponse()->getStatusCode());
        $this->assertEquals($time, $DB->get_field('videoquanda_questions', 'timemodified', array('id' => 1)));
    }

    // Test change question where question already has answers? @todo: Is this necessary ?

    /**
     * test deleting a non existing question
     */
    public function test_deleting_non_existing_question() {
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

        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/' . $videoquanda->id . '/questions/999');

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
        $this->assertFalse($DB->record_exists('videoquanda_questions', array('instanceid' => $videoquanda->id)));
    }

    /**
     * test deleting a question
     */
    public function test_deleting_question() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, $user->id, $now, $now + 1, 2, 'dummy text')
            )
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/' . $videoquanda->id . '/questions/1');

        $this->assertEquals(204, $client->getResponse()->getStatusCode());
        $this->assertFalse($DB->record_exists('videoquanda_questions', array('instanceid' => $videoquanda->id)));
    }

    /**
     * test deleting question where question has answers (so should not be able to delete)
     */
    public function test_deleting_question_with_answers() {
        global $DB;

        // Create user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, 1, $now, $now + 1, 2, 'dummy text')
            ),
            'videoquanda_answers' => array(
                array('id', 'questionid', 'userid', 'timecreated', 'timemodified', 'text'),
                array(1, 1, 1, $now, $now + 1, 'dummy answer.')
            )
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/' . $videoquanda->id . '/questions/1');

        $this->assertEquals(405, $client->getResponse()->getStatusCode());
    }

    /**
     * test deleting a question that is owned by another user
     */
    public function test_deleting_question_from_other_user() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, 99, $now, $now + 1, 2, 'dummy text')
            )
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/' . $videoquanda->id . '/questions/1');

        $this->assertEquals(405, $client->getResponse()->getStatusCode());
        $this->assertEquals(1, $DB->count_records('videoquanda_questions', array('instanceid' => $videoquanda->id)));
    }

    /**
     * test to get all answers for a specific question
     */
    /*public function test_all_answers_for_question() {
        global $DB;

        // Create user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array($videoquanda->id, 1, $now, $now + 1, 2, 'dummy text')
            ),
            'videoquanda_answers' => array(
                array('questionid', 'userid', 'timecreated', 'timemodified', 'text'),
                array(1, 1, $now, $now + 1, 'dummy answer 1.'),
                array(1, 1, $now, $now + 1, 'dummy answer 2.'),
                array(1, 1, $now, $now + 1, 'dummy answer 3.')
            )
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $videoquanda->id . '/questions/1/answers');

        $this->assertEquals(3, $DB->count_records('videoquanda_answers', array('questionid' => 1)));
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }*/

    /**
     * test posting a question
     */
    public function test_post_answer() {
        global $DB;

        // Create user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, 1, $now, $now, 2, 'dummy text')
            )
        )));

        $answer = array(
            'text' => 'This is a new answer.'
        );

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/' . $videoquanda->id . '/questions/1/answers', array(), array(), array(), json_encode($answer));

        $this->assertEquals('201', $client->getResponse()->getStatusCode());
        $this->assertEquals(1, $DB->count_records('videoquanda_answers', array('questionid' => '1')));
    }

    /**
     * tests trying to post an answer as a guest is not permitted
     * @global moodle_database $DB
     */
    public function test_post_answer_as_guest() {
        global $DB;

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        // set the instance of the 'guest' enrolment plugin to enabled
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, array(
            'courseid' => $course->id,
            'enrol' => 'guest',
        ));

        $now = time();
        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array($videoquanda->id, 1, $now, $now, 2, 'dummy text')
            )
        )));

        // login as guest
        $this->setGuestUser();

        // create a dummy answer to post
        $answer = array(
            'text' => 'This is a new answer.'
        );

        // try to post an answer
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/' . $videoquanda->id . '/questions/1/answers', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), json_encode($answer));
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertEquals(get_string('jsonapi:submitanswerasguestdenied', $this->_app['plugin']), json_decode($client->getResponse()->getContent()));
        $this->assertEquals(0, $DB->count_records('videoquanda_answers'));
    }

    /**
     * test changing non existing answer
     */
    public function test_changing_non_existing_answer() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $time = mktime(9, 0, 0, 11, 7, 2013);

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, $user->id, $time, $time, 2, 'dummy text')
            ),
            'videoquanda_answers' => array(
                array('id', 'questionid', 'userid', 'timecreated', 'timemodified', 'text'),
                array(1, 1, $user->id, $time, $time, 'dummy answer 1.')
            )
        )));

        $answer = array(
            'text' => 'I have updated my answer.',
            'timemodified' => time()
        );

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/' . $videoquanda->id . '/questions/1/answers/2', array(), array(), array(), json_encode($answer));

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
        $this->assertEquals($time, $DB->get_field('videoquanda_answers', 'timemodified', array('id' => 1)));
    }

    /**
     * test changing answer
     */
    public function test_changing_answer() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $time = mktime(9, 0, 0, 11, 7, 2013);

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, $user->id, $time, $time, 2, 'dummy text')
            ),
            'videoquanda_answers' => array(
                array('id', 'questionid', 'userid', 'timecreated', 'timemodified', 'text'),
                array(1, 1, $user->id, $time, $time, 'dummy answer 1.')
            )
        )));

        $answer = array(
            'text' => 'I have updated my answer.',
            'timemodified' => time()
        );

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/' . $videoquanda->id . '/questions/1/answers/1', array(), array(), array(), json_encode($answer));

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertGreaterThan($time, $DB->get_field('videoquanda_answers', 'timemodified', array('id' => 1)));
    }

    /**
     * test changing answer from other user
     */
    public function test_changing_answer_from_other_user() {
        global $DB;

        // create user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $time = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, 99, $time, $time, 2, 'dummy text')
            ),
            'videoquanda_answers' => array(
                array('id', 'questionid', 'userid', 'timecreated', 'timemodified', 'text'),
                array(1, 1, 1, $time, $time, 'dummy answer 1.')
            )
        )));

        $answer = array(
            'text' => 'I have updated my answer.',
            'timemodified' => time()
        );

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/' . $videoquanda->id . '/questions/1/answers/1', array(), array(), array(), json_encode($answer));

        $this->assertEquals(405, $client->getResponse()->getStatusCode());
        $this->assertEquals($time, $DB->get_field('videoquanda_answers', 'timemodified', array('id' => 1)));;

    }

    /**
     * test for deleting answer
     */
    public function test_deleting_answer() {
        global $DB;

        // Create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, 1, $now, $now + 1, 2, 'dummy text')
            ),
            'videoquanda_answers' => array(
                array('id', 'questionid', 'userid', 'timecreated', 'timemodified', 'text'),
                array(1, 1, $user->id, $now, $now + 1, 'dummy answer 1.')
            )
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/' . $videoquanda->id . '/questions/1/answers/1');

        $this->assertEquals(204, $client->getResponse()->getStatusCode());
        $this->assertFalse($DB->record_exists('videoquanda_answers', array('questionid' => 1)));
    }

    // Test delete answer that is owned by another user
    public function test_deleting_answer_from_other_user() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $videoquanda = $this->getDataGenerator()->create_module('videoquanda', array(
            'course' => $course->id
        ));

        $now = time();

        $this->loadDataSet($this->createArrayDataSet(array(
            'videoquanda_questions' => array(
                array('id', 'instanceid', 'userid', 'timecreated', 'timemodified' ,'seconds', 'text'),
                array(1, $videoquanda->id, 1, $now, $now + 1, 2, 'dummy text')
            ),
            'videoquanda_answers' => array(
                array('id', 'questionid', 'userid', 'timecreated', 'timemodified', 'text'),
                array(1, 1, 99, $now, $now + 1, 'dummy answer 1.')
            )
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        $this->setUser($user);

        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/' . $videoquanda->id . '/questions/1/answers/1');

        $this->assertEquals(1, $DB->count_records('videoquanda_questions', array('instanceid' => $videoquanda->id)));
        $this->assertEquals(1, $DB->count_records('videoquanda_answers', array('questionid' => 1)));
        $this->assertEquals(405, $client->getResponse()->getStatusCode());
    }

}
