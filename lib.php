<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/externallib.php');

// Función para extender la navegación del curso
function local_asistencia_extend_navigation_course($navigation, $course, $context)
{
    if (!has_capability('local/asistencia:view', $context)) {
        return;
    }
    // Obtener la URL
    $url = new moodle_url('/local/asistencia/index.php', ['courseid' => $course->id]);
    // Agregar el nodo de navegación
    $navigation->add(
        get_string('pluginname', 'local_asistencia'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_asistencia'
    );
}
// Función para construir los breadcrumbs
function local_asistencia_build_breadcrumbs(int $courseid, string $pagekey, array $pagelink = [])
{
    global $PAGE;
    // "Asistencia" → index.php
    $PAGE->navbar->add(
        get_string('pluginname','local_asistencia'),
        new moodle_url('/local/asistencia/index.php',['courseid'=>$courseid])
    );
    // "Menú asistencia" → index.php
    $PAGE->navbar->add(
        get_string('menuasistencia','local_asistencia'),
        new moodle_url('/local/asistencia/index.php',['courseid'=>$courseid])
    );
    // Último nivel (sin link si $pagelink vacío)
    if ($pagelink) {
        $PAGE->navbar->add(
            get_string($pagekey,'local_asistencia'),
            new moodle_url($pagelink['url'], $pagelink['params'])
        );
    } else {
        $PAGE->navbar->add(get_string($pagekey,'local_asistencia'));
    }
}