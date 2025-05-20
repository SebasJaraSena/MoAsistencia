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
 * @copyright  2025 Zajuna team 
 **/


define(['jquery'], function ($) {
    function tableStickyColumns(percent) {
        const table = document.getElementById('attendance-table');
        if (!table) return;

        const isSmallScreen = window.innerWidth <= 768;
        const headerRow = table.querySelector('thead tr');
        const headerCells = Array.from(headerRow.children);
        const rows = Array.from(table.querySelectorAll('tbody tr'));

        // Paso 1: calcular los "left" de cada columna sticky
        let leftOffsets = [];
        let accumulatedLeft = 0;

        headerCells.forEach((th, index) => {
            if (!th.classList.contains('sticky-column')) {
                leftOffsets.push(null);
                return;
            }

            if (isSmallScreen && index > 0) {
                leftOffsets.push(null);
                return;
            }

            const width = th.getBoundingClientRect().width || 150;
            leftOffsets.push(accumulatedLeft);
            accumulatedLeft += width;
        });

        // Paso 2: aplicar a los TH
        headerCells.forEach((th, index) => {
            if (!th.classList.contains('sticky-column')) return;

            if (leftOffsets[index] === null) {
                th.style.position = 'static';
                th.style.left = '';
                th.style.zIndex = '';
            } else {
                th.style.position = 'sticky';
                th.style.left = `${leftOffsets[index]}px`;
                th.style.zIndex = 2;
                th.style.backgroundColor = '#f1f1f1';
                th.style.fontSize = '14px';
            }
        });

        // Paso 3: aplicar a los TD por fila
        rows.forEach(row => {
            const cells = Array.from(row.children);
            cells.forEach((td, index) => {
                if (!td.classList.contains('sticky-column')) return;

                const text = td.innerText.trim();

                if (leftOffsets[index] === null) {
                    td.style.position = 'static';
                    td.style.left = '';
                    td.style.zIndex = '';
                } else {
                    td.style.position = 'sticky';
                    td.style.left = `${leftOffsets[index]}px`;
                    td.style.zIndex = 1;
                }

                // Color de fondo por estado
                if (text === 'SUSPENDIDO') {
                    td.style.backgroundColor = '#fcefdc';
                } else if (text === 'ACTIVO') {
                    td.style.backgroundColor = '#def1de';
                } else {
                    td.style.backgroundColor = '#f1f1f1';
                }
            });
        });
        // Paso 4: ajustar el ancho mínimo de la tabla si hay pocas columnas
        const visibleHeaderCells = headerCells.filter(cell => cell.offsetParent !== null); // solo visibles
        if (visibleHeaderCells.length <= 7) {
            table.style.minWidth = '100%';
            table.style.width = '100%';
        } else {
            table.style.minWidth = 'auto';
            table.style.width = 'auto';
        }

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
        const filter = normalizeText(searchInput.value);

        const table = document.getElementById('attendance-table');
        if (!table) return;

        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const text = normalizeText(row.innerText);
            row.style.display = text.includes(filter) ? '' : 'none';
        });

        tableStickyColumns(percent);
    });

    // Función mejorada para normalizar texto
    function normalizeText(text) {
        return text
            .trim()                            
            .normalize("NFD")                  
            .replace(/[\u0300-\u036f]/g, "")  
            .toLowerCase();                   
    }
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
        refreshSticky: tableStickyColumns 
    };
});