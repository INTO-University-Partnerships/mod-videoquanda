<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../../course/moodleform_mod.php';

class mod_videoquanda_mod_form extends moodleform_mod {

    /**
     * definition
     */
    protected function definition() {
        global $CFG, $COURSE, $DB;

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        // name
        $mform->addElement('text', 'name', get_string('videoquandaname', 'videoquanda'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // only show on edit (never on add)
        if ($this->context instanceof context_module) {
            $mform->addElement('html', '<div class="fitem"><div class="felement">');
            $mform->addElement('html', '<a href="' . $CFG->wwwroot . '/videoquanda/manage/' . $this->context->instanceid . '">' . get_string('attachment', 'videoquanda') . '</a>');
            $mform->addElement('html', '</div></div>');
        }

        $mform->addElement('editor', 'header', get_string('header', 'videoquanda'), array('context' => null, 'maxfiles' => 0));
        $mform->setType('header', PARAM_RAW);
        $mform->addElement('editor', 'footer', get_string('footer', 'videoquanda'), array('context' => null, 'maxfiles' => 0));
        $mform->setType('footer', PARAM_RAW);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();

    }

    function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        if (!empty($default_values['header'])) {
            $temp = $default_values['header'];
            $default_values['header'] = '';
            $default_values['header']['text'] = $temp;
        }

        if (!empty($default_values['footer'])) {
            $temp = $default_values['footer'];
            $default_values['footer'] = '';
            $default_values['footer']['text'] = $temp;
        }

    }

}
