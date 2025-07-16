<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Boost.
 *
 * @package    local_asistencia
 * @author     Equipo zajuna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Importar las clases necesarias
use block_rss_client\output\item;
use core\plugininfo\local;
use core_calendar\local\event\forms\create;

// Importar las clases necesarias
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/edit.php');
require_once(__DIR__ . '/externallib.php');
require_once($CFG->dirroot . '/local/asistencia/lib.php');

require_login();

$courseid = required_param('courseid', PARAM_INT);
$pageurl = optional_param('page', 1, PARAM_INT);
$search = optional_param('search', '', PARAM_RAW);
$startdate = $_GET['startdate'] ?? '';
$enddate = $_GET['enddate'] ?? '';

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
$params = [
    'courseid' => $courseid,
    'page' => $pageurl
];

// Parámetros y URL actual
$currenturl = new moodle_url('/local/asistencia/activities.php', $params);
$dircomplement = explode("/", $currenturl->get_path());
$PAGE->set_url($currenturl);
$PAGE->set_context($context);

//  Carga el curso ANTES de modificar el navbar
$PAGE->set_course(get_course($courseid));

// Verificar permisos
require_capability('local/asistencia:view', $context);

// Construye el breadcrumb completo
local_asistencia_build_breadcrumbs($courseid, 'logs_descarga');

//  Título y heading
$PAGE->set_title('Logs de descargas');
$PAGE->set_heading(get_course($courseid)->fullname);

//  JS y CSS
$PAGE->requires->js_call_amd('local_asistencia/attendance_views', 'init');
$PAGE->requires->css(new moodle_url('/local/asistencia/styles/styles.css', ['v' => time()]));

$dbname = $DB->get_record('local_asistencia_config', ['name' => 'dbname'])->value;

try {

    // Se consultan todos los registros en la tabla local_asistencia_logs
    $activities = local_asistencia_external::fetch_activities_report($courseid);

    // 🔍 Aplicar filtro si se usa el buscador
    if (!empty($search)) {
        // Función para normalizar tildes y espacios
        function normalize_for_search($text)
        {
            $text = mb_strtolower($text, 'UTF-8');
            $text = str_replace(
                ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
                ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
                $text
            );
            $text = preg_replace('/\s+/', ' ', $text);
            return trim($text);
        }

        $normalizedSearch = normalize_for_search($search);
        // Filtrar las actividades según el texto de búsqueda
        $activities = array_filter($activities, function ($activity) use ($normalizedSearch) {
            if (is_object($activity)) {
                $activity = (array) $activity;
            }
            // Obtener el texto completo de la actividad
            $fulltext = implode(' ', array_map(function ($value) {
                return is_scalar($value) ? $value : '';
            }, $activity));
            // Normalizar el texto completo de la actividad
            $normalized = normalize_for_search($fulltext);
            // Validar si el texto normalizado contiene el texto de búsqueda
            return strpos($normalized, $normalizedSearch) !== false;
        });
    }

    // 🔍 Aplicar filtro por rango de fechas si se han enviado
    if (!empty($startdate) || !empty($enddate)) {
        $activities = array_filter($activities, function ($activity) use ($startdate, $enddate) {
            if (is_object($activity)) {
                $activity = (array) $activity;
            }

            $logdate = strtotime($activity['date']);
            $from = !empty($startdate) ? strtotime($startdate . ' 00:00:00') : null;
            $to = !empty($enddate) ? strtotime($enddate . ' 23:59:59') : null;

            if ($from && $to) {
                return $logdate >= $from && $logdate <= $to;
            } elseif ($from) {
                return $logdate >= $from;
            } elseif ($to) {
                return $logdate <= $to;
            }
            return true;
        });
    }


    // Crear el formulario de edición
    $form = new edit();
    $numpages = (int) ceil(count($activities) / 10);

    // Obtener el curso
    $course = get_course($courseid);
    $shortname = $course->shortname;

    // Generar el contexto para el template
    echo $OUTPUT->header();
    $currentpage = (int) $pageurl;
    $numpages = (int) ceil(count($activities) / 10);
    $pages = [];
    // Generar las páginas
    for ($page = 1; $page <= $numpages; $page++) {
        if (
            $page == 1 ||
            $page == $numpages ||
            abs($page - $currentpage) <= 2
        ) {
            // Páginas visibles siempre (1, última y ±2 del actual)
            $pages[] = [
                'page' => $page,
                'current' => $page == $currentpage,
                'active' => ''
            ];
        } elseif (
            end($pages)['page'] !== '...'
        ) {
            // Insertar "..." solo una vez entre saltos
            $pages[] = [
                'page' => '...',
                'current' => false,
                'active' => 'disabled'
            ];
        }
    }
    // Generar el contexto para el template
    $templatecontext = (object) [
        'activities' => array_slice($activities, ($pageurl - 1) * 10, 10),
        'pages' => array_values($pages) ?? [],
        'dirroot' => $dircomplement[1],
        'courseid' => $courseid,
        'search' => $search,
        'startdate' => $startdate,
        'enddate' => $enddate

    ];
    // Renderizar el template
    echo $OUTPUT->render_from_template('local_asistencia/activities', $templatecontext);
    // Cerrar el footer
    echo $OUTPUT->footer();
} catch (\Throwable $th) {
    try {
        // Insertar el error en la tabla local_asistencia_logs
        $toinsert = new stdClass;
        $toinsert->code = "57";
        $toinsert->message = "No se pudo traer información de la base de datos externa $dbname.";
        $toinsert->date = date("Y-m-d H:i:s", time());
        $toinsert->userid = $USER->id;
        $DB->insert_record("local_asistencia_logs", $toinsert);
    } catch (\Throwable $th) {
        //throw $th;
    }
    // Obtener los administradores del sitio
    $adminsarray = explode(",", $DB->get_record("config", ["name" => "siteadmins"])->value);
    // Generar el contexto para el template
    $templatecontext = (object) [
        'courseid' => $data['courseid'],
        'config' => in_array($data['userid'], $adminsarray) ? 1 : 0,
        'dirroot' => $dircomplement[array_key_last($dircomplement)],
    ];
    // Renderizar el template
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_asistencia/error', $templatecontext);
    // Lanzar el error
    throw new \moodle_exception('dbconnectionerror', 'local_asistencia', '', $e->getMessage());
    // Cerrar el footer
    echo $OUTPUT->footer();
}

