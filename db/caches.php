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
 * Plugin capabilities for the local_auto_inscripcion plugin.
 *
 * @package   local_auto_inscripcion
 * @copyright Equipo zajuna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 defined('MOODLE_INTERNAL') || die();
 // Definir los cachés del plugin
$definitions = array(
    'coursestudentslist' => array( // 'my_cache' is the cache name
        'mode' => cache_store::MODE_SESSION,
        'simpledata' => true, // If true, data must be serializable with json_encode
        'ttl' => 10, // Time-to-live in seconds (optional)
    ),
);
