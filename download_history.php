<?php
require_once(__DIR__ . '/../../config.php');
require_login();
// Obtener el parámetro de búsqueda
$search = optional_param('search', '', PARAM_TEXT);
// Obtener el ID del curso
$courseid = required_param('courseid', PARAM_INT);
// Obtene el rango de fechas 
$startdate = optional_param('startdate', '', PARAM_TEXT);
$enddate = optional_param('enddate', '', PARAM_TEXT);


// Obtener el contexto del curso
$context = context_course::instance($courseid);
// Verificar la capacidad de ver
require_capability('local/asistencia:view', $context);
// Cargar el archivo de utilidad
require_once($CFG->dirroot . '/local/asistencia/classes/util/history_report_downloader.php');
// Crear el objeto de descarga          
$downloader = new \local_asistencia\util\history_report_downloader(null, $search, $startdate, $enddate);
// Generar y descargar el reporte
$downloader->generate_and_download();

