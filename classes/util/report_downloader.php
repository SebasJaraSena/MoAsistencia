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

require_once($CFG->libdir . '/excellib.class.php'); // Include Moodle's built-in Excel library
require_once(__DIR__ . '/../../../../config.php'); // Asegúrate de cargar el entorno Moodle
require_once($GLOBALS['CFG']->libdir . '/excellib.class.php');
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//clase report_donwloader
class report_donwloader
{
    //funcion para generar el reporte de asistencia
    public static function attendance_report($result, $initialdate, $finaldate, $shortname)
    {
        global $DB, $USER;
        session_write_close();

        //generar el nombre del archivo
        $downloadDate = date('Y-m-d_H-i-s');
        $filename = 'reporte_asistencia_' . $shortname . '_' . $downloadDate . '.xlsx';

        //filtrar estudiantes con asistencia
        $attendanceFound = array_filter($result, function ($row) {
            return isset($row['day0']);
        });

        // si hay estudiantes con asistencia
        if (!empty($attendanceFound)) {

            //Recoger todas las fechas únicas
            foreach ($result as $row) {
                $i = 0;
                while (isset($row["day$i"])) {
                    $date = $row["day$i"];
                    $teacher = $row["teacher$i"] ?? '';
                    $key = $date . '|' . $teacher; // clave única por fecha y profesor/sesión
                    $dateSet[$key] = $date; // guardamos solo la fecha como valor
                    $i++;
                }
            }

            // Ordenar cronológicamente
            $dates = array_values($dateSet); // solo los valores (las fechas puras)

            sort($dates);

            // Cabeceras 
            $fixedHeaders = ['idtype', 'username_clean', 'firstname', 'lastname', 'email', 'status'];
            $headers = array_map(function ($header) {
                switch ($header) {
                    case 'idtype':
                        return 'Tipo de documento';
                    case 'username_clean':
                        return 'Número de documento';
                    case 'firstname':
                        return 'Nombres';
                    case 'lastname':
                        return 'Apellidos';
                    case 'email':
                        return 'Correo electrónico';
                    case 'status':
                        return 'Estado del Aprendiz';
                    default:
                        return $header;
                }
            }, $fixedHeaders);

            // Agregar cabeceras dinámicas por fecha
            foreach ($dates as $date) {
                $headers[] = "$date - Asistencia";
                $headers[] = "$date - Horas";
                $headers[] = "$date - Observaciones";
                $headers[] = "$date - Instructor";
            }

            // Reorganizar por estudiante
            $sortedData = [];
            foreach ($result as $row) {

                // — EXTRA: extraer idtype y limpiar username —
                $rawUsername = trim($row['username'] ?? '');
                if (preg_match('/^(\d+)(\D+)$/i', $rawUsername, $m)) {
                    // m[1] = todos los dígitos, m[2] = sufijo no numérico
                    $row['username'] = $m[1];
                    $row['idtype'] = strtoupper($m[2]);
                } else {
                    // si no hay sufijo, dejamos sólo dígitos y vaciamos idtype
                    $row['username'] = preg_replace('/\D+/', '', $rawUsername);
                    $row['idtype'] = '';
                }

                // Inicializar arrays de asistencia y flag
                $dayState = [];
                $dayTime = [];
                $dayObservation = [];
                $dayTeacher = [];
                $hasAttendance = false;

                // Recolectar datos por día y marcar si hay asistencia
                $i = 0;
                while (isset($row["day$i"])) {
                    $date = $row["day$i"];
                    $state = trim($row["state$i"] ?? '');
                    $time = trim($row["time$i"] ?? '');
                    $obs = trim($row["observation$i"] ?? '');
                    $teacher = trim($row["teacher$i"] ?? '');

                    $dayState[$date][] = $state;
                    $dayTime[$date][] = $time;
                    $dayObservation[$date][] = $obs !== '' ? $obs : 'Sin observación';
                    $dayTeacher[$date][] = $teacher;

                    if ($state !== '' || $time !== '') {
                        $hasAttendance = true;
                    }

                    $i++;
                }

                // Si nunca hubo asistencia, saltamos este registro
                if (!$hasAttendance) {
                    continue;
                }

                // Armar la parte fija de la fila (incluye ahora idtype correctamente)
                $studentFixedData = [];
                foreach ($fixedHeaders as $field) {
                    if ($field === 'status') {
                        $val = isset($row[$field]) ? (int) $row[$field] : 0;
                        $studentFixedData[] = $val === 1 ? 'SUSPENDIDO' : 'ACTIVO';
                    } else {
                        $studentFixedData[] = $row[$field] ?? '';
                    }
                }
                //  Añadir datos dinámicos fecha a fecha
                $finalRow = $studentFixedData;
                foreach ($dates as $date) {
                    // Estado
                    $finalRow[] = isset($dayState[$date])
                        ? implode("\n", array_map(
                            function ($v, $i) {
                                return ($i + 1) . '. ' . $v;
                            },
                            $dayState[$date],
                            array_keys($dayState[$date])
                        ))
                        : '';
                    // Horas
                    $finalRow[] = isset($dayTime[$date])
                        ? implode("\n", array_map(
                            function ($v, $i) {
                                return ($i + 1) . '. (' . $v . ' horas)';
                            },
                            $dayTime[$date],
                            array_keys($dayTime[$date])
                        ))
                        : '';
                    // Observaciones
                    $finalRow[] = isset($dayObservation[$date])
                        ? implode("\n", array_map(
                            function ($v, $i) {
                                return ($i + 1) . '. ' . $v;
                            },
                            $dayObservation[$date],
                            array_keys($dayObservation[$date])
                        ))
                        : '';
                    // Instructor
                    $finalRow[] = isset($dayTeacher[$date])
                        ? implode("\n", array_map(
                            function ($v, $i) {
                                return ($i + 1) . '. ' . $v;
                            },
                            $dayTeacher[$date],
                            array_keys($dayTeacher[$date])
                        ))
                        : '';
                }

                $sortedData[] = $finalRow;
            }

            // Crear y guardar Excel en el servidor
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Reporte');

            //generar el nombre del instructor
            $instructor = fullname($USER);
            $sheet->setCellValue('A1', "Curso: $shortname");
            $sheet->setCellValue('B1', "Fecha: $initialdate a $finaldate");
            $sheet->setCellValue('C1', "Usuario : $instructor");

            $sheet->fromArray($headers, NULL, 'A2');
            $lastColumn = $sheet->getHighestColumn();
            $sheet->getStyle("A2:{$lastColumn}2")->getFont()->setBold(true);
            $sheet->fromArray($sortedData, NULL, 'A3');

            //generar el archivo temporal
            $tmp = sys_get_temp_dir() . "/$filename";
            //generar el escritor   
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tmp);

            //generar los encabezados
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"$filename\"");
            header('Content-Length: ' . filesize($tmp));
            readfile($tmp);
            unlink($tmp);

            // Log
            try {
                $toinsert = new stdClass;
                $toinsert->code = "40";
                $toinsert->message = "Reporte generado con nombre \"$filename\".";
                $toinsert->date = date("Y-m-d H:i:s");
                $toinsert->userid = $USER->id;
                $DB->insert_record("local_asistencia_logs", $toinsert);
            } catch (\Throwable $th) {
                // Silenciar errores
            }
            exit;
        } else {
            \core\notification::add("No hay información de asistencia.", \core\output\notification::NOTIFY_ERROR);
            try {
                $toinsert = new stdClass;
                $toinsert->code = "43";
                $toinsert->message = "El reporte \"$filename\" no pudo ser generado. No se encontró información en la tabla.";
                $toinsert->date = date("Y-m-d H:i:s", time());
                $toinsert->userid = $USER->id;
                $DB->insert_record("local_asistencia_logs", $toinsert);
            } catch (\Throwable $th) {
                // Silenciar errores
            }
        }
    }

}
