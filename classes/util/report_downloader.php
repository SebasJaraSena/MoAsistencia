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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class report_donwloader
{
    public static function attendance_report($result, $initialdate, $finaldate, $shortname)
    {
        global $DB, $USER;

        require_once($GLOBALS['CFG']->libdir . '/excellib.class.php');
        $filename = 'reporte_asistencia_' . $shortname . '_' . $initialdate . '_' . $finaldate . '.xlsx';

        // Filtrar estudiantes con asistencia
        $attendanceFound = array_filter($result, function ($row) {
            return isset($row['day0']);
        });

        if (!empty($attendanceFound)) {

            // 1. Recoger todas las fechas únicas
            $dateSet = [];
            foreach ($result as $row) {
                foreach ($row as $key => $value) {
                    if (preg_match('/^day(\d+)$/', $key, $matches)) {
                        $dateSet[$row["day{$matches[1]}"]] = true;
                    }
                }
            }

            // 2. Ordenar cronológicamente
            $dates = array_keys($dateSet);
            sort($dates);

            // 3. Cabeceras 

            $fixedHeaders = ['username', 'firstname', 'lastname', 'email', 'phone1', 'status'];
            $headers = array_map(function ($header) {
                switch ($header) {
                    case 'username':
                        return 'Número de documento';
                    case 'firstname':
                        return 'Nombres';
                    case 'lastname':
                        return 'Apellidos';
                    case 'email':
                        return 'Correo electrónico';
                    case 'phone1':
                        return 'Tipo de identificación';
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


            // 4. Reorganizar por estudiante
            $sortedData = [];
            foreach ($result as $row) {
                $studentFixedData = [];
                foreach ($fixedHeaders as $field) {
                    if ($field === 'status') {
                        $statusValue = isset($row[$field]) ? (int) $row[$field] : 0;
                        $studentFixedData[] = $statusValue === 1 ? 'SUSPENDIDO' : 'ACTIVO';
                    } else {
                        $studentFixedData[] = $row[$field] ?? '';
                    }
                }

                $dayState = [];
                $dayTime = [];
                $dayTeacher = [];
                $dayObservation = [];
                $i = 0;
                while (isset($row["day$i"])) {
                    $date = $row["day$i"];
                    $state = $row["state$i"] ?? '';
                    $time = $row["time$i"] ?? '';
                    $observation = $row["observation$i"] ?? '';
                    $teacher = $row["teacher$i"] ?? '';

                    $dayState[$date][] = $state;
                    $dayTime[$date][] = $time;
                    // Solo guarda la observación si hay contenido real
                    if (trim($observation) !== '') {
                        $dayObservation[$date][] = $observation;
                    }
                    $dayTeacher[$date][] = $teacher;
                    $i++;
                }

                $finalRow = $studentFixedData;
                foreach ($dates as $date) {
                    $finalRow[] = isset($dayState[$date])
                        ? implode("\n", array_map(function ($v, $i) {
                            return ($i + 1) . '. ' . $v;
                        }, $dayState[$date], array_keys($dayState[$date])))
                        : '';

                    $finalRow[] = isset($dayTime[$date])
                        ? implode("\n", array_map(function ($v, $i) {
                            return ($i + 1) . '. (' . $v . ' horas)';
                        }, $dayTime[$date], array_keys($dayTime[$date])))
                        : '';

                    $finalRow[] = isset($dayObservation[$date])
                        ? implode("\n", array_map(function ($v, $i) {
                            return ($i + 1) . '. ' . $v;
                        }, $dayObservation[$date], array_keys($dayObservation[$date])))
                        : '';

                    $finalRow[] = isset($dayTeacher[$date])
                        ? implode("\n", array_map(function ($v, $i) {
                            return ($i + 1) . '. ' . $v;
                        }, $dayTeacher[$date], array_keys($dayTeacher[$date])))
                        : '';
                }

                $sortedData[] = $finalRow;
            }

            // 5. Crear y guardar Excel
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Reporte');

            $instructor = fullname($USER);

            $sheet->setCellValue('A1', "Curso: $shortname");
            $sheet->setCellValue('B1', "Fecha: $initialdate a $finaldate");
            $sheet->setCellValue('C1', "Usuario : $instructor");

            $sheet->fromArray($headers, NULL, 'A2');
            $lastColumn = $sheet->getHighestColumn();
            $sheet->getStyle("A2:{$lastColumn}2")->getFont()->setBold(true);
            $sheet->fromArray($sortedData, NULL, 'A3');

            $tmp = sys_get_temp_dir() . "/$filename";
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tmp);

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
                $toinsert->date = date("Y-m-d H:i:s", time());
                $toinsert->userid = $USER->id;
                $DB->insert_record("local_asistencia_logs", $toinsert);
            } catch (\Throwable $th) {
                // Silenciar errores
            }

        } else {
            \core\notification::add("No hay información de asistencia en el rango de fechas.", \core\output\notification::NOTIFY_ERROR);
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
