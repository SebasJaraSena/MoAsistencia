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

// Cargar los archivos necesarios
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/externallib.php');
require_once($CFG->dirroot . '/local/asistencia/lib.php');

require_login();

global $CFG, $USER, $DB, $SESSION;

// Función para normalizar el texto de búsqueda
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

// Validar si el formulario se envió
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['attendance'], $_POST['extrainfo'], $_POST['extrainfoNum'], $_POST['courseid'], $_POST['teacherid'])
) {
    // Obtener los datos del formulario
    $attendances = $_POST['attendance'];
    $infos = $_POST['extrainfo'];
    $hours = $_POST['extrainfoNum'];
    $rcourseid = $_POST['courseid'];
    $teacherid = $_POST['teacherid'];
    $attendancepage = isset($_POST['page']) ? (int) trim($_POST['page']) : 1;

    // Recorrer los datos del formulario
    foreach ($attendances as $studentid => $days) {
        foreach ($days as $date => $attendance) {
            if ($attendance == "-8") {
                continue;
            }

            $observations = $infos[$studentid][$date] ?? '';
            $amountHours = $hours[$studentid][$date] ?? 0;

            // Verificar si el registro ya existe
            $existing = $DB->get_records('local_asistencia', [
                'courseid' => $rcourseid,
                'studentid' => $studentid,
                'teacherid' => $teacherid
            ]);
            // Recorrer los registros existentes
            $record = null;
            foreach ($existing as $rec) {
                if ($rec->date === $date) {
                    $record = $rec;
                    break;
                }
            }
            // Verificar si el registro existe
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
    // Obtener los datos de asistencia
    // Guardar en tabla permanente agrupando por estudiante y evitando duplicados
    $asistencia = $DB->get_records('local_asistencia', ['courseid' => $rcourseid]);
    $por_estudiante = [];
    // Recorrer los datos de asistencia
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
    // Recorrer los datos de asistencia
    foreach ($por_estudiante as $studentid => $records) {
        $result = $DB->get_record('local_asistencia_permanente', [
            'course_id' => $rcourseid,
            'student_id' => $studentid
        ]);
        // Verificar si el registro existe
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
    }
    // Eliminar los registros de asistencia
    $DB->delete_records('local_asistencia', ['courseid' => $rcourseid]);
    // Redireccionar
    // Redirección limpia
    $url = new moodle_url('/local/asistencia/previous_attendance.php', [
        'courseid' => $rcourseid,
        'page' => $attendancepage,
        'range' => 0
    ]);

    // Protección final: asegúrate de no haber enviado salida antes
    if (headers_sent($file, $line)) {
        die(" No se puede redirigir. Headers ya enviados en $file línea $line");
    }
    // Protección final: asegúrate de no haber enviado salida antes
    if (headers_sent($file, $line)) {
        die(" Headers already sent in <strong>$file</strong> on line <strong>$line</strong>. Cannot redirect.");
    }

    redirect($url, "Asistencia guardada...", null, \core\output\notification::NOTIFY_SUCCESS);
}

// Obtener el ID del curso
$courseid = required_param('courseid', PARAM_INT);
// Verificar si el curso existe
if (!$DB->record_exists('course', ['id' => $courseid])) {
    print_error('Curso no encontrado');
}

// Obtener los datos del formulario
$attendancepage = $_POST['page'] ?? $_GET['page'] ?? 1;
$range = $_POST['range'] ?? $_GET['range'] ?? 0;
// Crear la caché
$cache = cache::make('local_asistencia', 'coursestudentslist');
$userid = $USER->id;
$limit = 25;
$date = new DateTime(date('Y-m-d'));
// Verificar si el número de semanas es válido
if (isset($_POST['numweeks']) && in_array($_POST['numweeks'], ['1', '2', '3', '4'])) {
    $SESSION->selectedweeks = (int) $_POST['numweeks'];
} elseif (isset($_GET['weeks']) && in_array($_GET['weeks'], ['1', '2', '3', '4'])) {
    $SESSION->selectedweeks = (int) $_GET['weeks'];
}
// Obtener el número de semanas
$weeks = $SESSION->selectedweeks ?? 1;

$startweek = clone $date;
$endweek = clone $date;
$initial = $date->format('l') == 'Monday' ? $startweek->modify("-$weeks week")->format("Y-m-d") : $startweek->modify("-$weeks week")->modify("last monday")->format("Y-m-d");
$final = $date->format('l') == 'Sunday' ? $endweek->modify("-$weeks week")->format("Y-m-d") : $endweek->modify("-$weeks week")->modify("next sunday")->format("Y-m-d");
// Cerrar la validación de retardos
$close = local_asistencia_external::close_validation_retard($courseid, $initial, $final);
// Obtener el contexto del curso
$context = context_course::instance($courseid);
// Obtener los parámetros
$params = [
    'courseid' => $courseid,
    'page' => $attendancepage,
    'weeks' => $weeks,
    'range' => $range,
    'limit' => $limit
];
// Obtener la URL actual
$currenturl = new moodle_url('/local/asistencia/previous_attendance.php', $params);
// Obtener el complemento de la URL
$dircomplement = explode("/", $currenturl->get_path());
$PAGE->set_url($currenturl);
$PAGE->set_context($context);
// Cargar el curso ANTES de modificar el navbar         

$PAGE->set_course(get_course($courseid));

// Verificar permisos
require_capability('local/asistencia:viewanterior', $context);

// Construye el breadcrumb completo
local_asistencia_build_breadcrumbs($courseid, 'asistencia_anterior');

//  Título y heading
$PAGE->set_title("Lista Asistencia Semana $weeks");
$PAGE->set_heading(get_course($courseid)->fullname);

//  JS y CSS
$PAGE->requires->js_call_amd('local_asistencia/attendance_observations', 'init');
$PAGE->requires->css(new moodle_url('/local/asistencia/styles/styles.css', array('v' => time())));

$a = 0;

// Función para formatear los datos de asistencia
function studentsFormatWeek(
    $studentslist,
    $week,
    $cachehistoryattendance,
    $temporalattendance,
    $userid,
    $initial,
    $final,
    $a,
    $suspended,
    $close,
    $courseid,
) {
    global $DB;
    // Definir los nombres de los días de la semana
    $weekdaysnames = [
        'Monday' => 0,
        'Tuesday' => 1,
        'Wednesday' => 2,
        'Thursday' => 3,
        'Friday' => 4,
        'Saturday' => 5,
        'Sunday' => 6
    ];
    $totaldaysattendance = 0;
    // Recorrer los datos de asistencia
    foreach ($studentslist as $i => $student) {
        $studentid = $student['id'];
        $studentslist[$i]['week'] = $week;
        // Inicializar semana por defecto

        for ($j = 0; $j < 7; $j++) {
            $studentslist[$i]['week'][$j]['selection'] = [
                'op-8' => 1,
                'op0' => 0,
                'op1' => 0,
                'op2' => 0,
                'op3' => 0,
            ];
            $studentslist[$i]['week'][$j]['closed'] = $close;
            $studentslist[$i]['week'][$j]['locked'] = false;
            $studentslist[$i]['week'][$j]['missedhours'] = '';
            $studentslist[$i]['week'][$j]['observations'] = '';
        }
        // Marcar asistencias históricas (bloqueadas)
        $filtered = array_filter(
            $cachehistoryattendance,
            fn($item) => $item['student_id'] == $studentid
        );
        if (!empty($filtered)) {
            $firstKey = array_key_first($filtered);
            $jsonattendance = json_decode(
                $cachehistoryattendance[$firstKey]['full_attendance'],
                true
            ) ?: [];
            // Filtrar los datos de asistencia
            $records = array_filter($jsonattendance, function ($item) use ($initial, $final, $userid) {
                return $item['DATE'] >= $initial
                    && $item['DATE'] <= $final
                    && $item['TEACHER_ID'] == $userid;
            });
            // Recorrer los datos de asistencia
            foreach ($records as $record) {
                $date = DateTime::createFromFormat('Y-m-d', $record['DATE']);
                $index = $weekdaysnames[$date->format('l')];
                // Seleccionar las opciones
                foreach (['op-8', 'op0', 'op1', 'op2', 'op3'] as $op) {
                    $studentslist[$i]['week'][$index]['selection'][$op] = 0;
                }
                $studentslist[$i]['week'][$index]['selection']
                ['op' . $record['ATTENDANCE']] = 1;
                // Asignar las horas y observaciones
                $studentslist[$i]['week'][$index]['missedhours'] =
                    $record['AMOUNTHOURS'] ?? '';
                $studentslist[$i]['week'][$index]['observations'] =
                    $record['OBERVATIONS'] ?? '';
                // Bloquear la celda
                $studentslist[$i]['week'][$index]['locked'] = true;
                $totaldaysattendance++;
            }
        }
        // Marcar asistencias temporales (bloqueadas)
        if (!empty($temporalattendance)) {
            $filteredtemp = array_filter(
                json_decode(json_encode($temporalattendance), true),
                fn($item) => $item['studentid'] == $studentid
            );
            foreach ($filteredtemp as $prev) {
                $day = DateTime::createFromFormat('Y-m-d', $prev['date'])
                    ->format('l');
                $index = $weekdaysnames[$day];
                // Seleccionar las opciones
                foreach (['op-8', 'op0', 'op1', 'op2', 'op3'] as $op) {
                    $studentslist[$i]['week'][$index]['selection'][$op] = 0;
                }
                $studentslist[$i]['week'][$index]['selection']
                ['op' . $prev['attendance']] = 1;
                // Asignar las horas y observaciones
                $studentslist[$i]['week'][$index]['missedhours'] =
                    $prev['amounthours'] != 0 ? $prev['amounthours'] : '';
                $studentslist[$i]['week'][$index]['observations'] =
                    $prev['observations'] ?? '';
                // Bloquear la celda
                $studentslist[$i]['week'][$index]['locked'] = true;
            }
        }
        // Si el alumno está suspendido, bloquear TODAS las celdas
        if ($student['status']) {
            for ($j = 0; $j < 7; $j++) {
                $studentslist[$i]['week'][$j]['locked'] = true;
            }
        }
    }
    // Calcular el auxiliar
    $auxiliar = (count($studentslist) - $suspended) === 0
        ? 0
        : $totaldaysattendance / (count($studentslist) - $suspended);
    // Retornar los datos de asistencia
    return [$studentslist, $auxiliar, $a];
}

// Función para obtener el rango de días
function getWeekRange($initial): array
{ // Funtion that establishes the range of days tha would be shown in the table header
    $week = ['Monday' => 'L', 'Tuesday' => 'M', 'Wednesday' => 'X', 'Thursday' => 'J', 'Friday' => 'V', 'Saturday' => 'S', 'Sunday' => 'D'];
    $fullweek = [];
    $date = new DateTime($initial);
    // Recorrer los días de la semana
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
    // Formatear las fechas según su formato preferido
    return $fullweek;
}

// Se obtiene información guardada en caché
// Obtener la información de asistencia
$attendance_info = [];
// Obtener el histórico de asistencia
$historyattendance = $DB->get_records('local_asistencia_permanente', ['course_id' => $courseid]);
// Guardar el histórico en la caché
$cache->set("H_$courseid", json_encode($historyattendance));
$cachehistoryattendance = json_decode($cache->get("H_$courseid"), true);
// Obtener el número de página
$pageurl = $attendancepage - 1 ?? 0;
$currentpage = $pageurl + 1;
// Obtener el rango de días
$weekRange = getWeekRange($initial);
// Crear el formulario
$form = new edit();
// Inicializar la condición
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
// Guardar la condición en la caché
$cache->set("course$courseid.user$userid", $condition);
// Obtener el curso
$course = get_course($courseid);
// Obtener el usuario
echo $OUTPUT->header();
$userid = $USER->id;
$adminsarray = explode(",", $DB->get_record('config', ['name' => 'siteadmins'])->value);
$configbutton = in_array($userid, $adminsarray) ? 1 : 0;
$pages_attendance_string = $cache->get('attendancelist' . $courseid);
$cache->delete('attendancelist' . $courseid);
// Verificar si existe la información de asistencia
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
    // Filtrar los estudiantes suspendidos
    $supended = array_filter($paged_students, function ($item) {
        return $item['status'] == 1;
    });
    // Asignar el número de páginas
    $pages_attendance_array['pages'] = $total_pages;
    $pages_attendance_array[$attendancepage] = $paged_students;
    $studentsamount = $total_students;
    // Verificar si no hay estudiantes en la página
    if (empty($pages_attendance_array[$attendancepage])) {
        \core\notification::add("No se encontraron aprendices matriculados.", \core\output\notification::NOTIFY_WARNING);
    }
    // Copiar los datos de asistencia
    $pages_attendance_array_copy = $pages_attendance_array;
    $listlimit = count($pages_attendance_array_copy[$attendancepage]);
    $cache->set('attendancelist' . $courseid, json_encode($pages_attendance_array));
    $test = $cache->get('attendancelist' . $courseid);
    $studentslist = json_decode($test, true);
}

// Generar paginador
// Inicializar el paginador
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
// Obtener el rango de días
$range = isset($_GET['range']) ? $_GET['range'] : 0;
[$initialdate, $finaldate] = [$weekRange[0]['fulldate'], $weekRange[6]['fulldate']];
// Obtener las asistencias temporales
$sql = "SELECT * FROM {local_asistencia} WHERE courseid = $courseid AND teacherid = $userid AND \"date\" BETWEEN '$initialdate' AND '$finaldate'";
$temporalattendance = array_values($DB->get_records_sql($sql));
// Formatear los datos de asistencia
[$students, $totaldaysattendance, $a] = studentsFormatWeek($studentslist[$attendancepage] ?? $pages_attendance_array_copy[$attendancepage], $weekRange, $cachehistoryattendance, $temporalattendance, $userid, $initial, $final, $a, count($supended), $close, $courseid);

$studentslist[$attendancepage] = $students;
$closeattendance = ($studentsamount * $totaldaysattendance) == count($temporalattendance) && count($temporalattendance) ? 0 : 1;
$cache->set('attendancelist' . $courseid, json_encode($studentslist));
// Obtener los datos de asistencia
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
    'weeks' => $weeks,
    '1week' => $weeks == 1 ? 1 : 0,
    '2week' => $weeks == 2 ? 1 : 0,
    '3week' => $weeks == 3 ? 1 : 0,
    '4week' => $weeks == 4 ? 1 : 0,
    'asistio' => "Asistió",
    'inasistencia' => "Incumplimiento injustificado",
    'retraso' => "Inasistencia no programada",
    'excusa' => "Inasistencia programada",
    'page' => $attendancepage,
    'search' => $search,
];
// Renderizar la página                 
echo $OUTPUT->render_from_template('local_asistencia/previous_attendance', $templatecontext);
// Cargar el JS
$PAGE->requires->js_call_amd('local_asistencia/attendance_views', 'init');
echo $OUTPUT->footer();
