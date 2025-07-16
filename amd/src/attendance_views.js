define(['jquery'], function ($) {
    let headerCells, rows;
    // Función para inicializar el cache de la tabla
    function initCache() {
        const table = document.getElementById('attendance-table');
        if (!table) return;
        const headerRow = table.querySelector('thead tr');
        headerCells = Array.from(headerRow.children);
        rows = Array.from(table.querySelectorAll('tbody tr'));
    }
    // Función para normalizar el texto
    function normalizeText(text) {
        return text
            .trim()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
    }
    // Función para debounce
    function debounce(fn, wait = 100) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), wait);
        };
    }
    // Función para hacer las columnas de la tabla sticky
    function tableStickyColumns() {
        const table = document.getElementById('attendance-table');
        if (!table || !headerCells || !rows) return;

        // Threshold para pantalla pequeña: solo la primera columna sticky
        const isSmall = window.innerWidth <= 1024;
        let accumulated = 0;
        const leftOffsets = headerCells.map((th, i) => {
            if (!th.classList.contains('sticky-column') || (isSmall && i > 0)) {
                return null;
            }
            const width = th.getBoundingClientRect().width || 150;
            const left = accumulated;
            accumulated += width;
            return left;
        });

        // Columnas que deben tener fondo gris siempre
        const greyCols = [0, 1, 2, 3, 4, 5];  // incluye Documento, Tipo, Apellidos, Nombres, Correo y Estado

        // Aplica a TH
        headerCells.forEach((th, i) => {
            if (leftOffsets[i] == null) {
                th.style.position = 'static';
                th.style.left = '';
                th.style.zIndex = '';
            } else {
                th.style.position = 'sticky';
                th.style.left = `${leftOffsets[i]}px`;
                th.style.zIndex = 2;
                th.style.fontSize = '14px';
            }
            // Fondo gris solo en columnas tipo, apellidos, nombres, correo, estado
            if (greyCols.includes(i)) {
                th.style.backgroundColor = '#f1f1f1';
            } else {
                th.style.backgroundColor = '';
            }
        });

        // Aplica a TD
        rows.forEach(row => {
            const cells = Array.from(row.children);
            cells.forEach((td, i) => {
                if (leftOffsets[i] == null) {
                    td.style.position = 'static';
                    td.style.left = '';
                    td.style.zIndex = '';
                } else {
                    td.style.position = 'sticky';
                    td.style.left = `${leftOffsets[i]}px`;
                    td.style.zIndex = 1;
                }
                // Fondo gris solo en columnas tipo, apellidos, nombres, correo, estado
                if (greyCols.includes(i)) {
                    td.style.backgroundColor = '#f1f1f1';
                } else {
                    td.style.backgroundColor = '';
                }

                // Override fondo en columna Estado (índice 5)
                if (i === 5) {
                    const text = td.innerText.trim();
                    td.style.backgroundColor =
                        text === 'SUSPENDIDO' ? '#fcefdc'
                        : text === 'ACTIVO'     ? '#def1de'
                        : '#f1f1f1';
                }
            });
        });

        // Ajuste de ancho
        const visibleCount = headerCells.filter(th => th.offsetParent !== null).length;
        if (visibleCount <= 7) {
            table.style.width = '100%';
            table.style.minWidth = '100%';
        } else {
            table.style.width = '';
            table.style.minWidth = '';
        }
    }
    // Función para habilitar la búsqueda con actualización de columnas sticky
    function enableSearchWithStickyUpdate() {
        const searchInput = document.getElementById('searchInput');
        if (!searchInput) return;

        const doFilter = () => {
            const filter = normalizeText(searchInput.value);
            rows.forEach(row => {
                row.style.display = normalizeText(row.innerText).includes(filter) ? '' : 'none';
            });
            tableStickyColumns();
        };

        searchInput.addEventListener('input', debounce(doFilter));
    }
    // Función para esperar a que el elemento exista
    function waitForElement(selector, callback) {
        const interval = setInterval(() => {
            if (document.querySelector(selector)) {
                clearInterval(interval);
                callback();
            }
        }, 100);
    }
    // Función para inicializar el módulo   
    return {
        init: function () {
            waitForElement('#attendance-table', function () {
                initCache();
                tableStickyColumns();
                enableSearchWithStickyUpdate();

                window.addEventListener('resize', function () {
                    const mainbox = document.getElementById('region-main-box');
                    if (mainbox) {
                        mainbox.style.setProperty('flex', '0 0 100%', 'important');
                        mainbox.style.setProperty('max-width', '100%', 'important');
                    }
                    tableStickyColumns();
                });
            });
        },
        refreshSticky: tableStickyColumns
    };
});
