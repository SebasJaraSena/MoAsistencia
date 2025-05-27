<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/externallib.php');

function local_asistencia_extend_navigation_course($navigation, $course, $context) {
    if (!has_capability('local/asistencia:view', $context)) {
        return;
    }

    $url = new moodle_url('/local/asistencia/index.php', ['courseid' => $course->id]);

    $navigation->add(
        get_string('pluginname', 'local_asistencia'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_asistencia'
    );
}
function local_asistencia_setup_breadcrumb($page_title)
{
    global $PAGE, $SESSION;

    // Obtener todos los parámetros de la URL actual
    $params = $_GET;

    //  **Filtrar solo los parámetros esenciales**
    $allowed_params = ['courseid', 'info']; // Agrega aquí los parámetros que sí deben considerarse
    $filtered_params = array_intersect_key($params, array_flip($allowed_params));

    // Construir la URL con los parámetros relevantes
    $currenturl = new moodle_url('/local/asistencia/' . basename($_SERVER['SCRIPT_NAME']), $filtered_params);

    // Inicializar la sesión si no existe
    if (!isset($SESSION->asistencia_breadcrumb)) {
        $SESSION->asistencia_breadcrumb = [];
    }

    //  **Forzar "Asistencia General" como primer elemento si la miga está vacía**
    if (empty($SESSION->asistencia_breadcrumb) && $page_title === "Asistencia General") {
        $SESSION->asistencia_breadcrumb[] = [
            'name' => $page_title,
            'url' => $currenturl->out(false)
        ];
    }

   // **Eliminar duplicados por URL filtrada**
    foreach ($SESSION->asistencia_breadcrumb as $key => $breadcrumb) {
        if ($breadcrumb['url'] === $currenturl->out(false) || $breadcrumb['name'] === $page_title) {
            unset($SESSION->asistencia_breadcrumb[$key]);
        }
    }
    
    // Reindexar array
    $SESSION->asistencia_breadcrumb = array_values($SESSION->asistencia_breadcrumb);

    // Agregar la URL filtrada
    $SESSION->asistencia_breadcrumb[] = [
        'name' => $page_title,
        'url' => $currenturl->out(false)
    ];

    // **Limitar el tamaño de la miga de pan**
    if (count($SESSION->asistencia_breadcrumb) > 2) {
        array_shift($SESSION->asistencia_breadcrumb);
    }

    // **Evitar duplicados automáticos de Moodle**
    $PAGE->navbar->ignore_active();

    // **Eliminar "Asistencia" si es el primer elemento sin URL útil**
    if (!empty($SESSION->asistencia_breadcrumb) && $SESSION->asistencia_breadcrumb[0]['name'] === "Asistencia") {
        array_shift($SESSION->asistencia_breadcrumb);
    }

    // **Agregar rutas filtradas a la miga de pan**
    foreach ($SESSION->asistencia_breadcrumb as $breadcrumb) {
        $PAGE->navbar->add($breadcrumb['name'], new moodle_url($breadcrumb['url']));
    }
}
