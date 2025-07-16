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
namespace local_asistencia;

use core\dataformat;
use coding_exception;
use core_php_time_limit;
use stored_file;

defined('MOODLE_INTERNAL') || die();
// Incluir la libreria de Moodle para el PDF
require_once($CFG->libdir . '/classes/dataformat.php'); 

class detailed_report_donwloader extends dataformat
{
    public static function generate_report_pdf($filename, $arraydata, $dataformat, $userName, $shortname, $initialdate, $finaldate)
    {
        global $DB, $USER;
        $format = self::get_format_instance($dataformat);
        $title = "\n\n\n\n\nReporte detallado\n\n\n\n\n Curso:\n\n$shortname\n\n\nRango de fechas:\n\n$initialdate - $finaldate\n\n\n\n\nGenerado por el usuario:\n\n$userName\n\n\n\n\n\n\n\n\n\n";
        // El formato de datos puede tardar un tiempo en generarse.
        core_php_time_limit::raise();

        // Cerrar la sesión para que los usuarios no puedan acceder a otras pestañas en la misma sesión.
        \core\session\manager::write_close();


        // Si este archivo fue solicitado desde un formulario, entonces marcar la descarga como completa (antes de enviar los encabezados).
        \core_form\util::form_download_complete();

        $format->set_filename($filename);
        $format->send_http_headers();

        $format->start_output();
        $format->start_sheet(["$title"]);
        $format->close_sheet(["$title"]);
        $rownum = 0;
        foreach ($arraydata as $person) {
            $docentesData = array_filter($person, 'is_array');
        
            // Si no tiene datos, tabla con mensaje
            if (empty($docentesData)) {
                $headers = ["Estudiante", "Observación"];
                $format->start_sheet($headers);
                $format->write_record([
                    $person['userinfo'],
                    "Sin asistencia registrada en el rango seleccionado"
                ], 0);
                $format->close_sheet($headers);
        
                // Espacio entre tablas
                $format->write_record([null], 0); // o incluso [""]


            //$format->start_sheet([" "]);
         /*    $format->write_record([" "], 0);
            $format->write_record([" "], 0); */
            //$format->close_sheet([" "]);
        
                continue;
            }
        
            // Tiene asistencia, generar tabla normal
            $firstDocente = reset($docentesData);
            $headers = self::headersDays([$person['userinfo']], $firstDocente);
            $format->start_sheet($headers);
        
            foreach ($docentesData as $docenteData) {
                $row = array_merge([$person['userinfo']], $docenteData);
                $format->write_record($row, 0);
            }
        
            $format->close_sheet($headers);
        
            // Espacio entre tablas
            $format->write_record([null], 0); // o incluso [""]


            //$format->start_sheet([" "]);
          /*   $format->write_record([" "], 0);
            $format->write_record([" "], 0); */
            //$format->close_sheet([" "]);
        }
        

        $format->close_output();

        try {
            $toinsert = new \stdClass;
            $toinsert->code = "40";
            $toinsert->message = "Reporte detallado generado con nombre \"$filename\".";
            $toinsert->date = date("Y-m-d H:i:s", time());
            $toinsert->userid = $USER->id;
            $DB->insert_record("local_asistencia_logs", $toinsert);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    // Funcion para generar los encabezados de los dias
    private static function headersDays($array, $days)
    {

        foreach ($days as $key => $v) {
            $array[$key] = $key;
        }
        return $array;
    }

    // Funcion para generar el reporte de actividad
    public static function activityReport($filename, $userid)
    {
        global $DB;

        \core\notification::add("No hay información de asistencia.", \core\output\notification::NOTIFY_ERROR);
        try {
            $toinsert = new \stdClass;
            $toinsert->code = "43";
            $toinsert->message = "El reporte \"$filename\" no pudo ser generado. No se encontró información en la tabla.";
            $toinsert->date = date("Y-m-d H:i:s", time());
            $toinsert->userid = $userid;
            $DB->insert_record("local_asistencia_logs", $toinsert);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

}
