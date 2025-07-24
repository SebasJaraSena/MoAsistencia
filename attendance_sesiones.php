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
// Obtener el contexto del sistema
use block_rss_client\output\item;
use core\plugininfo\local;
use function PHPSTORM_META\type;
// Obtener el contexto del sistema
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/externallib.php');
require_once($CFG->dirroot . '/local/asistencia/lib.php');
// Obtener el contexto del sistema
require_login();
global $CFG, $USER, $DB, $SESSION;
// Obtenemos lo esencial del POST
$rcourseid = $_POST['courseid'] ?? null;
$attendancepage = isset($_POST['page']) ? (int) trim($_POST['page']) : 1;
$selecteddate = $_POST['date'] ?? $_GET['date'] ?? date('Y-m-d');
if (is_array($selecteddate)) {
    $selecteddate = reset($selecteddate);
}

$courseid = required_param('courseid', PARAM_INT);
$teacherid = $USER->id;


if (is_array($selecteddate)) {
    $selecteddate = reset($selecteddate);
}
// Validar si es un POST y procesar antes de cualquier salida
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST['attendance'], $_POST['extrainfo'], $_POST['extrainfoNum'], $_POST['courseid'], $_POST['teacherid'])
) {
    $attendances = $_POST['attendance'];
    $infos = $_POST['extrainfo'];
    $hours = $_POST['extrainfoNum'];
    $teacherid = $_POST['teacherid'];
    $sessionid = $_POST['sessionid']; // <-- nuevo
    // Obtener el contexto del sistema
    foreach ($attendances as $studentid => $days) {
        foreach ($days as $date => $attendance) {
            if ($attendance == "-8") {
                continue;
            }
            $observations = $infos[$studentid][$date] ?? '';
            $amountHours = $hours[$studentid][$date] ?? 0;
            // Buscar si ya existe un registro para este estudiante, sesión y fecha
            $existing = $DB->get_records('local_asistencia', [
                'courseid' => $rcourseid,
                'studentid' => $studentid,
                'teacherid' => $teacherid,
                'sessionid' => $sessionid
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
                $record->sessionid = $sessionid;
                $DB->insert_record('local_asistencia', $record);
            }
        }
    }
    // Obtener el contexto del sistema
    // Guardar permanentemente agrupado por estudiante
    $asistencia = $DB->get_records('local_asistencia', ['courseid' => $rcourseid]);
    $por_estudiante = [];
    // Obtener el contexto del sistema
    foreach ($asistencia as $asis) {
        $key = $asis->date . '_' . $asis->teacherid . '_' . $asis->sessionid;
        $por_estudiante[$asis->studentid][$key] = [
            'TEACHER_ID' => $asis->teacherid,
            'ATTENDANCE' => $asis->attendance,
            'DATE' => $asis->date,
            'OBERVATIONS' => $asis->observations,
            'AMOUNTHOURS' => $asis->amounthours,
            'SESSION_ID' => $asis->sessionid
        ];
    }
    // Obtener el contexto del sistema  
    foreach ($por_estudiante as $studentid => $records) {
        $result = $DB->get_record('local_asistencia_permanente', [
            'course_id' => $rcourseid,
            'student_id' => $studentid
        ]);
        // Validar si existe el resultado
        if ($result) {
            // Obtener el historial
            $historial = json_decode($result->full_attendance, true);
            // Recorrer los registros
            foreach ($records as $newRecord) {
                // Validar si existe el registro
                $existe = false;
                // Recorrer el historial
                foreach ($historial as &$item) {
                    if (
                        $item['DATE'] === $newRecord['DATE'] &&
                        $item['TEACHER_ID'] === $newRecord['TEACHER_ID'] &&
                        (isset($item['SESSION_ID']) && $item['SESSION_ID'] == $newRecord['SESSION_ID'])
                    ) {
                        $item = $newRecord;
                        $existe = true;
                        break;
                    }
                }
                // Validar si no existe el registro
                if (!$existe) {
                    $historial[] = $newRecord;
                }
            }
            $result->full_attendance = json_encode($historial);
            $DB->update_record('local_asistencia_permanente', $result);
        } else {
            // Crear un nuevo registro
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
    // Eliminar los registros de la asistencia
    $DB->delete_records('local_asistencia', ['courseid' => $rcourseid]);
    //  Redirigir limpiamente antes de cualquier salida
    $url = new moodle_url('/local/asistencia/attendance_sesiones.php', [
        'courseid' => $rcourseid,
        'range' => 0,
        'page' => $attendancepage,
        'date' => $selecteddate, 
        'sessionid' => $sessionid
    ]);

    // Redirigir
    redirect($url, "Asistencia guardada...", null, \core\output\notification::NOTIFY_SUCCESS);
}

// Creación de cache
$cache = cache::make('local_asistencia', 'coursestudentslist');
$userid = $USER->id;
$courseid = $_GET['courseid'];
$attendancepage = $_POST['page'] ?? $_GET['page'] ?? 1;
$limit = 25;
$search = trim($_GET['search'] ?? '');

$date = new DateTime(date('Y-m-d'));
$startweek = clone $date;
// Obtener el contexto del sistema
$endweek = clone $date;
$initial = $selecteddate;
$final = $selecteddate;

$close = local_asistencia_external::close_validation_retard($courseid, $initial, $final);
$context = context_course::instance($courseid);
$range = optional_param('range', 0, PARAM_INT);
//variables a renderizar
$params = [
    'courseid' => $courseid,
    'page' => $attendancepage,
    'range' => $range,
    'limit' => $limit
];

// Parámetros y URL actual
$currenturl = new moodle_url('/local/asistencia/attendance.php', $params);
$dircomplement = explode("/", $currenturl->get_path());
// Establecer URL y contexto
$PAGE->set_url($currenturl);
$PAGE->set_context($context);
// Obtener el contexto del sistema
local_asistencia_build_breadcrumbs($courseid, 'asistencia_sesiones');
// Cargar el curso (¡antes de tocar el navbar!)
$PAGE->set_course(get_course($courseid));
// Título de la página
$PAGE->set_title(get_string('asistencia_sesiones', 'local_asistencia'));
// Scripts y estilos
$PAGE->requires->js_call_amd('local_asistencia/attendance_observations', 'init');
$PAGE->requires->css(
    new moodle_url('/local/asistencia/styles/styles.css', ['v' => time()])
);
// Verificar permisos
require_capability('local/asistencia:vergeneral', $context);
// Función para normalizar el texto
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
// Función para formatear la semana
function studentsFormatWeek(
    $studentslist,
    $week,
    $cachehistoryattendance,
    $userid,
    $initial,
    $final,
    $a,
    $suspended,
    $close,
    $sessionid
) {
    global $DB, $courseid;
    //Arreglo con los dias de la semana                            
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
    // Recorrer los estudiantes
    foreach ($studentslist as $i => $student) {
        $studentid = $student['id'];
        $studentslist[$i]['week'] = $week;
        // Inicializar la semana con valores por defecto
        foreach ($week as $j => $dayinfo) {

            $studentslist[$i]['week'][$j]['selection'] = [
                'op-8' => 1,
                'op0' => 0,
                'op1' => 0,
                'op2' => 0,
                'op3' => 0,
            ];
            $studentslist[$i]['week'][$j]['closed'] = $close;
            $studentslist[$i]['week'][$j]['locked'] = false;
            // opcionalmente también:
            $studentslist[$i]['week'][$j]['missedhours'] = '';
            $studentslist[$i]['week'][$j]['observations'] = '';
        }
        // Obtener las asistencias ya guardadas
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
            // Filtrar las asistencias
            $records = array_filter($jsonattendance, function ($item) use ($initial, $final, $userid, $sessionid) {
                return $item['DATE'] >= $initial
                    && $item['DATE'] <= $final
                    && $item['TEACHER_ID'] == $userid
                    && isset($item['SESSION_ID'])
                    && $item['SESSION_ID'] == $sessionid;
            });

            // Recorrer las asistencias
            foreach ($records as $record) {
                $date = DateTime::createFromFormat('Y-m-d', $record['DATE']);
                $index = 0;
                // Poner la selección correcta
                foreach (['op-8', 'op0', 'op1', 'op2', 'op3'] as $op) {
                    $studentslist[$i]['week'][$index]['selection'][$op] = 0;
                }
                $studentslist[$i]['week'][$index]['selection']
                ['op' . $record['ATTENDANCE']] = 1;
                // Cargar horas y observaciones
                $studentslist[$i]['week'][$index]['missedhours'] =
                    $record['AMOUNTHOURS'] ?? '';
                $studentslist[$i]['week'][$index]['observations'] =
                    $record['OBERVATIONS'] ?? '';
                // Bloquear esa celda (modo sólo lectura)
                $studentslist[$i]['week'][$index]['locked'] = true;
                $totaldaysattendance++;
            }
        }
        // Si el alumno está suspendido, bloquear todas las celdas
        if ($student['status']) {
            for ($j = 0; $j < 1; $j++) {
                $studentslist[$i]['week'][$j]['locked'] = true;
            }
        }
    }
    // Retornar los datos
    return [$studentslist, $totaldaysattendance, $a];
}
// Función para obtener el rango de la semana
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
// Filtrar solo asistencias con SESSION_ID
if (is_array($cachehistoryattendance)) {
    foreach ($cachehistoryattendance as &$item) {
        if (isset($item['full_attendance'])) {
            $full = json_decode($item['full_attendance'], true);
            $full = array_filter($full, function ($att) {
                return isset($att['SESSION_ID']) && !empty($att['SESSION_ID']);
            });
            $item['full_attendance'] = json_encode(array_values($full));
        }
    }
    unset($item);
}

$pageurl = $attendancepage - 1 ?? 0;
$currentpage = $pageurl + 1;
$weekRange = [
    [
        'day' => (new DateTime($selecteddate))->format('D'),
        'date' => (new DateTime($selecteddate))->format('d/m'),
        'fulldate' => $selecteddate
    ]
];


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
    // Obtener el total de páginas y los estudiantes por página
    $pages_attendance_array['pages'] = $total_pages;
    $pages_attendance_array[$attendancepage] = $paged_students;
    $studentsamount = $total_students;

    // Validar si no hay estudiantes en la página actual
    if (empty($pages_attendance_array[$attendancepage])) {
        \core\notification::add("No se encontraron aprendices matriculados.", \core\output\notification::NOTIFY_WARNING);
    }

    // Copiar el array de páginas y estudiantes
    $pages_attendance_array_copy = $pages_attendance_array;
    $listlimit = count($pages_attendance_array_copy[$attendancepage]);
    // Guardar el array de páginas y estudiantes en caché
    $cache->set('attendancelist' . $courseid, json_encode($pages_attendance_array));
    // Obtener el array de páginas y estudiantes desde caché
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

// Obtener el rango de la semana
$range = isset($_GET['range']) ? $_GET['range'] : 0;
// Obtener las fechas inicial y final de la semana
$initialdate = $selecteddate;
$finaldate = $selecteddate;

// Obtener las asistencias temporales
$sql = "SELECT * FROM {local_asistencia} WHERE courseid = $courseid AND teacherid = $userid AND \"date\" = '$selecteddate'";

$temporalattendance = array_values($DB->get_records_sql($sql));


// --- SESIONES MULTIPLES POR DIA ---
$sessionstable = 'local_asistencia_sesiones';
$sessionparams = [
    'courseid' => $courseid,
    'teacherid' => $teacherid,
    'date' => $selecteddate
];
// Si se solicita crear nueva sesión (por GET)
if (isset($_GET['newsession']) && $_GET['newsession'] == 1) {
    // Contar cuántas sesiones existen ya para ese día
    $existingsessions = $DB->get_records($sessionstable, $sessionparams);
    $sessioncount = count($existingsessions) + 1;
    $sessionname = 'Sesión #' . $sessioncount . ' - ' . $selecteddate . ' ' . date('H:i');
    $newsession = new stdClass();
    $newsession->courseid = $courseid;
    $newsession->teacherid = $teacherid;
    $newsession->date = $selecteddate;
    $newsession->createdat = time();
    $newsession->sessionname = $sessionname;
    $sessionid = $DB->insert_record($sessionstable, $newsession);
    // DEBUG: Registrar en el log los valores antes de redirigir
    error_log('DEBUG nueva sesión: courseid=' . print_r($courseid, true) . ' selecteddate=' . print_r($selecteddate, true) . ' sessionid=' . print_r($sessionid, true) . ' attendancepage=' . print_r($attendancepage, true));
    // Redirigir para limpiar el parámetro newsession y seleccionar la nueva sesión
    $url = new moodle_url('/local/asistencia/attendance_sesiones.php', [
        'courseid' => $courseid,
        'date' => $selecteddate,
        'sessionid' => $sessionid,
        'page' => $attendancepage
    ]);
    redirect($url);
}
// Buscar todas las sesiones para ese curso, fecha e instructor
$sessions = $DB->get_records($sessionstable, $sessionparams, 'createdat ASC');
$sessions_list = array_values($sessions);
// Determinar la sesión seleccionada
$sessionid = $_GET['sessionid'] ?? null;
if (!$sessionid && count($sessions_list) > 0) {
    $sessionid = $sessions_list[0]->id;
}
// Si no hay ninguna sesión, crear una automáticamente SOLO si se solicita con newsession=1
if (count($sessions_list) == 0 && isset($_GET['newsession']) && $_GET['newsession'] == 1) {
    $sessionname = 'Sesión del ' . $selecteddate . ' ' . date('H:i');
    $newsession = new stdClass();
    $newsession->courseid = $courseid;
    $newsession->teacherid = $teacherid;
    $newsession->date = $selecteddate;
    $newsession->createdat = time();
    $newsession->sessionname = $sessionname;
    $sessionid = $DB->insert_record($sessionstable, $newsession);
    $sessions_list[] = $DB->get_record($sessionstable, ['id' => $sessionid]);
}
// Marcar la sesión seleccionada en el array sessions_list
foreach ($sessions_list as &$sess) {
    $sess->is_selected = ($sess->id == $sessionid);
}
unset($sess);

// Determinar si hay sesiones para el día seleccionado
$has_sessions = count($sessions_list) > 0;

// Obtener el nombre de la sesión seleccionada
$sessionname = '';
foreach ($sessions_list as $sess) {
    if ($sess->id == $sessionid) {
        $sessionname = $sess->sessionname;
        break;
    }
}
$a = 0;
// Formatear los datos de los estudiantes
[$students, $totaldaysattendance, $a] = studentsFormatWeek($studentslist[$attendancepage] ?? $pages_attendance_array_copy[$attendancepage], $weekRange, $cachehistoryattendance, $userid, $initial, $final, $a, count($supended), $close, $sessionid);

// Actualizar la lista de estudiantes
$studentslist[$attendancepage] = $students;
// Validar si la asistencia está cerrada
$closeattendance = $studentsamount == count($temporalattendance) ? 0 : 1;
// Guardar la lista de estudiantes en caché
$cache->set('attendancelist' . $courseid, json_encode($studentslist));

// Obtener la lista de estudiantes desde caché
$studentsstring = $cache->get('attendancelist' . $courseid);
$students = json_decode($studentsstring, true);
// Generar el contexto para el template
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

// Pasar sesiones, sessionid, sessionname y has_sessions al template
$templatecontext = (object) array_merge((array) $templatecontext, [
    'sessions_list' => $sessions_list,
    'sessionid' => $sessionid,
    'sessionname' => $sessionname,
    'has_sessions' => $has_sessions
]);

echo $OUTPUT->render_from_template('local_asistencia/attendance_sesiones', $templatecontext);

$PAGE->requires->js_call_amd('local_asistencia/attendance_views', 'init');
echo $OUTPUT->footer();
