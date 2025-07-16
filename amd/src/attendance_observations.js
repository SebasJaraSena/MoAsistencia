define(['jquery'], function ($) {
    return {
        init: function () {
            // Mostrar/ocultar inputs según selección de asistencia
            $('body').on('change', '.form-select', function () {
                const selectedValue = $(this).val();
                const inputContainer = $(this).closest('.select-container').find('.input-container');
                // Si el valor seleccionado es 0, 2 o 3, mostrar el input
                if (['0', '2', '3'].includes(selectedValue)) {
                    inputContainer.show();
                } else {
                    inputContainer.hide();
                }
                // Verificar si hay errores en las celdas de la tabla                                   
                checkAllSelections();
            });
            // Verificar si hay errores en las celdas de la tabla
            $('body').on('input change', '.input-container input', function () {
                const view = window.location.pathname.split('/').pop().split('.')[0];
                if (view === 'attendance') {
                    checkAllHours();
                } else if (view === 'previous_attendance') {
                    checkAllHours2();
                }
            });
            // Manejo de eventos del documento
            $(document).ready(function () {
                $('body').on('keydown', 'input[type=number]', function (e) {
                    if (e.key === '.' || e.key === ',' || e.key === 'e') {
                        e.preventDefault();
                    }
                });
                // Mostrar/ocultar inputs según selección de asistencia
                $('.form-select').each(function () {
                    const selectedValue = $(this).val();
                    const inputContainer = $(this).closest('.select-container').find('.input-container');
                    if (['0', '2', '3'].includes(selectedValue)) {
                        inputContainer.show();
                    } else {
                        inputContainer.hide();
                    }
                    // Verificar si hay errores en las celdas de la tabla
                });
                // Manejo de eventos del documento
                $('#saveButtonWrapper').on('click', function () {
                    const wrapper = document.getElementById('saveButtonWrapper');
                    const btn = document.getElementById('saveButton');
                    if (wrapper.classList.contains('disabled-click') || btn.disabled) return;
                    // Mostrar modal de confirmación
                    $('#modal1').modal('show');
                });
                // Verificar si hay errores en las celdas de la tabla
                document.querySelectorAll('select.form-select, .extra-input').forEach(el => {
                    el.addEventListener('change', checkAllHours);
                    el.addEventListener('input', checkAllHours);
                });
                // Verificar si hay errores en las celdas de la tabla
                checkAllSelections();
                dateShower();
                reportDownloader();
                tableSize();
            });
            // Manejo de cambio en selector de cantidad por página
            $('#perPageSelect').on('change', function () {
                const perPage = $(this).val();
                const url = new URL(window.location.href);
                url.searchParams.set('limit', perPage);
                //url.searchParams.set('page', 4); // Reiniciar a página 1
                window.location.href = url.toString();
            });
            // Manejo de envío del formulario
            $('form').on('submit', function () {
                console.log('Formulario enviado desde la página: ' + $('input[name="page"]').val());
            });
            // Verificar si hay errores en las celdas de la tabla
            function checkAllSelections() {
                const view = window.location.pathname.split('/').pop().split('.')[0];
                if (view === 'attendance') {
                    checkAllHours();
                } else if (view === 'previous_attendance') {
                    checkAllHours2();
                }
            }
            // Verificar si hay errores en las celdas de la tabla
            function checkAllHours2() {
                let allValid = true;
                let anyAttendanceSelected = false;

                $('td.select-container').each(function () {
                    const $cell = $(this);
                    const $select = $cell.find('select.form-select');
                    const $num = $cell.find('input[name^="extrainfoNum"]');
                    const value = $select.val();
                    const horas = parseInt($num.val(), 10);
                    let valid = true;

                    // Marcar si se ha seleccionado alguna asistencia distinta de "-"
                    if (value && value !== '-8') {
                        anyAttendanceSelected = true;
                    }

                    // Validar horas si aplica
                    if (['0', '2', '3'].includes(value)) {
                        if (isNaN(horas) || horas < 1 || horas > 10 || !Number.isInteger(horas)) {
                            valid = false;
                        }
                    }

                    if (!valid) {
                        allValid = false;
                        $cell.addClass('error-cell');
                    } else {
                        $cell.removeClass('error-cell');
                    }
                });

                const saveButton = $('#saveButton');
                const warning = $('#saveWarning');

                if (!anyAttendanceSelected) {
                    warning.text("Debes seleccionar al menos una asistencia.");
                } else {
                    warning.text("Debes ingresar un tiempo, con un límite máximo de 10 horas.");
                }

                const canSave = allValid && anyAttendanceSelected;

                saveButton.prop('disabled', !canSave);
                warning.toggle(!canSave);
            }

            // Manejo de clic en botón de guardar
            window.handleSaveClick = function () {
                const wrapper = document.getElementById('saveButtonWrapper');
                const btn = document.getElementById('saveButton');
                if (wrapper.classList.contains('disabled-click') || btn.disabled) return;

                $('#modal1').modal('show'); // o location.href = '#modal1';
            };
            // Verificar si hay errores en las celdas de la tabla
            function checkAllHours() {
                let allValid = true;
                let anyAttendanceSelected = false;

                $('td.select-container').each(function () {
                    const $cell = $(this);
                    const $select = $cell.find('select.form-select');
                    const $num = $cell.find('input[name^="extrainfoNum"]');

                    if (!$select.length) return; // si no hay select, saltar

                    const value = $select.val();
                    const horas = parseInt($num.val(), 10);
                    let valid = true;

                    // ✅ Verifica si hay al menos una asistencia tomada (≠ "-8")
                    if (value !== '-8') {
                        anyAttendanceSelected = true;
                    }

                    // ✅ Validación de horas obligatorias para ciertos valores
                    if (['0', '2', '3'].includes(value)) {
                        if (isNaN(horas) || horas < 1 || horas > 10 || !Number.isInteger(horas)) {
                            valid = false;
                        }
                    }

                    if (!valid) {
                        allValid = false;
                        $cell.addClass('error-cell');
                    } else {
                        $cell.removeClass('error-cell');
                    }
                });

                const saveButton = $('#saveButton');
                const warning = $('#saveWarning');

                // ✅ Mostrar mensaje adecuado
                if (!anyAttendanceSelected) {
                    warning.text("Debes seleccionar al menos una asistencia.");
                } else {
                    warning.text("Debes ingresar un tiempo, con un límite máximo de 10 horas.");
                }

                // ✅ Activar botón solo si hay asistencia seleccionada y no hay errores
                const canSave = allValid && anyAttendanceSelected;
                saveButton.prop('disabled', !canSave);
                warning.toggle(!canSave);
            }

            // Manejo de cambio en selector de fecha rango
            // Fecha rango
            $('#date-range-select').on('change', function () {
                const selectedValue = $(this).val();
                const dateInputsContainer = $('#date-inputs-container');
                if (selectedValue === 'range_dates') {
                    dateInputsContainer.show();
                } else {
                    dateInputsContainer.hide().find('input').val('');
                }
            });
            // Manejo de cambio en selector de fecha rango
            function dateShower() {
                const dateSelect = document.getElementById("date-range-select");
                const divRanges = document.getElementById("date-inputs-container");
                if (dateSelect && divRanges) {
                    divRanges.style.display = dateSelect.value === 'range_dates' ? '' : 'none';
                }
            }
            // Manejo de cambio en selector de fecha rango
            function reportDownloader() {
                const startDate = document.getElementById("start-date");
                const endDate = document.getElementById("end-date");
                const detailedDonwloader = document.getElementById("detailed_donwloader");
                if (!startDate || !endDate || !detailedDonwloader) return;

                const diff = (new Date(endDate.value) - new Date(startDate.value)) / (1000 * 60 * 60 * 24);
                detailedDonwloader.disabled = diff >= 7;
            }
            // Manejo de cambio en selector de fecha rango
            $('#start-date').on('change', function () {
                $('#end-date').attr('min', $(this).val());
            });
            // Manejo de cambio en selector de fecha rango
            $('#end-date').on('change', function () {
                $('#start-date').attr('max', $(this).val());
            });
            // Manejo de clic en botón de confirmar asistencia
            $('#confirmAtt').on('click', function () {
                const courseid = document.getElementById('courseid').value;
                $('#course_' + courseid).submit();
            });
            // Manejo de cambio en selector de fecha rango
            function adjustTableSize() {
                const textarea = document.querySelector('textarea[name="extrainfo[]"]');
                const table = document.querySelector('#attendance-table');
                if (textarea && table) {
                    table.style.width = textarea.offsetWidth + 'px';
                }
            }
            // Manejo de cambio en selector de fecha rango
            function tableSize() {
                const textarea = document.querySelector('textarea[name="extrainfo[]"]');
                if (textarea) {
                    textarea.addEventListener('resize', adjustTableSize);
                }
                window.onload = adjustTableSize;
            }
            //modal de paginacion
            let asistenciaModificada = false;
            let urlPendiente = null;
            // Manejo de cambio en selector de fecha rango
            // Detectar cambios en los campos de asistencia
            $('select[name^="attendance"], input[name^="attendance"]').on('change', function () {
                asistenciaModificada = true;
            });
            // Manejo de cambio en selector de fecha rango
            // Interceptar botón de paginación
            $('.pagelink').on('click', function (e) {
                e.preventDefault();
                const url = $(this).data('url');

                if (asistenciaModificada) {
                    urlPendiente = url;
                    $('#modal1').modal('show');
                } else {
                    window.location.href = url;
                }
            });
            // Manejo de cambio en selector de fecha rango  
            // Confirmar navegación
            $('#confirmAtt').on('click', function () {
                if (urlPendiente) {
                    window.location.href = urlPendiente;
                }
            });
            // Manejo de cambio en selector de fecha rango  
            // Opcional: limpiar URL pendiente si cancela
            $('#modal1 .btn-secondary, #modal1 .close').on('click', function () {
                urlPendiente = null;
            });
        }
    };
});
