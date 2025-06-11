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

use function PHPSTORM_META\type;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/externallib.php');
require_once($CFG->dirroot . '/local/asistencia/lib.php');

require_login();
global $CFG, $USER, $DB, $SESSION;

// 👇 Primero obtenemos lo esencial del POST (no usaremos $_GET todavía)
$rcourseid = $_POST['courseid'] ?? null;
$attendancepage = isset($_POST['page']) ? (int) trim($_POST['page']) : 1;


// 🔁 Validar si es un POST y procesar antes de cualquier salida
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['attendance'], $_POST['extrainfo'], $_POST['extrainfoNum'], $_POST['courseid'], $_POST['teacherid'])
) {
    $attendances = $_POST['attendance'];
    $infos = $_POST['extrainfo'];
    $hours = $_POST['extrainfoNum'];
    $teacherid = $_POST['teacherid'];

    foreach ($attendances as $studentid => $days) {
        foreach ($days as $date => $attendance) {
            if ($attendance == "-8") {
                continue;
            }

            $observations = $infos[$studentid][$date] ?? '';
            $amountHours = $hours[$studentid][$date] ?? 0;

            $existing = $DB->get_records('local_asistencia', [
                'courseid' => $rcourseid,
                'studentid' => $studentid,
                'teacherid' => $teacherid
            ]);

            $record = null;
            foreach ($existing as $rec) {
                if ($rec->date === $date) {
                    $record = $rec;
                    break;
                }
            }

            if ($record) {
                $record->attendance = $attendance;
                $record->observations = $observations;
                $record->amounthours = $amountHours;
                $DB->update_record('local_asistencia', $record);
            } else {
                $record = new stdClass();
                $record->courseid = $rcourseid;
                $record->studentid = $studentid;
                $record->teacherid = $teacherid;
                $record->date = $date;
                $record->attendance = $attendance;
                $record->observations = $observations;
                $record->amounthours = $amountHours;
                $DB->insert_record('local_asistencia', $record);
            }
        }
    }

    // Guardar permanentemente agrupado por estudiante
    $asistencia = $DB->get_records('local_asistencia', ['courseid' => $rcourseid]);
    $por_estudiante = [];

    foreach ($asistencia as $asis) {
        $key = $asis->date . '_' . $asis->teacherid;
        $por_estudiante[$asis->studentid][$key] = [
            'TEACHER_ID' => $asis->teacherid,
            'ATTENDANCE' => $asis->attendance,
            'DATE' => $asis->date,
            'OBERVATIONS' => $asis->observations,
            'AMOUNTHOURS' => $asis->amounthours
        ];
    }

    foreach ($por_estudiante as $studentid => $records) {
        $result = $DB->get_record('local_asistencia_permanente', [
            'course_id' => $rcourseid,
            'student_id' => $studentid
        ]);

        if ($result) {
            $historial = json_decode($result->full_attendance, true);
            foreach ($records as $newRecord) {
                $existe = false;
                foreach ($historial as &$item) {
                    if ($item['DATE'] === $newRecord['DATE'] && $item['TEACHER_ID'] === $newRecord['TEACHER_ID']) {
                        $item = $newRecord;
                        $existe = true;
                        break;
                    }
                }
                if (!$existe) {
                    $historial[] = $newRecord;
                }
            }
            $result->full_attendance = json_encode($historial);
            $DB->update_record('local_asistencia_permanente', $result);
        } else {
            $nuevo = new stdClass();
            $nuevo->course_id = $rcourseid;
            $nuevo->student_id = $studentid;
            $nuevo->full_attendance = json_encode(array_values($records));
            $DB->insert_record('local_asistencia_permanente', $nuevo);
        }

        // Actualizar caché
        $updated_attendance = $DB->get_records('local_asistencia_permanente', ['course_id' => $rcourseid]);
        $cache = cache::make('local_asistencia', 'coursestudentslist');
        $cache->set("H_$rcourseid", json_encode($updated_attendance));
    }

    $DB->delete_records('local_asistencia', ['courseid' => $rcourseid]);

    // 🔁 Redirigir limpiamente antes de cualquier salida
    $url = new moodle_url('/local/asistencia/attendance.php', [
        'courseid' => $rcourseid,
        'range' => 0,
        'page' => $attendancepage
    ]);

    redirect($url, "Guardando asistencia, redireccionando...", null, \core\output\notification::NOTIFY_SUCCESS);
}

// Creacion de cache
$cache = cache::make('local_asistencia', 'coursestudentslist');
$userid = $USER->id;
$courseid = $_GET['courseid'];
$attendancepage = $_POST['page'] ?? $_GET['page'] ?? 1;
$limit = 25;
$search = trim($_GET['search'] ?? '');

$date = new DateTime(date('Y-m-d'));
$startweek = clone $date;
$endweek = clone $date;

// Si hoy es lunes, usamos la fecha actual, si no, retrocedemos al lunes
$initial = $date->format('l') == 'Monday' ? $startweek->format("Y-m-d") : $startweek->modify("last monday")->format("Y-m-d");
// Si hoy es domingo, usamos la fecha actual, si no, avanzamos al domingo
$final = $date->format('l') == 'Sunday' ? $endweek->format("Y-m-d") : $endweek->modify("next sunday")->format("Y-m-d");

$close = local_asistencia_external::close_validation_retard($courseid, $initial, $final);
$context = context_course::instance($courseid);
$range = optional_param('range', 0, PARAM_INT);

$params = [
    'courseid' => $courseid,
    'page' => $attendancepage,
    'range' => $range,
    'limit' => $limit
];


// Parámetros y URL actual
$currenturl = new moodle_url('/local/asistencia/attendance.php', $params);
$dircomplement = explode("/", $currenturl->get_path());
// 1) Establecer URL y contexto
$PAGE->set_url($currenturl);
$PAGE->set_context($context);

local_asistencia_build_breadcrumbs($courseid, 'asistencia_general');
// 2) Cargar el curso (¡antes de tocar el navbar!)
$PAGE->set_course(get_course($courseid));

// 6) Título de la página
$PAGE->set_title(get_string('asistencia_general', 'local_asistencia'));

// 7) Scripts y estilos
$PAGE->requires->js_call_amd('local_asistencia/attendance_observations', 'init');
$PAGE->requires->css(
    new moodle_url('/local/asistencia/styles/styles.css', ['v' => time()])
);

// 8) Verificar permisos
require_capability('local/asistencia:vergeneral', $context);

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

function studentsFormatWeek($studentslist, $week, $cachehistoryattendance,
                            $userid, $initial, $final, $a, $suspended, $close) {
    global $DB, $courseid;

    $weekdaysnames = [
        'Monday'    => 0,
        'Tuesday'   => 1,
        'Wednesday' => 2,
        'Thursday'  => 3,
        'Friday'    => 4,
        'Saturday'  => 5,
        'Sunday'    => 6
    ];
    $totaldaysattendance = 0;

    foreach ($studentslist as $i => $student) {
        $studentid = $student['id'];
        $studentslist[$i]['week'] = $week;

        // 2. Inicializar la semana con valores por defecto
        for ($j = 0; $j < 7; $j++) {
            $studentslist[$i]['week'][$j]['selection'] = [
                'op-8' => 1, 'op0' => 0, 'op1' => 0,
                'op2'  => 0, 'op3' => 0,
            ];
            $studentslist[$i]['week'][$j]['closed'] = $close;
            $studentslist[$i]['week'][$j]['locked'] = false;
            // opcionalmente también:
            $studentslist[$i]['week'][$j]['missedhours']   = '';
            $studentslist[$i]['week'][$j]['observations']  = '';
        }

        // 3. Obtener las asistencias ya guardadas
        $filtered = array_filter($cachehistoryattendance,
            fn($item) => $item['student_id'] == $studentid
        );
        if (!empty($filtered)) {
            $firstKey       = array_key_first($filtered);
            $jsonattendance = json_decode(
                $cachehistoryattendance[$firstKey]['full_attendance'], true
            ) ?: [];

            $records = array_filter($jsonattendance, function($item)
                use ($initial, $final, $userid) {
                return $item['DATE']   >= $initial
                    && $item['DATE']   <= $final
                    && $item['TEACHER_ID'] == $userid;
            });

            foreach ($records as $record) {
                $date    = DateTime::createFromFormat('Y-m-d', $record['DATE']);
                $index   = $weekdaysnames[$date->format('l')];

                // poner la selección correcta
                foreach (['op-8','op0','op1','op2','op3'] as $op) {
                    $studentslist[$i]['week'][$index]['selection'][$op] = 0;
                }
                $studentslist[$i]['week'][$index]['selection']
                              ['op'.$record['ATTENDANCE']] = 1;

                // cargar horas y observaciones
                $studentslist[$i]['week'][$index]['missedhours']  =
                    $record['AMOUNTHOURS']   ?? '';
                $studentslist[$i]['week'][$index]['observations'] =
                    $record['OBERVATIONS']   ?? '';

                // bloquear esa celda (modo sólo lectura)
                $studentslist[$i]['week'][$index]['locked'] = true;
                $totaldaysattendance++;
            }
        }

        // 4. Si el alumno está suspendido, bloquear todas las celdas
        if ($student['status']) {
            for ($j = 0; $j < 7; $j++) {
                $studentslist[$i]['week'][$j]['locked'] = true;
            }
        }
    }

    return [$studentslist, $totaldaysattendance, $a];
}


function getWeekRange($initial): array
{
    $week = ['Monday' => 'L', 'Tuesday' => 'M', 'Wednesday' => 'X', 'Thursday' => 'J', 'Friday' => 'V', 'Saturday' => 'S', 'Sunday' => 'D'];
    $fullweek = [];
    $date = new DateTime($initial);

    for ($i = 0; $i < 7; $i++) {
        if ($i !== 0) {
            $date->modify('+1 day');
        }
        $fullweek[] = [
            'day' => $week[$date->format('l')],
            'date' => $date->format('d/m'),
            'fulldate' => $date->format('Y-m-d'),
        ];
    }

    return $fullweek;
}


// Se obtiene información guardada en caché
$attendance_data = $cache->get($courseid);
$attendance_info = [];

$historyattendance = $DB->get_records('local_asistencia_permanente', ['course_id' => $courseid]);

$cache->set("H_$courseid", json_encode($historyattendance));
$cachehistoryattendance = json_decode($cache->get("H_$courseid"), true);

$pageurl = $attendancepage - 1 ?? 0;
$currentpage = $pageurl + 1;
$date = new DateTime(date('Y-m-d'));
$weekRange = getWeekRange($initial);

$form = new edit();

$condition = '';

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

$cache->set("course$courseid.user$userid", $condition);


$course = get_course($courseid);
$shortname = $course->shortname;
$PAGE->set_heading($shortname);
echo $OUTPUT->header();
$userid = $USER->id;
$adminsarray = explode(",", $DB->get_record('config', ['name' => 'siteadmins'])->value);
$configbutton = in_array($userid, $adminsarray) ? 1 : 0;
$pages_attendance_string = $cache->get('attendancelist' . $courseid);
$cache->delete('attendancelist' . $courseid);
$attendance_data = $cache->get($courseid);

if (isset($pages_attendance_string)) {
    $pages_attendance_array = json_decode($pages_attendance_string, true);
    $search = trim($_GET['search'] ?? '');

    // Obtener todos los estudiantes sin paginación
    $students = local_asistencia_external::fetch_students($context->id, $courseid, 5, 0, 10000, '');
    $students_data = $students['students_data'];

    // Aplicar búsqueda si existe
    if (!empty($search)) {
        $normalizedSearch = normalize_for_search($search);
        $students_data = array_filter($students_data, function ($student) use ($normalizedSearch) {
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

    // Aplicar paginación después de la búsqueda
    $total_students = count($students_data);
    $students_per_page = $limit;
    $total_pages = ceil($total_students / $students_per_page);
    $offset = ($attendancepage - 1) * $students_per_page;
    $paged_students = array_slice($students_data, $offset, $students_per_page);

    $supended = array_filter($paged_students, function ($item) {
        return $item['status'] == 1;
    });

    $pages_attendance_array['pages'] = $total_pages;
    $pages_attendance_array[$attendancepage] = $paged_students;
    $studentsamount = $total_students;

    if (empty($pages_attendance_array[$attendancepage])) {
        \core\notification::add("No se encontraron aprendices matriculados.", \core\output\notification::NOTIFY_WARNING);
    }

    $pages_attendance_array_copy = $pages_attendance_array;
    $listlimit = count($pages_attendance_array_copy[$attendancepage]);
    $cache->set('attendancelist' . $courseid, json_encode($pages_attendance_array));
    $test = $cache->get('attendancelist' . $courseid);
    $studentslist = json_decode($test, true);
}

// Generar paginador
$pages = [];
for ($page = 1; $page <= $pages_attendance_array['pages']; $page++) {
    if ($page === 1 || $page === $pages_attendance_array['pages'] || abs($page - $attendancepage) <= 3) {
        $pages[$page] = [
            'page' => $page,
            'current' => $page == $attendancepage,
            'active' => ''
        ];
    } elseif (!empty($pages) && end($pages)['page'] !== '...') {
        $pages[] = [
            'page' => '...',
            'current' => false,
            'active' => 'disabled'
        ];
    }
}

$range = isset($_GET['range']) ? $_GET['range'] : 0;
[$initialdate, $finaldate] = [$weekRange[0]['fulldate'], $weekRange[6]['fulldate']];
$sql = "SELECT * FROM {local_asistencia} WHERE courseid = $courseid AND teacherid = $userid AND \"date\" BETWEEN '$initialdate' AND '$finaldate'";
$temporalattendance = array_values($DB->get_records_sql($sql));

[$students, $totaldaysattendance, $a] = studentsFormatWeek($studentslist[$attendancepage] ?? $pages_attendance_array_copy[$attendancepage], $weekRange, $cachehistoryattendance, $userid, $initial, $final, $a, count($supended), $close, $courseid);

$studentslist[$attendancepage] = $students;
$closeattendance = $studentsamount == count($temporalattendance) ? 0 : 1;
$cache->set('attendancelist' . $courseid, json_encode($studentslist));

$studentsstring = $cache->get('attendancelist' . $courseid);
$students = json_decode($studentsstring, true);
$templatecontext = (object) [
    'students' => $students[$attendancepage],
    'courseid' => $courseid,
    'teacherid' => $userid,
    'weekheader' => $weekRange,
    'display' => 0,
    'range' => $range,
    'listpages' => !empty($pages) ? array_values($pages) : [],
    'currentpage' => $attendancepage,
    'close' => $a == (count($students[$attendancepage]) * 7) ? 1 : 0,
    'closed' => $close,
    'range' => $_GET['range'] ?? 0,
    'config' => $configbutton,
    'closeattendance' => $closeattendance,
    'dirroot' => $dircomplement[1],
    'asistio' => "Asistió",
    'inasistencia' => "Incumplimiento injustificado",
    'retraso' => "Inasistencia no programada",
    'excusa' => "Inasistencia programada",
    'page' => $attendancepage,
    'search' => $search,
];

echo $OUTPUT->render_from_template('local_asistencia/studentslist', $templatecontext);

$PAGE->requires->js_call_amd('local_asistencia/attendance_views', 'init');
echo $OUTPUT->footer();
