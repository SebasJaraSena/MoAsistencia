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
                document.querySelectorAll('td.select-container').forEach(cell => {
                    const select = cell.querySelector('select.form-select');
                    if (!select) return;
                    const value = select.value;
                    const horas = parseInt(cell.querySelector('input[name^="extrainfoNum"]').value);
                    if (['0', '2', '3'].includes(value)) {
                        if (isNaN(horas) || horas < 1 || horas > 10) {
                            allValid = false;
                        }
                    }
                });

                const saveButton = document.getElementById('saveButton');
                const warning = document.getElementById('saveWarning');

                saveButton.disabled = !allValid;
                if (!allValid) {
                    warning.style.display = 'block';
                } else {
                    warning.style.display = 'none';
                }
            }


            window.handleSaveClick = function () {
                const wrapper = document.getElementById('saveButtonWrapper');
                const btn = document.getElementById('saveButton');
                if (wrapper.classList.contains('disabled-click') || btn.disabled) return;

                $('#modal1').modal('show'); // o location.href = '#modal1';
            };

            function checkAllHours() {
                let allValid = true;
                document.querySelectorAll('td.select-container').forEach(cell => {
                    const select = cell.querySelector('select.form-select');
                    if (!select) return;
                    const value = select.value;
                    const horas = parseInt(cell.querySelector('input[name^="extrainfoNum"]').value);
                    if (['0', '2', '3'].includes(value)) {
                        if (isNaN(horas) || horas < 1 || horas > 10) {
                            allValid = false;
                        }
                    }
                });

                const saveButton = document.getElementById('saveButton');
                const warning = document.getElementById('saveWarning');

                saveButton.disabled = !allValid;
                if (!allValid) {
                    warning.style.display = 'block';
                } else {
                    warning.style.display = 'none';
                }
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

        }
    };
});
