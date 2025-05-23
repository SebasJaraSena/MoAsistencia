<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$search = optional_param('search', '', PARAM_TEXT);
$courseid = required_param('courseid', PARAM_INT);
$context = context_course::instance($courseid);
require_capability('local/asistencia:view', $context);

require_once($CFG->dirroot . '/local/asistencia/classes/util/history_report_downloader.php');

$downloader = new \local_asistencia\util\history_report_downloader(null, $search);
$downloader->generate_and_download();

