<?php

use Functional as F;

defined('MOODLE_INTERNAL') || die();

class mod_videoquanda_services_test extends advanced_testcase {

    /**
     * @var Silex\Application
     */
    protected $_app;

    /**
     * setUp
     */
    public function setUp() {
        $this->_app = new Silex\Application();
        F\each([
            'get_groupmode',
        ], function ($srv) {
            $app = $this->_app;
            require __DIR__ . '/../services/' . $srv . '.php';
        });
        $this->resetAfterTest();
    }

    /**
     * tests default group mode is no groups
     */
    public function test_get_groupmode_default() {
        $course = $this->getDataGenerator()->create_course();
        $mod = $this->getDataGenerator()->create_module('videoquanda', [
            'course' => $course->id,
        ]);
        rebuild_course_cache($course->id);
        $groupmode = $this->_app['get_groupmode']($course->id, $mod->cmid);
        $this->assertEquals(NOGROUPS, $groupmode);
    }

    /**
     * tests getting group mode when NOGROUPS just returns NOGROUPS
     */
    public function test_get_groupmode_no_groups() {
        $course = $this->getDataGenerator()->create_course([
            'groupmode'      => NOGROUPS,
            'groupmodeforce' => true,
        ]);
        $mod = $this->getDataGenerator()->create_module('videoquanda', [
            'course' => $course->id,
        ]);
        rebuild_course_cache($course->id);
        $groupmode = $this->_app['get_groupmode']($course->id, $mod->cmid);
        $this->assertEquals(NOGROUPS, $groupmode);
    }

    /**
     * tests getting group mode when VISIBLEGROUPS returns NOGROUPS (because visible groups is not really supported)
     */
    public function test_get_groupmode_visible_groups() {
        $course = $this->getDataGenerator()->create_course([
            'groupmode'      => VISIBLEGROUPS,
            'groupmodeforce' => true,
        ]);
        $mod = $this->getDataGenerator()->create_module('videoquanda', [
            'course' => $course->id,
        ]);
        rebuild_course_cache($course->id);
        $groupmode = $this->_app['get_groupmode']($course->id, $mod->cmid);
        $this->assertEquals(NOGROUPS, $groupmode);
    }

    /**
     * tests getting group mode when SEPARATEGROUPS returns SEPARATEGROUPS
     */
    public function test_get_groupmode_separate_groups() {
        $course = $this->getDataGenerator()->create_course([
            'groupmode'      => SEPARATEGROUPS,
            'groupmodeforce' => true,
        ]);
        $mod = $this->getDataGenerator()->create_module('videoquanda', [
            'course' => $course->id,
        ]);
        rebuild_course_cache($course->id);
        $groupmode = $this->_app['get_groupmode']($course->id, $mod->cmid);
        $this->assertEquals(SEPARATEGROUPS, $groupmode);
    }

}
