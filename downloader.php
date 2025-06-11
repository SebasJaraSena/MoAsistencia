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
 * @author     Zajuna team 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/externallib.php');

// Creacion de cache
$cache = cache::make('local_asistencia', 'coursestudentslist');
$userid = $USER->id;
$initialdate = $_GET['initialdate'];
$finaldate = $_GET['finaldate'];
$cumulous = $_GET['cumulous'];
$search = $_GET['search'] ?? '';

/* $data = json_decode($urldata, true); */
$urldata = $_GET['urldata'] ?? null;
$data = !empty($urldata) ? json_decode($urldata, true) : [];

$courseid = $_GET['courseid'];
$PAGE->set_url(new moodle_url('/local/asistencia/downloader.php'));
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_title('Descargar reporte');

global $CFG, $DB;
$attendancehistory = json_decode(json_encode($DB->get_records('local_asistencia_permanente', ['course_id' => $courseid])), true);
$shortname = json_decode(json_encode($DB->get_record('course', ['id' => $courseid], 'shortname')), true)['shortname'];

$result = local_asistencia_external::fetch_attendance_report($attendancehistory, $initialdate, $finaldate, $cumulous, $userid);
if ($search !== '') {
    $result = array_filter($result, function ($row) use ($search) {
        return stripos($row['username'], $search) !== false
            || stripos($row['lastname'], $search) !== false
            || stripos($row['firstname'], $search) !== false
            || stripos($row['email'], $search) !== false;
    });
}

$contextid = context_course::instance($courseid)->id;
$studentdata = fetch_students::fetch_students($contextid, $courseid, 5, 0, 1000); // Role ID 5 = estudiantes
$students = $studentdata['students_data'];
// Filtrar resultado para dejar solo aprendices válidos
$usernames = array_column($students, 'username');
$result = array_filter($result, function ($row) use ($usernames) {
    return in_array($row['username'], $usernames);
});

// Enriquecer los datos con el estado (status)
foreach ($result as &$row) {
    foreach ($students as $student) {
        if ($row['username'] === $student['username']) {
            $row['status'] = $student['status'];
            break;
        }
    }
}
unset($row); // buena práctica para evitar problemas con el foreach por referencia

local_asistencia_external::attendance_report($result, $initialdate, $finaldate, $shortname);

if (isset($_GET['download']) && $_GET['download'] === '1') {
    // Ya enviamos el fichero: terminamos aquí.
    exit;
}
redirect($CFG->wwwroot . "/local/asistencia/history.php?courseid=$courseid&page=1&info=h&cumulous=$cumulous&filtro_fecha=0");
