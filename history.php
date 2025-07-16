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

// Creacion de cache
$cache = cache::make('local_asistencia', 'coursestudentslist');
$userid = $USER->id;
$attendancepage = $_GET['page'] ?? 1;
$courseid = $_GET['courseid'];
$limit = $_GET['limit'] ?? 1;

$context = context_course::instance($courseid);

$params = [
    'courseid' => $courseid,
    'page' => $attendancepage,
    'limit' => $limit,
    'initial' => $_GET['initial'] ?? null,
    'final' => $_GET['final'] ?? null,
    'range' => $_GET['range'] ?? null
];

// Parámetros y URL actual
$currenturl = new moodle_url('/local/asistencia/history.php', array_filter($params));
$dircomplement = explode("/", $currenturl->get_path());
$PAGE->set_url($currenturl);
$PAGE->set_context($context);

//  Carga el curso ANTES de modificar el navbar
$PAGE->set_course(get_course($courseid));

// Verificar permisos
require_capability('local/asistencia:view', $context);

// Construye el breadcrumb completo
local_asistencia_build_breadcrumbs($courseid, 'historial');

//  Título y heading
$PAGE->set_title('Históricos Asistencia');
$PAGE->set_heading(get_course($courseid)->fullname);

//  JS y CSS
$PAGE->requires->js_call_amd('local_asistencia/attendance_observations', 'init');
$PAGE->requires->css(new moodle_url('/local/asistencia/styles/styles.css', array('v' => time())));



// Functions
function studentsFormatMonth($studentslist, $month, $cachehistoryattendance, $userid, $dbtablefieldname, $initialdate, $finaldate, $comulus = 1)
{ // Función que formatea la información historica de asistencia por aprendiz en un rango de fechas [x-y]
    $dateaux = date_create($initialdate);
    for ($i = 0; $i < count($studentslist); $i++) {
        $studentid = $studentslist[$i]['id'];

        $filtered = !empty($cachehistoryattendance) ? array_filter($cachehistoryattendance, function ($item) use ($studentid) { // Se filtra información relacionada al aprendiz
            return $item['student_id'] == $studentid;
        }) : [];

        $arrayopts = [0 => "Incumplimiento injustificado", 1 => "Asistió", 2 => "Inasistencia no programada", 3 => "Inasistencia programada", -8 => "NA"]; // Se define el significado de los valores guardados en la DB externa
        $studentslist[$i]['month'] = $month;
        //iniciar op
        foreach ($studentslist[$i]['month'] as $key => $day) {
            $studentslist[$i]['month'][$key]['selection'] = [
                'op' => 0,
                'time' => 0
            ];
        }

        if (!empty($cachehistoryattendance)) {

            $jsonattendance = [];
            $firstKey = array_key_first($filtered);
            if ($firstKey !== null && isset($cachehistoryattendance[$firstKey][$dbtablefieldname])) {
                $jsonStr = $cachehistoryattendance[$firstKey][$dbtablefieldname];
                if (!empty($jsonStr)) {
                    $jsonattendance = json_decode($jsonStr, true);
                }
            }

            if ($comulus) {
                $filtereddates = !empty($jsonattendance) ? array_filter($jsonattendance, function ($item) use ($initialdate, $finaldate) { // Se filtran las asistencias que están dentro del rango de fechas
                    return ($item['DATE'] >= $initialdate) && ($item['DATE'] <= $finaldate);
                }) : [];
                foreach ($filtereddates as $ja) { // Se recorre cada uno de los datos previamente filtrados
                    $jadate = DateTime::createFromFormat('Y-m-d', $ja["DATE"]);
                    $index = $jadate->diff($dateaux)->days;
                    if (!isset($studentslist[$i]['month'][$index]['selection']['details'])) {
                        $studentslist[$i]['month'][$index]['selection']['details'] = [];
                    }

                    // Guardar cada entrada como texto separado
                    $attendance = isset($ja['ATTENDANCE']) && $ja['ATTENDANCE'] !== "-1" ? $arrayopts[$ja['ATTENDANCE']] : "SUSPENDIDO";
                    $hours = isset($ja['AMOUNTHOURS']) ? (int) $ja['AMOUNTHOURS'] : 0;
                    $studentslist[$i]['month'][$index]['selection']['details'][] = "$attendance - Horas: $hours";

                    // Para vista rápida, puedes contar total horas
                    $studentslist[$i]['month'][$index]['selection']["op"] += $hours;
                    $studentslist[$i]['month'][$index]['current'] = 0;

                }
            } else {
                $filteredteacher = !empty($jsonattendance) ? array_filter($jsonattendance, function ($item) use ($userid, $initialdate, $finaldate) { // Se filtran las asistencias relacionadas al instructor que está visualizando los históricos
                    return ($item['TEACHER_ID'] == $userid) && ($item['DATE'] >= $initialdate) && ($item['DATE'] <= $finaldate);
                }) : [];

                foreach ($filteredteacher as $ja) { // Se recorre cada una de las asistencias previamente filtradas
                    $jadate = DateTime::createFromFormat('Y-m-d', $ja["DATE"]);
                    $index = $jadate->diff($dateaux)->days;
                    $studentslist[$i]['month'][$index]['selection']["op"] = isset($ja['ATTENDANCE']) && $ja['ATTENDANCE'] != "-1" ? $arrayopts[$ja['ATTENDANCE']] : "SUSPENDIDO";
                    $studentslist[$i]['month'][$index]['selection']["time"] = isset($ja['AMOUNTHOURS']) ? (int) $ja['AMOUNTHOURS'] : 0;
                    $studentslist[$i]['month'][$index]['current'] = 0;
                }
            }
        }

    }
    return $studentslist;
}

function getWeekRange($initialdate, $finaldate)
{ // Función que establece el rango de días que se mostrarían en el encabezado de la tabla
    $week = ['Monday' => 'L', 'Tuesday' => 'M', 'Wednesday' => 'X', 'Thursday' => 'J', 'Friday' => 'V', 'Saturday' => 'S', 'Sunday' => 'D']; // Se define traductor de días
    $fullmonth = [];
    // Modificar la fecha al inicio de la semana (lunes)
    $firstday = DateTime::createFromFormat('Y-m-d', $initialdate); // Obtener la fecha inicial
    $lastday = DateTime::createFromFormat('Y-m-d', $finaldate); // 
    $aux = $lastday->diff($firstday)->days;
    for ($i = 0; $i <= (int) $aux; $i++) { // Se crea rango de fechas que va a ser mostrado en el header de la tabla
        if ($i !== 0) {
            $firstday->modify('+1 day');
        }
        $fullmonth[] = ['day' => $week[$firstday->format('l')], 'date' => $firstday->format('d/m'), 'current' => date('d/m') == $firstday->format('d/m') ? 0 : 1];
    }
    // Formatee las fechas según su formato preferido
    return $fullmonth;
}

$dbtablefieldname = "full_attendance";
$historyattendance = $DB->get_records('local_asistencia_permanente', ['course_id' => $courseid]); // Se consulta todo el histórico relacionado al curso

// Guardar el histórico en la caché
$cache->set("H_$courseid", json_encode($historyattendance));
$cachehistoryattendance = json_decode($cache->get("H_$courseid"), true);

// Paginador
$pageurl = $attendancepage - 1 ?? 0;
$currentpage = $pageurl + 1;
$postfilter;
$date = new DateTime();
$initialdate = clone $date;
$finaldate = clone $date;

// Filtros
$day = $_GET['day'] ?? 0;
$week = $_GET['week'] ?? 0;
$range_dates = $_GET['range_dates'] ?? 0;

// Validar si las fechas son válidas
if (isset($_GET['initial']) && isset($_GET['final']) && ($day || $week || $range_dates)) {
    $inital = (int) $_GET['initial'] <= strtotime($initialdate->format(('Y-m-d'))) / 100;
    $final = ((int) $_GET['final'] < strtotime($finaldate->modify('last day of')->format(('Y-m-d'))) / 100) || (int) $_GET['final'] < strtotime($finaldate->modify('next sunday')->format(('Y-m-d'))) / 100;
    [$day, $week, $range_dates] = ($inital && $final) ? [$day, $week, $range_dates] : 0;
}

// Validaciones seguras
$inital = isset($_GET['initial']) && is_numeric($_GET['initial'])
    ? ((int) $_GET['initial'] <= strtotime($initialdate->format('Y-m-d')) / 100)
    : false;

// Validar si la fecha final es válida
$final = isset($_GET['final']) && is_numeric($_GET['final'])
    ? (
        ((int) $_GET['final'] < strtotime($finaldate->modify('last day of')->format('Y-m-d')) / 100)
        || ((int) $_GET['final'] < strtotime($finaldate->modify('next sunday')->format('Y-m-d')) / 100)
    )
    : false;

// Restablecer flags si la fecha no es válida
if (!(isset($_GET['initial']) && isset($_GET['final']) && ($day || $week || $range_dates))) {
    $day = 0;
    $week = 0;
    $range_dates = 0;
}

// Aplicar fechas       
if ($inital && isset($_GET['initial'])) {
    $initialdate = (new DateTime())->setTimestamp((int) $_GET['initial'] * 100);
} else {
    $initialdate->modify('first day of');
}

// Aplicar fechas
if ($final && isset($_GET['final'])) {
    $finaldate = (new DateTime())->setTimestamp((int) $_GET['final'] * 100);
} else {
    $finaldate->modify('last day of');
}

// Inicializar variables
$op = 1;
$postfilter;

$filter_method = $_GET['filtro_fecha'] ?? ($_POST['filtro_fecha'] ?? null);
// Aplicar rango de fechas
if ($filter_method === 'range_dates') {
    $day = 0;
    $week = 0;
    $range_dates = 1;
    $op = 0;
    $initialdate = isset($_GET['initial']) ? date_create($_GET['initial']) : $initialdate;
    $finaldate = isset($_GET['final']) ? date_create($_GET['final']) : $finaldate;
} elseif ($filter_method === 'week') {
    $op = 0;
    $day = 0;
    $week = 1;
    $range_dates = 0;

    $date = new DateTime(date('Y-m-d'));
    $dateaux = clone $date;
    $nextsunday = clone $date;
    $dateaux->modify('last monday');
    if ($dateaux->modify('+7 days')->format('Y-m-d') == $date->format('Y-m-d')) {
        $initialdate = clone $date;
    } else {
        $dateaux->modify('last monday');
        $initialdate = clone $dateaux;
    }
    $finaldate = clone $nextsunday->modify('next sunday');
} elseif ($filter_method === 'day') {
    $op = 0;
    $day = 1;
    $week = 0;
    $range_dates = 0;
    $initialdate = new DateTime();
    $finaldate = new DateTime();
}

// Formulario de filtros
$form = new edit();
$condition = '';
// Validar si el formulario se canceló
if ($form->is_cancelled()) {
    $condition = '';
} else if ($fromform = $form->get_data()) {
    $filterarrayoptionstraslate = ['firstname', 'lastname', 'email', 'username'];
    foreach ($fromform as $key => $value) {
        if ($key != 'submitbutton') {
            if ($key == 'filters' && $value >= 0 && $value <= 3) {
                $condition = "AND UPPER(" . $filterarrayoptionstraslate[$value] . ")";
            } elseif ($key == 'filterValue') {
                $value = in_array('email', explode(" ", $condition)) ? $value : strtoupper($value);
                if (!empty($value)) {
                    $condition .= " LIKE '%$value%' ";
                } else {
                    $condition = '';
                }
            }
        }
    }
}
// Obtener el rango de días
$monthrange = getWeekRange($initialdate->format('Y-m-d'), $finaldate->format('Y-m-d'));

// Obtener el curso
$course = get_course($courseid);
// Obtener el nombre corto del curso
$shortname = $course->shortname;

// Mostrar el encabezado
echo $OUTPUT->header();

// Mostrar notificación si no hay información de asistencia
if (optional_param('noinfo', 0, PARAM_INT)) {
    echo $OUTPUT->notification(
        'No hay información de asistencia.',
        \core\output\notification::NOTIFY_ERROR
    );
}
// Obtener el usuario
$userid = $USER->id;
$adminsarray = explode(",", $DB->get_record('config', ['name' => 'siteadmins'])->value);
$configbutton = in_array($userid, $adminsarray) ? 1 : 0;
$pages_attendance_string = $cache->get('attendancelist' . $courseid);
$cache->delete('attendancelist' . $courseid);
$attendance_data = $cache->get($courseid);
// Obtener los datos de asistencia
if (isset($pages_attendance_string)) {
    $search = $_GET['search'] ?? '';

    $studentsdata = local_asistencia_external::fetch_students($context->id, $courseid, 5, 0, 10000, '');
    $studentslist = $studentsdata['students_data'];

    // Aplicar búsqueda si existe
    function normalize_for_search($text)
    {
        // Elimina tildes y convierte a minúsculas
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $text
        );
        $text = preg_replace('/\s+/', ' ', $text); // elimina espacios dobles
        return trim($text);
    }
    // Aplicar búsqueda si existe
    if (!empty($search)) {
        $normalizedSearch = normalize_for_search($search);

        $studentslist = array_filter($studentslist, function ($student) use ($normalizedSearch) {
            $student = is_object($student) ? (array) $student : $student;
            $text = implode(' ', [
                $student['username'] ?? '',
                $student['firstname'] ?? '',
                $student['lastname'] ?? '',
                $student['email'] ?? '',
            ]);

            $normalizedStudentText = normalize_for_search($text);

            return strpos($normalizedStudentText, $normalizedSearch) !== false;
        });
    }

    // ...
    $studentstatus = $_GET['studentstatus'] ?? null;

    // Filtrado por estado
    if ($studentstatus !== null && $studentstatus !== '') {
        $studentslist = array_filter($studentslist, function ($student) use ($studentstatus) {
            return (string) $student['status'] === (string) $studentstatus;
        });
    }

    $comulus = isset($_GET['range']) ? intval($_GET['range']) : 0; 

    // Filtrado por tipo de asistencia
    $attendancefilter = $_GET['attendancefilter'] ?? null;
    if ($attendancefilter !== null && $attendancefilter !== '') {
        $studentslist = array_filter($studentslist, function ($student) use ($attendancefilter, $cachehistoryattendance, $dbtablefieldname, $initialdate, $finaldate, $userid, $comulus) {
            $studentid = $student['id'];
            $filtered = !empty($cachehistoryattendance) ? array_filter($cachehistoryattendance, function ($item) use ($studentid) {
                return $item['student_id'] == $studentid;
            }) : [];

            if (empty($filtered)) {
                return false;
            }

            $firstKey = array_key_first($filtered);
            if ($firstKey === null || !isset($cachehistoryattendance[$firstKey][$dbtablefieldname])) {
                return false;
            }

            $jsonStr = $cachehistoryattendance[$firstKey][$dbtablefieldname];
            if (empty($jsonStr)) {
                return false;
            }

            $jsonattendance = json_decode($jsonStr, true);
            if (empty($jsonattendance)) {
                return false;
            }

            // Filtrar por rango de fechas
            $filtereddates = array_filter($jsonattendance, function ($item) use ($initialdate, $finaldate) {
                return ($item['DATE'] >= $initialdate->format('Y-m-d')) && ($item['DATE'] <= $finaldate->format('Y-m-d'));
            });

            if ($comulus == 0) { // Personal: SOLO asistencias tomadas por el instructor actual
                foreach ($filtereddates as $attendance) {
                    if (
                        isset($attendance['TEACHER_ID']) && $attendance['TEACHER_ID'] == $userid &&
                        isset($attendance['ATTENDANCE']) && (string) $attendance['ATTENDANCE'] === (string) $attendancefilter
                    ) {
                        return true;
                    }
                }
            } else { // Consolidado: buscar en todas las asistencias
                foreach ($filtereddates as $attendance) {
                    if (isset($attendance['ATTENDANCE']) && (string) $attendance['ATTENDANCE'] === (string) $attendancefilter) {
                        return true;
                    }
                }
            }
            return false; // Si no cumple, NO lo muestres
        });
    }

    // Paginación manual
    $totalstudents = count($studentslist);
    $perpage = $limit == 1 ? 10 : 10000;
    $offset = ($attendancepage - 1) * $perpage;
    $pagedstudents = array_slice($studentslist, $offset, $perpage);

    // Generar paginador
    $numpages = ceil($totalstudents / $perpage);
    $pages = [];
    for ($i = 1; $i <= $numpages; $i++) {
        if ($i == 1 || $i == $numpages || abs($i - $attendancepage) <= 2) {
            $pages[] = [
                'page' => $i,
                'current' => $i == $attendancepage,
                'active' => ''
            ];
        } elseif (!empty($pages) && end($pages)['page'] !== '...') {
            $pages[] = ['page' => '...', 'current' => false, 'active' => 'disabled'];
        }
    }

}
// Obtener los datos de asistencia
$raw = $cache->get('attendancelist' . $courseid);
if ($raw !== false && $raw !== null) {
    $pages_attendance_array = json_decode($raw, true);
} else {
    // Sin caché previa, definimos al menos 'pages' para evitar errores
    $pages_attendance_array = ['pages' => 0];
}
// Paginador
for ($page = 1; $page <= $pages_attendance_array['pages']; $page++) { // Paginador
    if ($page === 1) {
        $pages[$page] = [
            'page' => $page,
            'current' => $page == $pageurl + 1,
            'active' => ''
        ];
    }
    if (($page === 2 || $page === $pages_attendance_array['pages'] - 1) && abs($currentpage - $page) >= 3) {
        $pages[$page] = [
            'page' => '...',
            'current' => false,
            'active' => 'disabled'
        ];
    }
    if (abs($page - $currentpage) < 3) {
        $pages[$page] = [
            'page' => $page,
            'current' => $page == $pageurl + 1,
            'active' => ''
        ];
    }
    if ($page === $pages_attendance_array['pages']) {
        $pages[$page] = [
            'page' => $page,
            'current' => $page == $pageurl + 1
        ];
    }
}

// Obtener los datos de asistencia
/* $comulus = isset($_GET['range']) ? intval($_GET['range']) : 0; */
$temporalattendance = array_values($DB->get_records('local_asistencia', ['courseid' => $courseid]));
$students = studentsFormatMonth(
    $pagedstudents,
    $monthrange,
    $cachehistoryattendance,
    $userid,
    $dbtablefieldname,
    $initialdate->format('Y-m-d'),
    $finaldate->format('Y-m-d'),
    $comulus
);
$filtroFecha = $filter_method;
// Obtener los datos de asistencia
$studentslist[$attendancepage] = $students;
//$closeattendance = $studentsamount == count($temporalattendance) ? 0 : 1;
$cache->set('attendancelist' . $courseid, json_encode($studentslist));
$search = optional_param('search', '', PARAM_RAW);
$searchEscaped = rawurlencode($search);
$studentsstring = $cache->get('attendancelist' . $courseid);
$students = json_decode($studentsstring, true);
// Variables para el filtro de asistencia
$attendancefilter = $_GET['attendancefilter'] ?? '';
$templatecontext = (object) [
    'students' => $students[$attendancepage],
    'courseid' => $courseid,
    'teacherid' => $userid,
    'monthheader' => $monthrange,
    'display' => 0,
    'range' => $comulus,
    'listpages' => !empty($pages) ? array_values($pages) : [],
    'currentpage' => $currentpage,
    'initial_value' => $initialdate->format('Y-m-d'),
    'final_value' => $finaldate->format('Y-m-d'),
    'filtro_fecha' => $filtroFecha,
    'isrange1' => (isset($_GET['range']) && $_GET['range'] === '1'),
    'day' => $day,
    'week' => $week,
    'range_dates' => $range_dates,
    'page' => $_GET['page'] ?? 0,
    'config' => $configbutton,
    'limit' => $limit,
    'dirroot' => $dircomplement[1],
    'search' => $search,
    'searchEscaped' => $searchEscaped,
    'studentstatus' => $studentstatus,
    'isstatus0' => $studentstatus === '0',
    'isstatus1' => $studentstatus === '1',
    'isstatusAll' => $studentstatus === '',
    'attendancefilter' => $attendancefilter,
    'isattendanceAll' => $attendancefilter === '',
    'isattendance0' => $attendancefilter === '0',
    'isattendance1' => $attendancefilter === '1',
    'isattendance2' => $attendancefilter === '2',
    'isattendance3' => $attendancefilter === '3',
];

echo $OUTPUT->render_from_template('local_asistencia/history', $templatecontext);
$PAGE->requires->js_call_amd('local_asistencia/attendance_views', 'init');

echo $OUTPUT->footer();