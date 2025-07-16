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
 * Plugin capabilities for the local_asistencia plugin.
 *
 * @package   local_asistencia
 * @copyright Equipo zajuna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
// Clase para obtener el reporte de estudiantes
class fetch_students
{
    // Función para obtener el reporte de estudiantes
    public static function fetch_students($contextid, $courseid, $roleid, $offset, $limit, $conditions = '')
    {
        global $DB;

        $students_cant = $DB->count_records('enrol', ['enrol' => 'manual', 'courseid' => $courseid]);

        // Verificar si el curso existe
        if (!$students_cant) {
            return false;
        }
        // Obtener el id del registro de matrícula manual para el valor de $courseid
        $enrolid = $DB->get_record('enrol', ['enrol' => 'manual', 'courseid' => $courseid], 'id');

        // Obtener todos los registros que están relacionados con el id de matrícula
        $enrollments = array_values(self::fetch_user_enrolments($enrolid->id));
        $enrollmentscopy = self::into_array_values($enrollments);
        // Construir la cadena para consultar la información de los usuarios
        $querystring = self::user_query_string($enrollments);

        // Obtener solo los usuarios que pertenecen al curso y que fueron asignados como estudiantes
        $studentsids = array_values(self::fetch_user_roles($querystring, $roleid, $contextid));

        // Construir la cadena para consultar la información de los usuarios
        $querystring = self::user_query_string($studentsids) ?? '';

        // Obtener la información principal de los usuarios
        $query = empty($conditions)
            ? 'SELECT id, username, lastname, firstname, email FROM {user} WHERE id IN (' . $querystring . ') ORDER BY lastname ASC OFFSET ' . ($offset * $limit) . ' LIMIT ' . $limit
            : 'SELECT id, username, lastname, firstname, email FROM {user} WHERE id IN (' . $querystring . ') ' . $conditions . ' ORDER BY lastname ASC';

        // Obtener la información de los estudiantes
        $studentsinfo = !empty($querystring) ? $DB->get_records_sql($query) : [];

        // Formato de la información de los estudiantes
        $students_data = [];
        // Formato de la información de los estudiantes
        foreach ($studentsinfo as $studentinfo) {
            $id = $studentinfo->id;
            $username = $studentinfo->username;

            // 1. Extraer número y sufijo del documento
            $tipo = '';
            if (preg_match('/^(\d+)(CC|TI|CE|PEP|PPT)$/i', $username, $m)) {
                // $m[1] = solo dígitos, $m[2] = CC|TI|… (insensible a mayúsculas)
                $documento = $m[1];
                $tipo = strtoupper($m[2]);
            } else {
                // Si no coincide, mantenemos todo como documento y tipo vacío
                $documento = $username;
            }

            // 2. Buscamos el estado de matrícula como antes
            $filtered_array = array_values(array_filter($enrollmentscopy, function ($item) use ($id) {
                return $item['userid'] == $id;
            }));

            // 3. Armamos el array incluyendo el nuevo campo 'tipo' y, opcionalmente,
            //    si quieres renombrar username → documento, podrías usar 'documento' en lugar de 'username'.
            $student_data = [
                'id' => $id,
                // tu plantilla hoy usa {{username}} para Documento; lo dejamos así:
                'username' => $documento,
                // nuevo campo para que la segunda columna sea {{tipo}}
                'tipo' => $tipo,
                'lastname' => $studentinfo->lastname,
                'firstname' => $studentinfo->firstname,
                'email' => $studentinfo->email,
                'status' => $filtered_array[0]['status'],
            ];
            $students_data[] = $student_data;
        }

        // Devolver los datos de los estudiantes formateados
        return [
            'students_data' => $students_data,
            'pages' => empty($conditions) ? (int) ceil(count($studentsids) / $limit) : ceil(count($students_data) / $limit),
            'studentsamount' => count($studentsids)
        ];
    }

    // Función para obtener todas las inscripciones relacionadas con enrolid
    public static function fetch_user_enrolments($enrolid)
    {
        global $DB;

        // Obtener las inscripciones de los usuarios
        $userenrolments = $DB->get_records('user_enrolments', ['enrolid' => $enrolid], '', 'userid, status');

        return $userenrolments;
    }

    // Función para obtener todos los estudiantes relacionados con un contexto y sus ID de usuario
    public static function fetch_user_roles($querystring, $roleid, $contextid)
    {
        global $DB;

        // Obtener las inscripciones de los usuarios
        $userenrolments = empty($querystring) ? [] : $DB->get_records_sql('SELECT userid FROM {role_assignments} WHERE roleid=:roleid AND contextid = :contextid AND userid IN (' . $querystring . ')', array('roleid' => $roleid, 'contextid' => $contextid));

        return $userenrolments;
    }

    // Función para crear una cadena de consulta con ID de usuario
    public static function user_query_string($array)
    {
        $string = '';
        foreach ($array as $item) { // Ciclo para agregar los ids a una cadena de texto separada por ","
            $string .= $item->userid . ',';
        }

        return rtrim($string, ","); // Se retrna la cadena generada eliminando la última ","
    }

    // Función para transformar todos los elementos en valores de matriz
    public static function into_array_values($array)
    {
        $len = count($array);
        for ($i = 0; $i < $len; $i++) { // Ciclo para convertir la información en arreglos asociativos
            $array[$i] = get_object_vars($array[$i]);
        }

        return $array;
    }

    // Función para verificar si la asistencia debe estar cerrada o abierta para ser editada
    public static function close_validation($courseid)
    {
        global $USER, $DB;
        $userid = $USER->id;
        $currentdate = date('Y-m-d', time());
        $context = context_course::instance($courseid);

        $historyattendance = [];
        try {
            $historyattendance = $DB->get_record('local_asistencia_permanente', ['course_id' => $courseid]); // Consulta de tabla histórica de base de datos
            $adminrole = $DB->get_record('role_assignments', ['contextid' => $context->id, 'userid' => $userid], 'roleid');
            $admin = $adminrole ? $adminrole->roleid != 5 : $adminrole;
        } catch (\Throwable $th) {
            //throw $th;
        }

        $attendacearray = [];
        if ($admin && isset($historyattendance) && isset($historyattendance->full_attendance)) {
            $attendacearray = json_decode($historyattendance->full_attendance, true) ?? [];
        }

        $filtered = !empty($attendacearray) ? array_filter($attendacearray, function ($item) use ($userid, $currentdate) { // Filtro para encontrar los datos relacionados con el id del instructor
            return $item['TEACHER_ID'] == $userid && $item['DATE'] == $currentdate;
        }) : [];

        // Retorna  si el usuario puede editar o no
        return !empty($admin) && empty($filtered) ? 0 : 1;

    }

    // Función para verificar si la asistencia debe estar cerrada o abierta para ser editada
    public static function close_validation_retard($courseid, $initial, $final)
    {
        global $USER, $DB;
        $userid = $USER->id;
        $context = context_course::instance($courseid);

        $historyattendance = [];
        try {
            $historyattendance = $DB->get_record('local_asistencia_permanente', ['course_id' => $courseid]); // Consulta de tabla histórica de base de datos
            $adminrole = $DB->get_record('role_assignments', ['contextid' => $context->id, 'userid' => $userid], 'roleid');
            $admin = $adminrole ? $adminrole->roleid != 5 : $adminrole;
        } catch (\Throwable $th) {
            //throw $th;
        }

        $attendacearray = [];
        if ($admin && isset($historyattendance) && isset($historyattendance->full_attendance)) {
            $attendacearray = json_decode($historyattendance->full_attendance, true) ?? [];
        }

        $filtered = !empty($attendacearray) ? array_filter($attendacearray, function ($item) use ($userid, $initial, $final) { // Filtro para encontrar los datos relacionados con el id del instructor
            return $item['TEACHER_ID'] == $userid && $item['DATE'] >= $initial && $item['DATE'] <= $final;
        }) : [];

        // Retorna  si el usuario puede editar o no
        return !empty($admin) && (count($filtered) < 7) ? 0 : 1;

    }
}
