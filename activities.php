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

use block_rss_client\output\item;
use core\plugininfo\local;
use core_calendar\local\event\forms\create;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/edit.php');
require_once(__DIR__ . '/externallib.php');
require_once($CFG->dirroot . '/local/asistencia/lib.php');

require_login();

$courseid = required_param('courseid', PARAM_INT);
$pageurl = optional_param('page', 1, PARAM_INT);
$search = optional_param('search', '', PARAM_RAW);

$params = [
    'courseid' => $courseid,
    'page' => $pageurl
];

$currenturl = new moodle_url('/local/asistencia/activities.php', $params);
$dircomplement = explode("/", $currenturl->get_path());

$context = context_system::instance();
$PAGE->set_url($currenturl);
$PAGE->set_context($context);
$PAGE->set_title('Logs de descargas');
$PAGE->requires->js_call_amd('local_asistencia/attendance_views', 'init');

$PAGE->requires->css(new moodle_url('/local/asistencia/styles/styles.css', ['v' => time()]));

$dbname = $DB->get_record('local_asistencia_config', ['name' => 'dbname'])->value;

try {

    // Se consultan todos los registros en la tabla local_asistencia_logs
    $activities = local_asistencia_external::fetch_activities_report();

    // 🔍 Aplicar filtro si se usa el buscador
    if (!empty($search)) {
        // Normalizar búsqueda: minúsculas, sin espacios múltiples
        $search = strtolower(trim(preg_replace('/\s+/', ' ', $search)));

        $activities = array_filter($activities, function ($activity) use ($search) {
            // Convertir el objeto completo a un array plano si es necesario
            if (is_object($activity)) {
                $activity = (array) $activity;
            }

            // Convertir todo el array a un solo string
            $fulltext = implode(' ', array_map(function ($value) {
                if (is_array($value) || is_object($value)) {
                    return ''; // Ignorar valores complejos
                }
                return $value;
            }, $activity));

            // Normalizar texto: minúsculas, sin saltos ni múltiples espacios
            $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $fulltext)));

            return strpos($normalized, $search) !== false;
        });
    }

    $form = new edit();
    $numpages = (int) ceil(count($activities) / 10);

    local_asistencia_setup_breadcrumb('Logs de descargas ');
    $course = get_course($courseid);
    $shortname = $course->shortname;
    $PAGE->set_heading($shortname);
    echo $OUTPUT->header();
    $currentpage = (int) $pageurl;
    $numpages = (int) ceil(count($activities) / 10);
    $pages = [];

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

    $templatecontext = (object) [
        'activities' => array_slice($activities, ($pageurl - 1) * 10, 10),
        'pages' => array_values($pages) ?? [],
        'dirroot' => $dircomplement[1],
        'courseid' => $courseid,
        'search' => $search
    ];
    echo $OUTPUT->render_from_template('local_asistencia/activities', $templatecontext);

    echo $OUTPUT->footer();
} catch (\Throwable $th) {
    try {
        $toinsert = new stdClass;
        $toinsert->code = "57";
        $toinsert->message = "No se pudo traer información de la base de datos externa $dbname.";
        $toinsert->date = date("Y-m-d H:i:s", time());
        $toinsert->userid = $USER->id;
        $DB->insert_record("local_asistencia_logs", $toinsert);
    } catch (\Throwable $th) {
        //throw $th;
    }
    $adminsarray = explode(",", $DB->get_record("config", ["name" => "siteadmins"])->value);

    $templatecontext = (object) [
        'courseid' => $data['courseid'],
        'config' => in_array($data['userid'], $adminsarray) ? 1 : 0,
        'dirroot' => $dircomplement[array_key_last($dircomplement)],
    ];
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_asistencia/error', $templatecontext);
    throw new \moodle_exception('dbconnectionerror', 'local_asistencia', '', $e->getMessage());

    echo $OUTPUT->footer();
}

