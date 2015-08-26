<?php

use Symfony\Component\HttpFoundation\Response;

// bootstrap Moodle
require_once __DIR__ . '/../../config.php';
global $CFG, $FULLME;

// fix $FULLME
$FULLME = str_replace($CFG->wwwroot, $CFG->wwwroot . SLUG, $FULLME);

// create Silex app
require_once __DIR__ . '/../../vendor/autoload.php';
$app = new Silex\Application();
$app['debug'] = debugging('', DEBUG_MINIMAL);

// enable Twig service provider
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/templates',
    'twig.options' => array(
        'cache' => empty($CFG->disable_twig_cache) ? "{$CFG->dataroot}/twig_cache" : false,
        'auto_reload' => debugging('', DEBUG_MINIMAL),
    ),
));

// enable validator, form, url generator and translation service providers
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\FormServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'translator.messages' => array(),
));

$app->register(new Silex\Provider\SessionServiceProvider());
$app['session.storage.handler'] = null;

// enable URL generator service provider
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// set Twig constants
$app['twig']->addGlobal('plugin', 'mod_videoquanda');
$app['twig']->addGlobal('wwwroot', $CFG->wwwroot);
$app['twig']->addGlobal('slug', SLUG);

// require Twig library functions
require __DIR__ . '/twiglib.php';

// module settings
$app['plugin'] = 'mod_videoquanda';
$app['module_table'] = 'videoquanda';
$app['accepted_file_types'] = array(
    'mp4' => array('accept' => array('video/mp4', 'application/octet-stream')),
    'ogv' => array('accept' => array('video/ogg', 'application/ogg')),
    'webm' => array('accept' => array('video/webm', 'application/octet-stream')),
);

// require the services
foreach (array(
    'course_module_viewed',
    'course_module_instance_list_viewed',
    'get_course_and_course_module',
    'get_groupmode',
    'has_capability',
    'require_course_login'
) as $service) {
    require __DIR__ . '/services/' . $service . '.php';
}

// mount the controllers
foreach (array(
    'instances' => 'instances',
    'view' => '',
    'manage' => '',
    'v1_api' => 'api/v1',
) as $controller => $mount_point) {
    $app->mount('/' . $mount_point, require __DIR__ . '/controllers/' . $controller . '.php');
}

// handle errors
$app->error(function(Exception $e, $code) use ($app) {
    global $PAGE;

    // ensure context is at least set to context_system
    $PAGE->set_context(null);
    return new Response($app['twig']->render('error.twig', array(
        'message' => $e->getMessage(),
    )), $code);
});

// return the app
return $app;
