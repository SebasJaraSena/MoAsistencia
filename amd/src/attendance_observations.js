define(['jquery'], function ($) {
    return {
        init: function () {
            // Mostrar/ocultar inputs según selección de asistencia
            $('body').on('change', '.form-select', function () {
                const selectedValue = $(this).val();
                const inputContainer = $(this).closest('.select-container').find('.input-container');

                if (['0', '2', '3'].includes(selectedValue)) {
                    inputContainer.show();
                } else {
                    inputContainer.hide();
                }

                checkAllSelections();
            });

            $('body').on('input change', '.input-container input', function () {
                const view = window.location.pathname.split('/').pop().split('.')[0];
                if (view === 'attendance') {
                    checkAllHours();
                } else if (view === 'previous_attendance') {
                    checkAllHours2();
                }
            });

            $(document).ready(function () {
                $('body').on('keydown', 'input[type=number]', function (e) {
                    if (e.key === '.' || e.key === ',' || e.key === 'e') {
                        e.preventDefault();
                    }
                });

                $('.form-select').each(function () {
                    const selectedValue = $(this).val();
                    const inputContainer = $(this).closest('.select-container').find('.input-container');
                    if (['0', '2', '3'].includes(selectedValue)) {
                        inputContainer.show();
                    } else {
                        inputContainer.hide();
                    }

                });

                $('#saveButtonWrapper').on('click', function () {
                    const wrapper = document.getElementById('saveButtonWrapper');
                    const btn = document.getElementById('saveButton');
                    if (wrapper.classList.contains('disabled-click') || btn.disabled) return;

                    $('#modal1').modal('show');
                });

                document.querySelectorAll('select.form-select, .extra-input').forEach(el => {
                    el.addEventListener('change', checkAllHours);
                    el.addEventListener('input', checkAllHours);
                });

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

            $('form').on('submit', function () {
                console.log('Formulario enviado desde la página: ' + $('input[name="page"]').val());
            });


            function checkAllSelections() {
                const view = window.location.pathname.split('/').pop().split('.')[0];
                if (view === 'attendance') {
                    checkAllHours();
                } else if (view === 'previous_attendance') {
                    checkAllHours2();
                }
            }

            function checkAllHours2() {
                let allValid = true;
                $('td.select-container').each(function () {
                    const $cell = $(this);
                    const value = $cell.find('select.form-select').val();
                    const $num = $cell.find('input[name^="extrainfoNum"]');
                    const horas = parseInt($num.val(), 10);
                    let valid = true;

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

                saveButton.prop('disabled', !allValid);
                warning.toggle(!allValid);
            }


            window.handleSaveClick = function () {
                const wrapper = document.getElementById('saveButtonWrapper');
                const btn = document.getElementById('saveButton');
                if (wrapper.classList.contains('disabled-click') || btn.disabled) return;

                $('#modal1').modal('show'); // o location.href = '#modal1';
            };

            function checkAllHours() {
                let allValid = true;
                $('td.select-container').each(function () {
                    const $cell = $(this);
                    const value = $cell.find('select.form-select').val();
                    const $num = $cell.find('input[name^="extrainfoNum"]');
                    const horas = parseInt($num.val(), 10);
                    let valid = true;

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

                saveButton.prop('disabled', !allValid);
                warning.toggle(!allValid);
            }

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

            function dateShower() {
                const dateSelect = document.getElementById("date-range-select");
                const divRanges = document.getElementById("date-inputs-container");
                if (dateSelect && divRanges) {
                    divRanges.style.display = dateSelect.value === 'range_dates' ? '' : 'none';
                }
            }

            function reportDownloader() {
                const startDate = document.getElementById("start-date");
                const endDate = document.getElementById("end-date");
                const detailedDonwloader = document.getElementById("detailed_donwloader");
                if (!startDate || !endDate || !detailedDonwloader) return;

                const diff = (new Date(endDate.value) - new Date(startDate.value)) / (1000 * 60 * 60 * 24);
                detailedDonwloader.disabled = diff >= 7;
            }

            $('#start-date').on('change', function () {
                $('#end-date').attr('min', $(this).val());
            });

            $('#end-date').on('change', function () {
                $('#start-date').attr('max', $(this).val());
            });

            $('#confirmAtt').on('click', function () {
                const courseid = document.getElementById('courseid').value;
                $('#course_' + courseid).submit();
            });

            function adjustTableSize() {
                const textarea = document.querySelector('textarea[name="extrainfo[]"]');
                const table = document.querySelector('#attendance-table');
                if (textarea && table) {
                    table.style.width = textarea.offsetWidth + 'px';
                }
            }

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

            // Detectar cambios en los campos de asistencia
            $('select[name^="attendance"], input[name^="attendance"]').on('change', function () {
                asistenciaModificada = true;
            });

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

            // Confirmar navegación
            $('#confirmAtt').on('click', function () {
                if (urlPendiente) {
                    window.location.href = urlPendiente;
                }
            });

            // Opcional: limpiar URL pendiente si cancela
            $('#modal1 .btn-secondary, #modal1 .close').on('click', function () {
                urlPendiente = null;
            });
        }
    };
});
