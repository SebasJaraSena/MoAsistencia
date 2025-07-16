<?php
namespace local_asistencia\util;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/excellib.class.php');
require_once($CFG->dirroot . '/local/asistencia/externallib.php');

//clase history_report_downloader
class history_report_downloader
{

    //variable privada para la busqueda
    private $search;

    //constructor de la clase
    public function __construct($courseid = null, $search = '', $startdate = '', $enddate = '')
    {
        // El parámetro courseid se deja por compatibilidad, pero no se usa
        $this->search = trim(preg_replace('/\s+/', ' ', strtolower($search)));
        $this->startdate = $startdate;
        $this->enddate = $enddate;
    }

    //funcion para generar y descargar el historial de asistencia
    public function generate_and_download()
    {
        global $DB,$USER, $courseid;
        // Obtener todos los logs usando la misma función que history.php
        $activities = \local_asistencia_external::fetch_activities_report($courseid);
        $shortname = $DB->get_record('course', ['id' => $courseid], 'shortname')->shortname;
        $normalize = function ($text) {
            $text = mb_strtolower($text, 'UTF-8');
            $text = str_replace(
                ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
                ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
                $text
            );
            $text = preg_replace('/\s+/', ' ', $text); // eliminar espacios extra
            return trim($text);
        };

        // Aplicar el mismo filtro usado en history.php
        if (!empty($this->search)) {
            $searchterm = $normalize($this->search);

            $activities = array_filter($activities, function ($activity) use ($searchterm, $normalize) {
                if (is_object($activity)) {
                    $activity = (array) $activity;
                }

                // Buscar en campos específicos
                $fields = ['message', 'username', 'date'];

                foreach ($fields as $field) {
                    if (!empty($activity[$field])) {
                        $normalizedField = $normalize($activity[$field]);
                        if (strpos($normalizedField, $searchterm) !== false) {
                            return true;
                        }
                    }
                }

                return false;
            });
        }

        if (!empty($this->startdate) || !empty($this->enddate)) {
            $activities = array_filter($activities, function ($activity) {
                $logdate = strtotime($activity['date']);
                $from = !empty($this->startdate) ? strtotime($this->startdate . ' 00:00:00') : null;
                $to = !empty($this->enddate) ? strtotime($this->enddate . ' 23:59:59') : null;

                if ($from && $to) {
                    return $logdate >= $from && $logdate <= $to;
                } elseif ($from) {
                    return $logdate >= $from;
                } elseif ($to) {
                    return $logdate <= $to;
                }
                return true;
            });
        }

        //generar el nombre del archivo
        $filename = 'Reporte_logs_de_descarga_'.$shortname.'_'.date('Y-m-d_H-i') . '.xlsx';

        //generar el libro de trabajo
        $workbook = new \MoodleExcelWorkbook("-");
        $workbook->send($filename);
        $worksheet = $workbook->add_worksheet('Historial');

        $instructor = fullname($USER);
        $initialdate = $this->startdate;
        $finaldate = $this->enddate;

        // Escribir metadatos manualmente
        $worksheet->write(0, 0, "Curso: $shortname ");
        $worksheet->write(0, 1, "Usuario: $instructor");
        $worksheet->write(0, 2, "Fecha: $initialdate a $finaldate");

        // Encabezados en la siguiente fila
        $headers = ['MENSAJE', 'FECHA', 'NOMBRE USUARIO'];
        foreach ($headers as $col => $header) {
            $worksheet->write(1, $col, $header);
        }


        //generar las filas
        $row = 2;
        foreach ($activities as $record) {
            $worksheet->write($row, 0, $record['message']);
            $worksheet->write($row, 1, $record['date']);
            $worksheet->write($row, 2, $record['username']);
            $row++;
        }

        $workbook->close();
        exit;
    }
}
