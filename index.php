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
 * Página principal del menú de asistencia.
 *
 * @package    local_asistencia
 * @author     Equipo zajuna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 o posterior
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/externallib.php');
require_once($CFG->dirroot . '/local/asistencia/lib.php');

require_login();

global $USER, $PAGE, $DB;

// Parámetros seguros
$courseid = required_param('courseid', PARAM_INT);
$context = context_course::instance($courseid);

// Verificación de capacidades
require_capability('local/asistencia:view', $context); // acceso general al plugin
$denybutton = !has_capability('local/asistencia:vergeneral', $context); // bloqueo de botones específicos

// Preparar URL y configuración de página
$params = ['courseid' => $courseid];
$currenturl = new moodle_url('/local/asistencia/index.php', $params);

$dircomplement = explode("/", $currenturl->get_path());

$PAGE->set_url($currenturl);
$PAGE->set_context($context);
$PAGE->set_course(get_course($courseid));
$PAGE->set_title('Lista Asistencia');
$PAGE->set_heading(get_course($courseid)->shortname);
$PAGE->navbar->add('Menú Asistencia');
/* $PAGE->navbar->ignore_active_url(true); */

$PAGE->requires->css(new moodle_url('/local/asistencia/styles/styles.css', ['v' => time()]));
$PAGE->requires->js_call_amd('local_asistencia/attendance_observations', 'init');
$PAGE->requires->js_call_amd('local_asistencia/attendance_views', 'init');

// Breadcrumb
/* local_asistencia_setup_breadcrumb('Menú'); */

// Contexto para Mustache
$templatecontext = (object) [
    'courseid' => $courseid,
    'teacherid' => $USER->id,
    'denybutton' => $denybutton,
    'dirroot' => $dircomplement[1],
    'bannerurl' => new moodle_url('/local/asistencia/pix/banner.jpg')
];

// Renderizar
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_asistencia/menu', $templatecontext);
echo $OUTPUT->footer();
