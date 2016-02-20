/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


define(['masks', 'moment', 'datetimepicker'], function (masks, moment) {

    var generate = (function () {

        var templateDate = moment();

        var add = $("#addAttendanceDate");
        var rm = $("#removeAttendanceDate");
        var attImportInput = $("#attendanceListInput");
        var lists;
        var attendanceLists = $("#attendanceLists");

        var listModels = [];

        initDateCopy = function () {
            add.click(addAttendanceDate);
            rm.click(removeAttendanceDate);
        };

        addAttendanceDate = function () {

            var currentValue = $("input[name*=attendanceDate]").last().val();

            if (currentValue !== "") {
                templateDate = moment(currentValue, "DD/MM/YYYY");
            }

            var currentCount = $('form fieldset > fieldset').length;
            var template = $('form fieldset > span').data('template');
            template = template.replace(/__index__/g, currentCount);

            var htmlTemplate = $(template);
            htmlTemplate.find('input')
                    .val(templateDate.add(1, 'days').format('DD/MM/YYYY'));

            $('form fieldset').first().append(htmlTemplate);
            applyDatepickers();
        };

        removeAttendanceDate = function () {
            var currentCount = $('form fieldset > fieldset').length;
            if (currentCount > 1) {
                $('form fieldset > fieldset').last().remove();
                templateDate.subtract(1, 'days').format('DD/MM/YYYY')
            }
        };

        initMasks = function () {
            masks.bind({
                date: "input[name*=attendanceDate]"
            });
        };

        applyDatepickers = function () {
            $("input[name*=attendanceDate]")
                    .closest('.input-group')
                    .datetimepicker({
                        format: 'DD/MM/YYYY',
                        minDate: moment().subtract(6, 'months'),
                        useCurrent: true,
                        locale: 'pt-br',
                        viewMode: 'days',
                        viewDate: moment()
                    });
        };

        bindImportEvent = function (bootbox) {

            attImportInput.click(function () {
                $(this).val("");
            });

            attImportInput.change(function (e) {

                var files = e.target.files; // FileList object
                var file = files[0];

                var reader = new FileReader();
                reader.readAsText(file);

                reader.onload = function (event) {
                    lists = $.csv.toArrays(event.target.result);
                    importReset();
                    createLists();
                    setAttendanceActionListeners();
                };

                reader.onerror = function () {
                    bootbox.alert("Não foi possível abrir o arquivo <b>" + file.name + "<br>");
                };
            });
        };

        importReset = function () {
            listModels = [];
            attendanceLists.html("");
        };

        createLists = function () {

            var index;

            index = lists[1].indexOf("");
            if (index > 0) {
                lists[1] = lists[1].slice(0, index);
            }
            index = lists[2].indexOf("");
            if (index > 0) {
                lists[2] = lists[2].slice(0, index);
            }
            index = lists[3].indexOf("");
            if (index > 0) {
                lists[3] = lists[3].slice(0, index);
            }

            var attendanceTypesIds = lists[1].slice(1);
            var attendanceTypesNames = lists[2].slice(1);
            var dates = lists[3].slice(1);

            // config
            $("#schoolClass")
                    .data("id", lists[0][1])
                    .next().find("em").text(lists[0][2]);

            $("#attendanceTypes")
                    .data("id", JSON.stringify(attendanceTypesIds))
                    .next().find("em").text(attendanceTypesNames.join(", "));
            $("#attendanceDates")
                    .data("id", JSON.stringify(dates))
                    .next().find("em").text(dates.join(", "));

            // foreach day
            for (var d = 0; d < dates.length; d++) {

                var sm = {
                    date: moment(dates[d], "DD/MM/YYYY").format("YYYY-MM-DD"),
                    typeNames: attendanceTypesNames,
                    students: []
                };

                // foreach student
                for (var i = 7; i < lists.length; i++) {

                    var student = {
                        id: lists[i][0], //enrollmentId
                        name: lists[i][1],
                        types: []
                    };
                    // foreach attendanceType
                    for (var a = 0; a < attendanceTypesIds.length; a++) {

                        student.types.push({
                            id: attendanceTypesIds[a],
                            status: lists[i][2 + a + d * (attendanceTypesIds.length + 1)].toUpperCase() === "P"
                        });
                    }

                    sm.students.push(student);
                }

                listModels.push(sm);
                showList(sm, d);
            }
        };

        showList = function (list, index) {
            var i, j;
            var container = $("<div class='panel box box-success col-md-6 col-xs-12 cats-row' style='display:none;'>" +
                    "<div class='box-header with-border'>" +
                    "<h4 class='box-title'>" +
                    "<a data-toggle='collapse' data-parent='#" + attendanceLists.attr("id") + "' " +
                    "href='#collapse-" + index + "'>" +
                    "Lista de " + moment(list.date, "YYYY-MM-DD")
                    .format("DD/MM/YYYY") +
                    "</a>" +
                    "</h4>" +
                    "</div>" +
                    "<div id='collapse-" + index + "' class='panel-collapse collapse'>" +
                    "<div class='box-body bg-white'>" +
                    "</div>" +
                    "</div>" +
                    "</div>");

            var table = "<div class='col-md-8'><table data-id='" + index + "' class='table table-condensed table-bordered table-striped table-hover attendanceListTable'>" +
                    "<thead><tr>";

            table += "<th>Aluno</th>";
            for (i = 0; i < list.typeNames.length; i++) {
                table += "<th class='text-center'>" + list.typeNames[i] + "</th>";
            }

            table += "</tr></thead><tbody>";


            for (i = 0; i < list.students.length; i++) {
                table += "<tr data-id='" + i + "'>";
                table += "<td>" + list.students[i].name + "</td>";
                for (j = 0; j < list.students[i].types.length; j++) {
                    table += "<td data-id='" + j + "'" +
                            "class='text-center btn-" + (list.students[i].types[j].status ? "success" : "danger") + " attendanceStatus'>" +
                            "<i class='fa " + (list.students[i].types[j].status ? "fa-check" : "fa-close") + "' ></i></td>";
                }
                table += "</tr>";
            }

            table += "</tbody></table></div>";
            container.find(".box-body").append(table);
            attendanceLists.append(container);
            container.slideDown('fast');
        };

        setAttendanceActionListeners = function () {

            // block cats-selected-row change on table
            attendanceLists.off("click", ".attendanceListTable");
            attendanceLists.on("click", ".attendanceListTable", function (e) {
                e.stopPropagation();
            });

            attendanceLists.off("click", ".attendanceListTable td.attendanceStatus");
            attendanceLists.on("click", ".attendanceListTable td.attendanceStatus", function (e) {
                var type = $(this).data("id");
                var student = $(this).closest("tr").data("id");
                var list = $(this).closest("table").data("id");

                var result = listModels[list].students[student].types[type].status = !listModels[list].students[student].types[type].status;

                if (result) {
                    $(this)
                            .addClass("btn-success")
                            .removeClass("btn-danger")
                            .find("i")
                            .addClass("fa-check")
                            .removeClass("fa-close");
                } else {
                    $(this)
                            .addClass("btn-danger")
                            .removeClass("btn-success")
                            .find("i")
                            .addClass("fa-close")
                            .removeClass("fa-check");
                }

            });

        };

        return {
            init: function () {

                if (add.length > 0 && rm.length > 0) {
                    initDateCopy();
                    initMasks();
                    applyDatepickers();
                }

                if (attImportInput.length > 0) {
                    require(['bootbox', 'jquerycsv'], function (bootbox) {
                        bindImportEvent(bootbox);
                    });
                }
            }
        };

    }());

    return generate;

});