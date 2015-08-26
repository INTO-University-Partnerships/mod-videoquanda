<?php

defined('MOODLE_INTERNAL') || die();

class mod_videoquanda_generator extends testing_module_generator {

    /**
     * create new videoquanda instance
     * @throws coding_exception
     * @param array|stdClass $record
     * @param array $options
     * @return stdClass videoquanda record with extra cmid field
     */
    public function create_instance($record = null, array $options = null) {
        require_once __DIR__ . '/../../lib.php';

        $record = (object)(array)$record;
        $options = (array)$options;

        if (empty($record->course)) {
            throw new coding_exception('module generator requires $record->course');
        }
        if (isset($options['idnumber'])) {
            $record->cmidnumber = $options['idnumber'];
        } else {
            $record->cmidnumber = '';
        }

        return parent::create_instance($record, $options);
    }


}
