<?php

defined('MOODLE_INTERNAL') || die();

$app['has_capability'] = $app->protect(function ($capability, $context) {
    return has_capability($capability, $context);
});
