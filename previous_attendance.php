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

require_once(__DIR__ .'/../../config.php');
require_once(__DIR__.'/externallib.php');
require_once($CFG->dirroot . '/local/asistencia/lib.php');

require_login();

global $CFG, $USER;

// Creacion de cache
$cache = cache::make('local_asistencia', 'coursestudentslist');
$userid = $USER->id;
$courseid = $_GET['courseid'];
$attendancepage = $_GET['page']??1;
// $limit = $_GET['limit']??1;
$date = new DateTime(date('Y-m-d')); 
$weeks = isset($_GET['weeks']) && ($_GET['weeks']<=4 &&  $_GET['weeks']>=1)?$_GET['weeks']:1;
$weeks = isset($_POST['numweeks'])?$_POST['numweeks']:$weeks;
$startweek = clone $date;
$endweek = clone $date;
$initial = $date->format('l') == 'Monday'?$startweek->modify("-$weeks week")->format("Y-m-d"):$startweek->modify("-$weeks week")->modify("last monday")->format("Y-m-d");
$final =  $date->format('l') == 'Sunday'?$endweek->modify("-$weeks week")->format("Y-m-d"):$endweek->modify("-$weeks week")->modify("next sunday")->format("Y-m-d");

$close = local_asistencia_external::close_validation_retard($courseid, $initial, $final);
$context = context_course::instance($courseid);
$currenturl = new moodle_url('/local/asistencia/previous_attendance.php');
$dircomplement = explode("/",$currenturl->get_path());
$PAGE->set_url($currenturl);
$PAGE->set_context($context);
$PAGE->set_title("Lista Asistencia Semana $weeks");
$PAGE->requires->js_call_amd('local_asistencia/attendance_observations', 'init');
$PAGE->requires->css(new moodle_url('/local/asistencia/styles/styles.css', array('v'=> time())));

require_capability('local/asistencia:view', $context);

$a=0;

// Functions
function studentsFormatWeek($studentslist, $week, $cachehistoryattendance, $temporalattendance, $userid, $initial, $final, $a, $suspended) {
    global $DB;
    $weekdaysnames = ['Monday'=>0, 'Tuesday'=>1, 'Wednesday'=>2, 'Thursday'=>3, 'Friday'=>4, 'Saturday'=>5, 'Sunday'=>6];
    $totaldaysattendance = 0;

    foreach ($studentslist as $i => $student) {
        $studentid = $student['id'];
        $studentslist[$i]['week'] = $week;

        // Inicializar semana vacía
        for ($j = 0; $j < 7; $j++) {
            $studentslist[$i]['week'][$j]['selection'] = [
                'op-8' => 1,
                'op0' => 0,
                'op1' => 0,
                'op2' => 0,
                'op3' => 0,
            ];
            $studentslist[$i]['week'][$j]['edit'] = 0;
        }

        // Obtener asistencia permanente
        $filtered = array_filter($cachehistoryattendance, fn($item) => $item['student_id'] == $studentid);

        $jsonattendance = [];
        $firstKey = array_key_first($filtered);
        if ($firstKey !== null && isset($cachehistoryattendance[$firstKey]['full_attendance'])) {
            $jsonattendance = json_decode($cachehistoryattendance[$firstKey]['full_attendance'], true) ?? [];
        }

        $filtereddate = array_filter($jsonattendance, function ($item) use ($initial, $final, $userid) {
            return $item['DATE'] >= $initial && $item['DATE'] <= $final && $item['TEACHER_ID'] == $userid;
        });

        foreach ($filtereddate as $index => $value) {
            $a++;
            $jadate = DateTime::createFromFormat('Y-m-d', $value['DATE']);
            $dayIndex = $weekdaysnames[$jadate->format('l')];

            $studentslist[$i]['week'][$dayIndex]['selection'] = [
                'op-8' => 0,
                'op0' => 0,
                'op1' => 0,
                'op2' => 0,
                'op3' => 0,
            ];
            $studentslist[$i]['week'][$dayIndex]['selection']['op'.$value['ATTENDANCE']] = 1;
            $studentslist[$i]['week'][$dayIndex]['edit'] = 1;
            $studentslist[$i]['week'][$dayIndex]['missedhours'] = $value['AMOUNTHOURS'] ?? '';
            $studentslist[$i]['week'][$dayIndex]['observations'] = $value['OBERVATIONS'] ?? '';
        }

        // Obtener fecha de suspensión si aplica
        $suspensionDate = null;
        if ($student['status']) {
            $sql = "SELECT ue.timemodified
                    FROM {user_enrolments} ue
                    JOIN {enrol} e ON ue.enrolid = e.id
                    WHERE ue.userid = ? AND e.courseid = ? AND ue.status = 1
                    ORDER BY ue.timemodified DESC LIMIT 1";
            $params = [$studentid, $student['courseid'] ?? 0];
            if ($record = $DB->get_record_sql($sql, $params)) {
                $suspensionDate = date('Y-m-d', $record->timemodified);
            }
        }

        // Procesar asistencia temporal
        if (!empty($temporalattendance)) {
            $filteredtemp = array_filter(json_decode(json_encode($temporalattendance), true), fn($item) => $item['studentid'] == $studentid);

            foreach ($filteredtemp as $prevattndnc) {
                $day = DateTime::createFromFormat('Y-m-d', $prevattndnc['date'])->format('l');
                $dayIndex = $weekdaysnames[$day];

                // Solo permitir si es antes de suspensión o no está suspendido
                if (!$student['status'] || $prevattndnc['date'] < $suspensionDate) {
                    $studentslist[$i]['week'][$dayIndex]['selection'] = [
                        'op-8' => 0,
                        'op0' => 0,
                        'op1' => 0,
                        'op2' => 0,
                        'op3' => 0,
                    ];
                    $studentslist[$i]['week'][$dayIndex]['selection']['op'.$prevattndnc['attendance']] = 1;
                    $studentslist[$i]['week'][$dayIndex]['missedhours'] = $prevattndnc['amounthours'] != 0 ? $prevattndnc['amounthours'] : '';
                    $studentslist[$i]['week'][$dayIndex]['observations'] = $prevattndnc['observations'];
                }
            }
        }
    }

    $auxiliar = (count($studentslist) - $suspended) == 0 ? 0 : $totaldaysattendance / (count($studentslist) - $suspended);
    return [$studentslist, $auxiliar, $a];
}




function getWeekRange($initial): array{ // Funtion that establishes the range of days tha would be shown in the table header
    $week = ['Monday'=>'L', 'Tuesday'=>'M', 'Wednesday'=>'X', 'Thursday'=>'J', 'Friday'=>'V', 'Saturday'=>'S', 'Sunday'=>'D'];
    $fullweek=[];
    $date = new DateTime($initial);
    
    
    for ($i = 0 ; $i < 7 ; $i++){
        if ($i !== 0){
            $date->modify('+1 day');
        }
        $fullweek[] = [
            'day'=> $week[$date->format('l')],
            'date'=> $date->format('d/m'),
            'fulldate'=> $date->format('Y-m-d'),
        ];
    }
    
    // Format the dates to your preferred format
    return $fullweek;
}

// Se obtiene información guardada en caché

$attendance_info=[];

$historyattendance = $DB->get_records('local_asistencia_permanente', ['course_id'=> $courseid]);

$cache->set("H_$courseid", json_encode($historyattendance));
$cachehistoryattendance = json_decode($cache->get("H_$courseid"), true);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['attendance'], $_POST['extrainfo'], $_POST['extrainfoNum'], $_POST['courseid'], $_POST['teacherid'])) {
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
    }

    $DB->delete_records('local_asistencia', ['courseid' => $courseid]);
    //redirect($CFG->wwwroot . "/local/asistencia/previous_attendance.php?courseid=$courseid&page=1&range=0");
    $url = new moodle_url('/local/asistencia/previous_attendance.php', [
        'courseid' => $courseid,
        'page' => 1,
        'range' => 0
        ]);
        echo $OUTPUT->header();
echo $OUTPUT->notification("Guardando asistencia, redireccionando...", 'notifysuccess');
echo "<script>setTimeout(function(){ window.location.href = '{$url}'; }, 50);</script>";
echo $OUTPUT->footer();
exit;
}

$pageurl = $attendancepage-1??0;
$currentpage = $pageurl+1;

$weekRange = getWeekRange($initial);

$form = new edit();

$condition='';


if ($form->is_cancelled()){
    $condition='';
}
else if ($fromform = $form->get_data()){
    $filterarrayoptionstraslate = ['firstname', 'lastname', 'email', 'username'];
    foreach($fromform as $key => $value){
        if ($key != 'submitbutton'){
            if ($key == 'filters' && $value >= 0 && $value <=3){
                $condition = "AND UPPER(".$filterarrayoptionstraslate[$value].")";
            } elseif($key == 'filterValue'){
                $value = in_array('email', explode(" ",$condition))?$value:strtoupper($value);
                if(!empty($value)){
                    $condition.= " LIKE '%$value%' ";
                }else{
                    $condition='';
                }
                
            }
        }
    }
}

    $cache->set("course$courseid.user$userid", $condition);
    local_asistencia_setup_breadcrumb('Asistencia anterior');
    echo $OUTPUT->header();
    $userid = $USER->id;
    $adminsarray = explode(",",$DB->get_record('config', ['name' => 'siteadmins'])->value);
    $configbutton = in_array($userid, $adminsarray)?1:0;
    $pages_attendance_string = $cache->get('attendancelist'.$courseid);
    $cache->delete('attendancelist'.$courseid);
    
    if(isset($pages_attendance_string)){
        $pages_attendance_array = json_decode($pages_attendance_string, true);
        
        $students = local_asistencia_external::fetch_students($context->id, $courseid, 5, $pageurl,/*$limit=='1'?10:*/10000, $condition);
        $supended = array_filter($students['students_data'], function($item) {
            return $item['status'] == 1;
        });
        $pages_attendance_array['pages'] = $students['pages'];
        $pages_attendance_array[$attendancepage] = $students['students_data'];
        $studentsamount= $students['studentsamount'];
        
        $pages_attendance_array_copy = $pages_attendance_array;
        $listlimit = count($pages_attendance_array_copy[$attendancepage]);
        $cache->set('attendancelist'.$courseid, json_encode($pages_attendance_array));
        $test=$cache->get('attendancelist'.$courseid);
        $studentslist = json_decode($test, true);
    }
        
    for($page = 1; $page <= $pages_attendance_array['pages']; $page++){
        if ($page === 1){
            $pages[$page] =[
                'page' => $page,
                'current' => $page==$pageurl+1,
                'active' => ''
            ];
        }
        if(($page === 2 || $page === $pages_attendance_array['pages']-1) && abs($currentpage-$page) >= 3 ){
            $pages[$page] = [
                'page'=> '...',
                'current'=> false,
                'active' => 'disabled'
            ];
        }
        if (abs($page - $currentpage) < 3){
            $pages[$page] =[
                'page' => $page,
                'current' => $page==$pageurl+1,
                'active' => ''
            ];
        }
        if ($page === $pages_attendance_array['pages']){
            $pages[$page] =[
                'page' => $page,
                'current' => $page==$pageurl+1];
        }
    }
            
    $range = isset($_GET['range'])?$_GET['range']:0;
    
    [$initialdate, $finaldate]= [$weekRange[0]['fulldate'],$weekRange[6]['fulldate']];
    $sql= "SELECT * FROM {local_asistencia} WHERE courseid = $courseid AND teacherid = $userid AND \"date\" BETWEEN '$initialdate' AND '$finaldate'";
    $temporalattendance = array_values($DB->get_records_sql($sql));
    
    
    [$students, $totaldaysattendance, $a] = studentsFormatWeek($studentslist[$attendancepage]?? $pages_attendance_array_copy[$attendancepage], $weekRange, $cachehistoryattendance, $temporalattendance, $userid, $initial,$final, $a, count($supended), $courseid);
    
    $studentslist[$attendancepage] = $students;
    
    $closeattendance = ($studentsamount*$totaldaysattendance) == count($temporalattendance) && count($temporalattendance)?0:1;
    $cache->set('attendancelist'.$courseid, json_encode($studentslist));

    $studentsstring = $cache->get('attendancelist'.$courseid);
    $students = json_decode($studentsstring, true);
    $templatecontext = (object)[
        'students' => $students[$attendancepage],
        'courseid' => $courseid,
        'teacherid' => $userid,
        // 'data' => $urldata,
        // 'data2' => $urldata2,
        'weekheader' => $weekRange,
        // 'monthheader' => $monthrange,
        'display' => 0,
        'range' => $range,
        'listpages' => !empty($pages)?array_values($pages):[],
        'currentpage' => $currentpage,
        'close' => $a == (count($students[$attendancepage])*7)?1:0,
        'closed' => $close,
        'range' => $_GET['range']??0,
        // 'saved' => $saved,
        'config' => $configbutton,
        'closeattendance' => $closeattendance,
        // 'limit' => $limit, //*  Variable que cambia entre páginado o todo en una página
        'dirroot' => $dircomplement[1],
        'weeks' => $weeks,
        '1week' => $weeks == 1?1:0,
        '2week' => $weeks == 2?1:0,
        '3week' => $weeks == 3?1:0,
        '4week' => $weeks == 4?1:0,
        'asistio'=> "Asistió",
        'inasistencia' => "No asistió",
        'retraso' => "Llegó tarde",
        'excusa' => "Excusa médica",
    ];
    
    //$form->display();

    echo $OUTPUT->render_from_template('local_asistencia/previous_attendance', $templatecontext);

    $PAGE->requires->js_call_amd('local_asistencia/attendance_views', 'init');
    echo $OUTPUT->footer();
    