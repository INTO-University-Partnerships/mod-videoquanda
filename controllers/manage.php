<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;

defined('MOODLE_INTERNAL') || die();

$controller = $app['controllers_factory'];

// manage videos
$controller->match('/manage/{cmid}', function (Request $request, $cmid) use ($app) {
    global $DB, $CFG, $PAGE;

    $course_module = $DB->get_record('course_modules', array('id' => $cmid), '*', MUST_EXIST);

    // Get the course and throw error if it does not exist
    if (!$course = $DB->get_record('course', array('id' => $course_module->course))) {
        throw new moodle_exception('invalidcourseid');
    }

    require_capability('moodle/course:manageactivities', context_system::instance());

    // Add language strings to be used in JS
    $PAGE->requires->strings_for_js(array(
        'confirm_delete'
    ), $app['plugin']);

    // Get current data
    $data = $DB->get_record('videoquanda', array('id' => $course_module->instance, 'course' => $course_module->course), '*', MUST_EXIST);
    if (!empty($data->url)) {
        $videos = explode(';', $data->url);
    }

    // Form + form fields
    $builder = $app['form.factory']->createBuilder('form', array(), array('csrf_protection' => false));

    // Create fields for all accepted video types
    foreach ($app['accepted_file_types'] as $key => $type) {
        $value = "";
        if (!empty($videos) && in_array($key, $videos)) {
            $index = array_search($key, $videos);
            $value = $videos[$index + 1];
        }

        $builder->add($key, 'file', array('required' => 'false','attr' => array('value' => $value)));
    }

    $builder->add('cancel', 'submit');
    $builder->add('submit', 'submit');

    $form = $builder->getForm();

    if ('POST' == $request->getMethod()) {
        $form->bind($request);

        // Validate files (check for mimetype only when not empty)
        $files = $request->files->get($form->getName());
        foreach($app['accepted_file_types'] as $f => $type) {
            if (!empty($files[$f])) {
                $constraint = new Symfony\Component\Validator\Constraints\File(array('mimeTypes' => $type['accept']));
                $error = $app['validator']->validateValue($files[$f], $constraint);

                if ($error) {
                    foreach($error as $e){
                        $form->get($f)->addError(new FormError($e->getMessage()));
                    }
                }
            }
        }

        if ($form->isValid() || $form->get('cancel')->isClicked()) {

            // Check if clicked on 'Submit' and not on 'Cancel';
            if ($form->get('submit')->isClicked()) {

                $data = $form->getData();

                // Array of files to be deleted
                $deleted = $request->get('delete');

                // Where to upload to
                $uploadpath = $CFG->dataroot . '/into/mod_videoquanda/' . $course_module->instance;

                $obj = new stdClass();
                $obj->id = $course_module->instance;
                $obj->url = "";

                // Add $videotype . ';' . $videofile to object to be updated / inserted in database later.
                foreach($app['accepted_file_types'] as $f => $type) {
                    // Upload new added files
                    if (!empty($files[$f])) {
                        $filename  = strtolower($files[$f]->getClientOriginalName());
                        $files[$f]->move($uploadpath, $filename);
                        $obj->url .= $f . ';' . $filename .';';
                        continue;
                    }

                    // If files already been uploaded
                    if (!empty($data[$f])) {
                        $obj->url .= $f . ';' . $data[$f] . ';';
                    }

                    // Delete files
                    if (!empty($deleted[$f]) && file_exists($uploadpath . '/'. $deleted[$f])) {
                        unlink($uploadpath . '/' . $deleted[$f]);
                    }
                }

                // Update or add filename for video in the database
                $DB->update_record('videoquanda', $obj);
            }

            // Redirect back to manage page
            return $app->redirect($CFG->wwwroot . '/course/modedit.php?update=' . $cmid);
        }

    }

    // set heading and title
    $app['heading_and_title'](
        $course->fullname,
        get_string('editinga', 'moodle', get_string('pluginname', $app['plugin']))
    );

    return $app['twig']->render('manage.twig', array(
        'form' => $form->createView(),
        'course' => $course,
        'data' => $data,
        'accepted_file_types' => $app['accepted_file_types']
    ));
})
->assert('cmid', '\d+');

// return the controller
return $controller;