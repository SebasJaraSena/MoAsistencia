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
 * @author     Luis Pérez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_rss_client\output\item;
use core\plugininfo\local;

use function PHPSTORM_META\type;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/externallib.php');
require_once($CFG->dirroot . '/local/asistencia/lib.php');

require_login();

global $CFG, $USER;

// Creacion de cache
$cache = cache::make('local_asistencia', 'coursestudentslist');
$userid = $USER->id;
$courseid = $_GET['courseid'];
$attendancepage = $_GET['page'] ?? 1;
//$limit = $_GET['limit']??1;/**
$date = new DateTime(date('Y-m-d'));
$startweek = clone $date;
$endweek = clone $date;

// Si hoy es lunes, usamos la fecha actual, si no, retrocedemos al lunes
$initial = $date->format('l') == 'Monday' ? $startweek->format("Y-m-d") : $startweek->modify("last monday")->format("Y-m-d");
// Si hoy es domingo, usamos la fecha actual, si no, avanzamos al domingo
$final = $date->format('l') == 'Sunday' ? $endweek->format("Y-m-d") : $endweek->modify("next sunday")->format("Y-m-d");

$close = local_asistencia_external::close_validation_retard($courseid, $initial, $final);
$context = context_course::instance($courseid);
$currenturl = new moodle_url('/local/asistencia/index.php');
$dircomplement = explode("/", $currenturl->get_path());
$PAGE->set_url($currenturl);
$PAGE->set_context($context);
$PAGE->set_title('Lista Asistencia');
$PAGE->requires->js_call_amd('local_asistencia/attendance_observations', 'init');
$PAGE->requires->css(new moodle_url('/local/asistencia/styles/styles.css', array('v' => time())));

require_capability('local/asistencia:view', $context);
$a = 0;
$close = 0;

function studentsFormatWeek($studentslist, $week, $cachehistoryattendance, $userid, $initial, $final, $a, $suspended, $close)
{
    global $DB, $courseid;

    $weekdaysnames = ['Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2, 'Thursday' => 3, 'Friday' => 4, 'Saturday' => 5, 'Sunday' => 6];
    $totaldaysattendance = 0;

    foreach ($studentslist as $i => $student) {
        $studentid = $student['id'];
        $studentslist[$i]['week'] = $week;

        // 1. Obtener la fecha de suspensión (si aplica)
        $suspensionDate = null;
        if ($student['status']) {
            $sql = "SELECT ue.timemodified
                    FROM {user_enrolments} ue
                    JOIN {enrol} e ON ue.enrolid = e.id
                    WHERE ue.userid = ? AND e.courseid = ? AND ue.status = 1
                    ORDER BY ue.timemodified DESC LIMIT 1";
            $params = [$studentid, $courseid];

            if ($record = $DB->get_record_sql($sql, $params)) {
                $suspensionDate = date('Y-m-d', $record->timemodified);
            }
        }

        // 2. Inicializar la semana con valores por defecto
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
        }

        // 3. Obtener asistencias permanentes
        $filtered = array_filter($cachehistoryattendance, fn($item) => $item['student_id'] == $studentid);
        if (!empty($filtered)) {
            $firstKey = array_key_first($filtered);
            $jsonattendance = json_decode($cachehistoryattendance[$firstKey]['full_attendance'], true) ?? [];

            $filteredRecords = array_filter($jsonattendance, function ($item) use ($initial, $final, $userid) {
                return $item['DATE'] >= $initial && $item['DATE'] <= $final && $item['TEACHER_ID'] == $userid;
            });

            foreach ($filteredRecords as $record) {
                $date = DateTime::createFromFormat('Y-m-d', $record['DATE']);
                $dayName = $date->format('l');
                $index = $weekdaysnames[$dayName];

                // Reiniciar valores
                $studentslist[$i]['week'][$index]['selection'] = [
                    'op-8' => 0,
                    'op0' => 0,
                    'op1' => 0,
                    'op2' => 0,
                    'op3' => 0,
                ];
                $studentslist[$i]['week'][$index]['selection']['op' . $record['ATTENDANCE']] = 1;

                $studentslist[$i]['week'][$index]['missedhours'] = $record['AMOUNTHOURS'] ?? '';
                $studentslist[$i]['week'][$index]['observations'] = $record['OBERVATIONS'] ?? '';

                //  Bloquear si ya existe asistencia guardada
                $studentslist[$i]['week'][$index]['locked'] = true;

                $totaldaysattendance++;
            }
        }

        // 4. Validar por suspensión para bloquear desde cierta fecha
        if ($suspensionDate) {
            foreach ($week as $j => $day) {
                if ($day['fulldate'] >= $suspensionDate) {
                    $studentslist[$i]['week'][$j]['locked'] = true;
                }
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


if ($_SERVER["REQUEST_METHOD"] === "POST" && $close == 0) {
    $attendances = $_POST['attendance'];
    $infos = $_POST['extrainfo'];
    $hours = $_POST['extrainfoNum'];
    $rcourseid = $_POST['courseid'];
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

    // Guardar en tabla permanente agrupando por estudiante y evitando duplicados
    $asistencia = $DB->get_records('local_asistencia', ['courseid' => $courseid]);
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
            'course_id' => $courseid,
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
            $nuevo->course_id = $courseid;
            $nuevo->student_id = $studentid;
            $nuevo->full_attendance = json_encode(array_values($records));
            $DB->insert_record('local_asistencia_permanente', $nuevo);
        }

        // 🔄 Refrescar caché después de guardar
        $updated_attendance = $DB->get_records('local_asistencia_permanente', ['course_id' => $courseid]);
        $cache->set("H_$courseid", json_encode($updated_attendance));

    }
    $DB->delete_records('local_asistencia', ['courseid' => $courseid]);


    $url = new moodle_url('/local/asistencia/attendance.php', [
        'courseid' => $courseid,
        'range' => 0,
        'page' => 1
    ]);

    echo $OUTPUT->header();
    echo $OUTPUT->notification("Guardando asistencia, redireccionando...", 'notifysuccess');
    echo "<script>setTimeout(function(){ window.location.href = '{$url}'; }, 50);</script>";
    echo $OUTPUT->footer();
    exit;

}

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

local_asistencia_setup_breadcrumb('Asistencia general');
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

    $students = local_asistencia_external::fetch_students($context->id, $courseid, 5, $pageurl, 10000, $condition);
    $supended = array_filter($students['students_data'], function ($item) {
        return $item['status'] == 1;
    });

    $pages_attendance_array['pages'] = $students['pages'];
    $pages_attendance_array[$attendancepage] = $students['students_data'];
    $studentsamount = $students['studentsamount'];

    if (empty($pages_attendance_array[$attendancepage])) {
        \core\notification::add("No se encontraron aprendices matriculados.", \core\output\notification::NOTIFY_WARNING);
    }

    $pages_attendance_array_copy = $pages_attendance_array;
    $listlimit = count($pages_attendance_array_copy[$attendancepage]);
    $cache->set('attendancelist' . $courseid, json_encode($pages_attendance_array));
    $test = $cache->get('attendancelist' . $courseid);
    $studentslist = json_decode($test, true);
}

for ($page = 1; $page <= $pages_attendance_array['pages']; $page++) {
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
    'currentpage' => $currentpage,
    'close' => $a == (count($students[$attendancepage]) * 7) ? 1 : 0,
    'closed' => $close,
    'range' => $_GET['range'] ?? 0,
    'config' => $configbutton,
    'closeattendance' => $closeattendance,
    'dirroot' => $dircomplement[1],
    'asistio' => "Asistió",
    'inasistencia' => "No asistió",
    'retraso' => "Llegó tarde",
    'excusa' => "Excusa médica",
];

echo $OUTPUT->render_from_template('local_asistencia/studentslist', $templatecontext);

$PAGE->requires->js_call_amd('local_asistencia/attendance_views', 'init');
echo $OUTPUT->footer();
