<?php
namespace local_asistencia\util;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/excellib.class.php');
require_once($CFG->dirroot . '/local/asistencia/externallib.php');

class history_report_downloader
{

    private $search;

    public function __construct($courseid = null, $search = '')
    {
        // El parámetro courseid se deja por compatibilidad, pero no se usa
        $this->search = trim(preg_replace('/\s+/', ' ', strtolower($search)));
    }

    public function generate_and_download()
    {
        // Obtener todos los logs usando la misma función que history.php
        $activities = \local_asistencia_external::fetch_activities_report();

        // Aplicar el mismo filtro usado en history.php
        if (!empty($this->search)) {
            $searchterm = $this->search;

            $activities = array_filter($activities, function ($activity) use ($searchterm) {
                // Convertir a texto plano para búsqueda
                $flattext = implode(' ', array_map(function ($v) {
                    return is_scalar($v) ? $v : '';
                }, (array) $activity));

                $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $flattext)));

                return strpos($normalized, $searchterm) !== false;
            });
        }

        $filename = 'historial_asistencia_' . date('Ymd_His') . '.xlsx';

        $workbook = new \MoodleExcelWorkbook("-");
        $workbook->send($filename);
        $worksheet = $workbook->add_worksheet('Historial');

        $headers = [ 'MENSAJE', 'FECHA', 'NOMBRE USUARIO'];
        foreach ($headers as $col => $header) {
            $worksheet->write(0, $col, $header);
        }

        $row = 1;
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
