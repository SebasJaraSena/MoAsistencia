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
$filtro_fecha = $_GET['filtro_fecha'] ;
$initialdate = $_GET['initialdate'];
$finaldate = $_GET['finaldate'];
$cumulous = $_GET['cumulous'];
$studentstatus = $_GET['studentstatus'] ?? '';
$search = trim($_GET['search'] ?? '');
$attendancefilter = $_GET['attendancefilter'] ?? '';
$teacherid = $_GET['teacherid'] ?? '';
$selected_sessionid = $_GET['sessionid'] ?? '';


// Si hay filtro por instructor, solo mostrar sus asistencias (como si fuera "mi asistencia")
if (!empty($teacherid) && $cumulous == 1) {
    $cumulous = 0; // Cambia a modo 'personal'
    $userid = $teacherid; // El usuario a filtrar es el instructor seleccionado
}

// Obtener el URL de los datos
$urldata = $_GET['urldata'] ?? null;
// Obtener los datos
$data = !empty($urldata) ? json_decode($urldata, true) : [];
// Obtener el ID del curso
$courseid = $_GET['courseid'];
// Configurar la URL
$PAGE->set_url(new moodle_url('/local/asistencia/downloader.php'));
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_title('Descargar reporte');
// Obtener el contexto del sistema
global $CFG, $DB;
// Obtener el historial de asistencia
$attendancehistory = json_decode(json_encode($DB->get_records('local_asistencia_permanente', ['course_id' => $courseid])), true);
// Obtener el nombre corto del curso
$shortname = json_decode(json_encode($DB->get_record('course', ['id' => $courseid], 'shortname')), true)['shortname'];
// Obtener el resultado de la asistencia
$result = local_asistencia_external::fetch_attendance_report($attendancehistory, $initialdate, $finaldate, $cumulous, $userid, $selected_sessionid);
// Obtener el contexto del sistema
$contextid = context_course::instance($courseid)->id;
// Obtener los datos de los estudiantes
$studentdata = fetch_students::fetch_students($contextid, $courseid, 5, 0, 1000); // Role ID 5 = estudiantes
// Obtener los datos de los estudiantes
$students = $studentdata['students_data'];
// Filtrar resultado para dejar solo aprendices válidos
// Normalizar cada fila de $result: separar número y sufijo en username_clean e idtype
foreach ($result as &$row) {
    $raw = trim($row['username'] ?? '');
    if (preg_match('/^(\d+)(CC|TI|CE|PEP|PPT)$/i', $raw, $m)) {
        $row['username_clean'] = $m[1];
        $row['idtype']         = strtoupper($m[2]);
    } else {
        $row['username_clean'] = preg_replace('/\D+/', '', $raw);
        $row['idtype']         = '';
    }
}
unset($row);

// Filtrar usando la parte numérica (username_clean)
$userdocs = array_column($students, 'username');
$result = array_filter($result, function($row) use ($userdocs) {
    return in_array($row['username_clean'], $userdocs);
});

// Obtener el contexto del sistema
// Enriquecer los datos con el estado (status)
// Comparar usando el campo limpio username_clean
foreach ($result as &$row) {
    foreach ($students as $student) {
        if (ltrim($row['username_clean'], '0') === ltrim($student['username'], '0')) {
            $row['status'] = $student['status'];
            break;
        }
    }
}

//Filtro de estado por activo e inactivo
unset($row);
if ($studentstatus !== '') {
    $result = array_filter($result, function($row) use ($studentstatus) {
        return (string)$row['status'] === (string)$studentstatus;
    });
}

// Filtrado de búsqueda (incluye idtype aunque esté vacío)
if (!empty($search)) {
    // Función para normalizar texto: quita tildes y pasa a minúsculas
    $normalize = function($t) {
        return mb_strtolower(
            preg_replace('/[\p{Mn}]/u','',
                iconv('UTF-8','UTF-8//IGNORE',$t)
            )
        );
    };
    $q = $normalize($search);
    // Filtrar los resultados
    $result = array_filter($result, function($row) use ($q, $normalize) {
        // Concatenamos todos los campos relevantes en un solo string
        $haystack = implode(' ', [
            $row['username_clean'],  // número de documento
            $row['lastname'],        // apellidos
            $row['firstname'],       // nombres
            $row['email'],           // correo
            $row['idtype'],          // tipo de identificación (puede estar vacío)
        ]);

        return strpos($normalize($haystack), $q) !== false;
    });
}

if ($attendancefilter !== '') {
    // Normalizar valor del filtro a texto para comparar con los 'stateX'
    $attendance_states = [
        '0' => 'INCUMPLIMIENTO_INJUSTIFICADO',
        '1' => 'ASISTIÓ',
        '2' => 'INASISTENCIA_NO_PROGRAMADA',
        '3' => 'INASISTENCIA_PROGRAMADA'
    ];

    $target_state = $attendance_states[$attendancefilter] ?? null;

    if ($target_state) {
        $result = array_filter($result, function($row) use ($target_state) {
            foreach ($row as $key => $value) {
                if (strpos($key, 'state') === 0 && $value === $target_state) {
                    return true; // Tiene al menos una coincidencia
                }
            }
            return false;
        });
    }
}

// Obtener el resultado de la asistencia
local_asistencia_external::attendance_report($result, $initialdate, $finaldate, $shortname);
// Verificar si estamos en modo descarga
// Si estamos en modo descarga, salimos (únicamente cuando attendance_report envía el fichero)
if (isset($_GET['download']) && $_GET['download'] === '1') {
    exit;
}
// Redirigir a history.php e incluir el mensaje de "no hay info" como parámetro de notificación
// Al final de downloader.php, reemplaza tu redirect actual por esto:
// Redirigir y preservar search para que vuelva al mismo filtro
$params = [
    'courseid'     => $courseid,
    'page'         => 1,
    'info'         => 'h',
    'cumulous'     => $cumulous,
    'filtro_fecha' => $filtro_fecha,
    'noinfo'       => 1,  
];

// Añadir fechas si se han establecido
if (!empty($initialdate)) {
    $params['initial'] = $initialdate;
}
if (!empty($finaldate)) {
    $params['final'] = $finaldate;
}
if (!empty($finaldate)) {
    $params['final'] = $finaldate;
}
// Si había un filtro, agregarlo
if (!empty($studentstatus)) {
    $params['studentstatus'] = $studentstatus;
}
$url = new moodle_url('/local/asistencia/history.php', $params);
redirect($url);