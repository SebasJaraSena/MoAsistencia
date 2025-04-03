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
 * Events for the grading interface.
 * @module     local_asistencia/attendance_views
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2024 Luis Pérez <lfperezv@sena.edu.co>
 **/


define(['jquery'], function($) {
    function tableStickyColumns(percent) {
        const stickyColumns = document.querySelectorAll('.sticky-column');
        const table = document.getElementById('attendance-table');
        if (!table) return;

        const thSticky = table.querySelectorAll('th.sticky-column');
        const tdSticky = table.querySelectorAll('td.sticky-column');
        const div = thSticky.length;
        const tdColumns = tdSticky.length;

        stickyColumns.forEach((column, index) => {
            const width = column.offsetWidth;
            column.style.width = width + 'px';

            if (index === 0) {
                thSticky[index].style.left = (percent * 0) + 'px';
                tdSticky[index].style.left = (percent * 0) + 'px';
                thSticky[index].style.backgroundColor = '#f1f1f1';
            } else if (index < div) {
                const prevWidth = parseInt(thSticky[index - 1].style.width);
                const prevLeft = parseInt(thSticky[index - 1].style.left);
                const newLeft = ((prevWidth + prevLeft) * percent) + 'px';

                thSticky[index].style.left = newLeft;
                tdSticky[index].style.left = newLeft;
                thSticky[index].style.backgroundColor = '#f1f1f1';
            } else if (index < tdColumns) {
                const thIndex = index % div;
                const leftPosition = thSticky[thIndex].style.left;
                tdSticky[index].style.left = leftPosition;
            }

            // Color según estado
            if (index < tdColumns) {
                const text = tdSticky[index].innerText.trim();
                if (text === 'SUSPENDIDO') {
                    tdSticky[index].style.backgroundColor = '#fcefdc';
                } else if (text === 'ACTIVO') {
                    tdSticky[index].style.backgroundColor = '#def1de';
                } else {
                    tdSticky[index].style.backgroundColor = '#f1f1f1';
                }
            }
        });
    }

    function waitForElement(selector, callback) {
        const interval = setInterval(() => {
            if (document.querySelector(selector)) {
                clearInterval(interval);
                callback();
            }
        }, 100);
    }

    function enableSearchWithStickyUpdate(percent) {
        const searchInput = document.getElementById('searchInput');
        if (!searchInput) return;

        searchInput.addEventListener('keyup', function () {
            const filter = searchInput.value.toLowerCase();
            const table = document.getElementById('attendance-table');
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });

            // Recalcular sticky después de mostrar/ocultar
            tableStickyColumns(percent);
        });
    }

    return {
        init: function () {
            waitForElement('#attendance-table', function () {
                const percent = window.innerWidth > 768 ? 1 : 0.2;
                tableStickyColumns(percent);
                enableSearchWithStickyUpdate(percent);

                // Reaplicar sticky al redimensionar ventana
                window.addEventListener('resize', function () {
                    const mainbox = document.getElementById('region-main-box');
                    if (mainbox) {
                        mainbox.style.setProperty('flex', '0 0 100%', 'important');
                        mainbox.style.setProperty('max-width', '100%', 'important');
                    }

                    const newPercent = window.innerWidth > 768 ? 1 : 0.2;
                    tableStickyColumns(newPercent);
                });
            });
        },
        refreshSticky: tableStickyColumns // Por si lo necesitas externamente
    };
});
