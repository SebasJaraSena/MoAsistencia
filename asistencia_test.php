<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/asistencia/attendance.php');

echo "<h3>Prueba de getWeekRange()</h3>";

$initial = '2025-04-28';

$result = getWeekRange($initial);

echo "<p>Inicio de semana: " . date('Y-m-d', $result['start']) . "</p>";
echo "<p>Fin de semana: " . date('Y-m-d', $result['end']) . "</p>";
