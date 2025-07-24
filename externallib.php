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
 * Plugin capabilities for the block_asistencia plugin.
 *
 * @package   local_asistencia
 * @copyright Equipo zajuna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_asistencia\detailed_report_donwloader;

require_once(__DIR__ . '/classes/external/fetch_students.php');
require_once(__DIR__ . '/classes/external/fetch_activities.php');
require_once(__DIR__ . '/classes/external/foreing_db_connection.php');
require_once(__DIR__ . '/classes/form/edit.php');
require_once(__DIR__ . '/classes/util/report_downloader.php');
require_once(__DIR__ . '/classes/util/detail_report_downloader.php');

// Clase para la conexión externa
class local_asistencia_external
{
    // Función para obtener los estudiantes
    public static function fetch_students($contextid, $courseid, $roleid, $offset, $limit = 10, $condition)
    {
        return fetch_students::fetch_students($contextid, $courseid, $roleid, $offset, $limit, $condition);
    }
    // Función para editar
    public static function edit()
    {
        /*  return edit::definition(); */
        $form = new edit();
        $form->definition();

    }
    // Función para ejecutar una consulta
    public static function query($query, $params = [])
    {
        $thequery = new foreing_db_connection();
        return $thequery->query($query, $params);
    }
    // Función para obtener el reporte de asistencia
    public static function fetch_attendance_report($attendancehistory, $initialdate, $finaldate, $cumulous, $userid, $sessionid = '')
    {
        return fetch_activities::fetch_attendance_report($attendancehistory, $initialdate, $finaldate, $cumulous, $userid, $sessionid);
    }
    // Función para obtener el reporte de asistencia detallado
    public static function fetch_attendance_report_detailed($attendancehistory, $initialdate, $finaldate, $cumulous, $userid)
    {
        return fetch_activities::fetch_attendance_report_detailed($attendancehistory, $initialdate, $finaldate, $cumulous, $userid);
    }
    // Función para obtener el reporte de actividades
    public static function fetch_activities_report($courseid)
    {
        return fetch_activities::fetch_activities_report($courseid);
    }
    // Función para obtener el reporte de asistencia
    public static function attendance_report($data, $initaldate, $finaldate, $shortname)
    {
        return report_donwloader::attendance_report($data, $initaldate, $finaldate, $shortname);
    }
    // Función para obtener el reporte de asistencia detallado
    public static function attendance_detailed_report($filename, $arraydata, $dataformat, $userName, $shortname, $initialdate, $finaldate)
    {
        return detailed_report_donwloader::generate_report_pdf($filename, $arraydata, $dataformat, $userName, $shortname, $initialdate, $finaldate);
    }
    // Función para cerrar la validación
    public static function close_validation($courseid)
    {
        return fetch_students::close_validation($courseid);
    }
    // Función para cerrar la validación de retardos    
    public static function close_validation_retard($courseid, $initial, $final)
    {
        return fetch_students::close_validation_retard($courseid, $initial, $final);
    }
    // Función para obtener el reporte de actividades
    public static function activityReport($filename, $userid)
    {
        return detailed_report_donwloader::activityReport($filename, $userid);
    }
}