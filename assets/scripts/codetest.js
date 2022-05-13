/*Main Javascript File*/

// Init listeners >>
$(() => {
    $('.results-collapse.collapse').on('show.bs.collapse', function(){
        var rowDiv = $(this).parent();
        rowDiv.find(".fa.rotate").addClass("open");
        rowDiv.parent().addClass("selected-row");
    }).on('hide.bs.collapse', function(){
        var rowDiv = $(this).parent();
        rowDiv.find(".fa.rotate").removeClass("open");
        rowDiv.parent().removeClass("selected-row");
    });

    $("#importModal").on("hidden.bs.modal", function() {
        $(this).find('.results-collapse.collapse').collapse("hide");
        $(this).find("input[name='exercise']").prop("checked", false);
    });

    let $bodyContainer = $('#body_container');
    let $navbar = $('#tsugi_tool_nav_bar');
    let hasNavbar = $navbar.length > 0;
    let navBarHeight = hasNavbar ? 62 : 0;

    $bodyContainer.get(0).style.setProperty("--navbarHeight", `${navBarHeight}px`);

    $('#import-file-field').on('change', (ev) => {
        $('#import-confirmation').removeClass('hidden')
        $('#import-file-label').addClass('hidden')
        const inputEl = ev.target;
        const { files } = inputEl;
        if(files.length <= 0){
            return
        }
        const firstFile = files[0]

        $('#import-confirmation .file-info').text(`${firstFile.name}`)
        $('#import-confirmation .file-info').prop('title',`${firstFile.name}`)
        $('#import-confirmation .file-size').text(bytesToHuman(firstFile.size))
    })

    $('#import-confirmation').on('click', () => {
        let confirmation = confirm("Confirm import?")
        if(confirmation){
            document.querySelector('#form-confirm-import').submit()
        }
    })

    $('#import-confirmation .file-cancel').on('click', (ev) => {
        ev.stopPropagation();
        document.querySelector('#form-confirm-import').reset()
        $('#import-confirmation').addClass('hidden')
        $('#import-file-label').removeClass('hidden')

        $('#import-confirmation .file-info').text("")
        $('#import-confirmation .file-info').prop('title', null)
        $('#import-confirmation .file-size').text("")
    })

    if(document.getElementById('exercise[statement]')){
        const ckExerciseInput = getCKEditor('exercise[statement]');
        ckExerciseInput.on('focus', (e) => {
            const wrapperEl = e.editor.element.$.parentElement
            wrapperEl.classList.add('ct-focused')
        })
        ckExerciseInput.on('blur', (e) => {
            const wrapperEl = e.editor.element.$.parentElement
            wrapperEl.classList.remove('ct-focused')
        })
    }

    if(document.getElementById('exercise[exercise_solution]')){
        const codeTextArea = document.getElementById('exercise[exercise_solution]');
        const selectElement = document.getElementById('typeSelect');

        const selectedText = getCodeOptionText(selectElement);

        var codeEditor = CodeMirror.fromTextArea(codeTextArea, {
            lineNumbers: true,
            matchBrackets: true,
            mode: selectedText
        });

        window.codeEditor = codeEditor;
        if(document.getElementById('typeSelect')){
            $('#typeSelect').on('change', (ev) => {
                // debugger;
                const selectedEl = ev.target
                const selectedText = getCodeOptionText(selectedEl)
                codeEditor.setOption("mode", selectedText);
            })
        }
    }

    if(document.querySelector('[id*=answerText]')){
        const codeTextArea = document.querySelector('[id*=answerText]');
        const selectElement = document.getElementById('typeSelect');

        const selectedText = getCodeOptionText(selectElement);

        var codeEditor = CodeMirror.fromTextArea(codeTextArea, {
            lineNumbers: true,
            matchBrackets: true,
            mode: selectedText
        });

        window.codeEditor = codeEditor;
        if(document.getElementById('typeSelect')){
            $('#typeSelect').on('change', (ev) => {
                // debugger;
                const selectedEl = ev.target
                const selectedText = getCodeOptionText(selectedEl)
                codeEditor.setOption("mode", selectedText);
            })
        }
    }

    if(document.querySelector('.exercise-import.page')){
        let i = 0;
        let object = 'test';

        if (object == 'test') {
            $.ajax({
                type: "GET",
                url: "actions/import/ImportLtiContexts.php?page=" + i + "&" + _TSUGI.ajax_session,
                success: function (data) {
                    $('.import-body').html(data);
                    $('#buttonImport').attr('onclick', "document.getElementById('importForm').submit();");
                },
                error: function (data) {
                    console.error(data.responseText);
                }
            });
        } else if (object = "exercise") {
            $.ajax({
                type: "GET",
                url: "actions/import/ImportLtiContextsExercises.php?page=" + i + "&" + _TSUGI.ajax_session,
                success: function (data) {
                    $('.import-body-exercises').html(data);
                    $('#buttonImport').attr('onclick', "document.getElementById('importExercisesForm').submit();");
                },
                error: function (data) {
                    console.error(data.responseText);
                }
            });
        }
    }

    if(document.querySelector('#exerciseIdsModal')){
        const originalText = $('.copy-ids-to-clip').text()

        $('#exerciseIdsModal').on('show.bs.modal', function (e) {
            $('.copy-ids-to-clip').text(originalText)
            $('.copy-ids-to-clip').attr('disabled', false)
        })

        $('#exerciseIdsModal .copy-ids-to-clip').on('click', async (ev) => {
            const selectedIds = $('#exerciseIdsModal [name="ex-id-select"]:checked').toArray().map(el => $(el).data('exid'))
            const textToCopy = selectedIds.join(',');
            if (!navigator.clipboard) {
                alert('Clipboard feature not supported in this browser')
                return
            }
            const textWithLabel = `exercises_list=${textToCopy}`
            await navigator.clipboard.writeText(textWithLabel)
            $('.copy-ids-to-clip').text('Saved!')
            $('.copy-ids-to-clip').attr('disabled', true)
            setTimeout(() => {
                $('#exerciseIdsModal').modal('hide')
            }, 600);
        })
    }

    if(document.querySelector('.ak-exercises-list')){
        $('.ak-exercises-list .exercises-list > a').on('click', async (ev) => {

            ev.preventDefault()
            document.querySelector('.loading-overlay').classList.remove('hidden')
            document.querySelector('.loading-overlay').classList.add('appearing')

            const { exerciseId } = $(ev.currentTarget).data();

            const downloadAkExerciseResponse = await $.ajax({
                type: "GET",
                url: `download-ak-exercise.php?exerciseId=${exerciseId}&${_TSUGI.ajax_session}`,
            });

            const importExerciseResponse = await $.ajax({
                type: "POST",
                url: `actions/ImportExercisesQuestionAk.php?exercise[]=${exerciseId}&${_TSUGI.ajax_session}`,
                data: {
                    'exercise': downloadAkExerciseResponse,
                    'PHPSESSID': _TSUGI.react_token,
                },
            });

            window.location.replace(`instructor-home.php?${_TSUGI.ajax_session}`);

        })
    }

});

// Init listeners <<

function getCodeOptionText(el){
    const selectedIndex = el.options.selectedIndex
    const selectedOption = el.options[selectedIndex]
    const selectedText = (selectedOption.text || '').toLowerCase()
    if(selectedText === 'java'){
        return 'text/x-java'
    }
    return selectedText
}

function bytesToHuman(bytes) {
    var i = Math.floor(Math.log(bytes) / Math.log(1024)),
        sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    return (bytes / Math.pow(1024, i)).toFixed(2) * 1 + ' ' + sizes[i];
}

global.confirmDeleteExercise = function() {
    return confirm("Are you sure you want to delete this exercise? This action cannot be undone.");
}

global.confirmDeleteExerciseBlank = function(exerciseId) {
    if ($("#exerciseTextInput"+exerciseId).val().trim().length < 1) {
        return confirm("Saving this exercise with blank text will delete this exercise. Are you sure you want to delete this exercise? This action cannot be undone.");
    } else {
        return true;
    }
}

global.importLtiContexts = function(i = 0, object = 'test') {
    if (object == 'test') {
        $.ajax({
            type: "GET",
            url: "actions/import/ImportLtiContexts.php?page=" + i + "&" + _TSUGI.ajax_session,
            success: function (data) {
                $('.import-body').html(data);
                $('#buttonImport').attr('onclick', "document.getElementById('importForm').submit();");

                if ($('#importModal').is(':hidden')) {
                    //If exercise tab is active, it deactivates and test tab is activated
                    if ($('#li-exercises').addClass('active')) {
                        $('#li-test').addClass('active');
                        $('#li-exercises').removeClass('active');
                        $('#tab-test').addClass('active in');
                        $('#tab-exercise').removeClass('active in');
                    }
                    // Display Modal
                    $('#importModal').modal('show');
                }
            },
            error: function (data) {
                console.error(data.responseText);
            }
        });

    } else if (object = "exercise") {
        $.ajax({
            type: "GET",
            url: "actions/import/ImportLtiContextsExercises.php?page=" + i + "&" + _TSUGI.ajax_session,
            success: function (data) {
                $('.import-body-exercises').html(data);
                $('#buttonImport').attr('onclick', "document.getElementById('importExercisesForm').submit();");
            },
            error: function (data) {
                console.error(data.responseText);
            }
        });
    }
}

global.updateDate = function(ty) {
    var date = new Date(ty);
    $("#modified").text(formatDate(date));
}

global.importLtiContextsPage = function(i = 0, object='test') {
    var object;
    var body;
    if ($("#tab-test").hasClass("active")) {
        object = "test";
        body = ".import-body";
    } else if ($("#tab-exercise").hasClass("active")) {
        object = "exercise";
        body = ".import-body-exercises";
    }
    $.ajax({
        type: "GET",
        url: "actions/import/ImportLtiContextsValue.php?page=" + i + "&action=page&object="+object+"&" + _TSUGI.ajax_session,
        success: function (data) {
            $(body).html(data);
        },
        error: function (data) {
            console.error(data.responseText);
        }
    });
}

global.importLtiContextsButtons = function(value) {
    var object;
    var body;

    //body is used to replace the correct element
    if ($("#tab-test").hasClass("active")) {
        object = "test";
        body = ".import-body";
    } else if ($("#tab-exercise").hasClass("active")) {
        object = "exercise";
        body = ".import-body-exercises";
    }

    $.ajax({
        type: "GET",
        url: "actions/import/ImportLtiContextsValue.php?value=" + value + "&action=add&object=" + object + "&" + _TSUGI.ajax_session,
        success: function (data) {
            $(body).html(data);
        },
        error: function (data) {
            console.error(data.responseText);
        }
    });
}

global.deleteTag = function(value) {
    var object;
    var body;

    //body is used to replace the correct element
    if ($("#tab-test").hasClass("active")) {
        object = "test";
        body = ".import-body";
    } else if ($("#tab-exercise").hasClass("active")) {
        object = "exercise";
        body = ".import-body-exercises";
    }

    $.ajax({
        type: "GET",
        url: "actions/import/ImportLtiContextsValue.php?value=" + value + "&action=delete&object=" + object + "&" + _TSUGI.ajax_session,
        success: function (data) {
            $(body).html(data);
        },
        error: function (data) {
            console.error(data.responseText);
        }
    });
}

global.importMains = function(contextId) {

    $.ajax({
        type: "GET",
        url: "actions/import/ImportMains.php?contextId=" + contextId + "&" + _TSUGI.ajax_session,
        success: function(data) {
            $('#site' + contextId).html(data);
            $('#site' + contextId + '.collapse').collapse();
        },
        error: function(data) {
            console.error(data.responseText);
        }
    });
}

global.showTestInfo = function(testId) {
    element = $('#divTest' + testId);

    if ($(element).is(':visible')) {
        $(element).hide();
    } else if ($(element).is(':hidden')) {
        $(element).show();
    }

}

global.showExercises = function(exerciseId) {
    element = $('#main' + exerciseId);

    if ($(element).is(':visible')) {
        $(element).hide();
    } else if ($(element).is(':hidden')) {
        $(element).show();
    }
}


global.importExercises = function(exerciseId, testId) {
    $.ajax({
        type: "GET",
        url: "actions/import/ImportExercises.php?" + _TSUGI.ajax_session,
        data: {
            exerciseId: exerciseId,
            testId: testId
        },
        success: function (data) {
            $('#main' + exerciseId).html(data);
        },
        error: function (data) {
            console.error(data.responseText);
        }
    });
}
global.editExerciseText = function(exerciseId) {
    var exerciseText = $("#exerciseText" + exerciseId);
    exerciseText.hide();
    $("#exerciseDeleteAction" + exerciseId).hide();
    $("#exerciseEditAction" + exerciseId).hide();
    $("#exerciseReorderAction" + exerciseId).hide();

    var theForm = $("#exerciseTextForm" + exerciseId);

    editor = getCKEditor("exerciseTextInput" + exerciseId);
    theForm.show();
    theForm.find('#exerciseTextInput' + exerciseId).focus()
            .off("keypress").on("keypress", function (e) {
        if (e.which === 13) {
            e.preventDefault();
            if ($('#exerciseTextInput' + exerciseId).val().trim() === '') {
                if (confirmDeleteExerciseBlank(exerciseId)) {
                    // User entered blank exercise text and wants to delete.
                    deleteExercise(exerciseId, true);
                }
            } else {
                // Still has text in exercise. Save it.
                $.ajax({
                    type: "POST",
                    url: theForm.prop("action"),
                    data: theForm.serialize(),
                    success: function (data) {
                        exerciseText.text($('#exerciseTextInput' + exerciseId).val());
                        exerciseText.show();
                        $("#exerciseDeleteAction" + exerciseId).show();
                        $("#exerciseEditAction" + exerciseId).show();
                        $("#exerciseReorderAction" + exerciseId).show();
                        $("#exerciseSaveAction" + exerciseId).hide();
                        $("#exerciseCancelAction" + exerciseId).hide();
                        theForm.hide();
                        $("#flashmessages").html(data.flashmessage);
                        setupAlertHide();
                    }
                });
            }
        }
    });
    $("#exerciseSaveAction" + exerciseId).show()
            .off("click").on("click", function (e) {
        updateCKeditorElements();
        if (editor)
            editor.destroy();
        if ($('#exerciseTextInput' + exerciseId).val().trim() === '') {
            if (confirmDeleteExerciseBlank(exerciseId)) {
                // User entered blank exercise text and wants to delete.
                deleteExercise(exerciseId, true);
            }
        } else {
            // Still has text in exercise. Save it.
            $.ajax({
                type: "POST",
                url: theForm.prop("action"),
                data: theForm.serialize() + '&' + _TSUGI.ajax_session,
                success: function (data) {
                    exerciseText.html($('#exerciseTextInput' + exerciseId).val());
                    exerciseText.show();
                    $("#exerciseDeleteAction" + exerciseId).show();
                    $("#exerciseEditAction" + exerciseId).show();
                    $("#exerciseReorderAction" + exerciseId).show();
                    $("#exerciseSaveAction" + exerciseId).hide();
                    $("#exerciseCancelAction" + exerciseId).hide();
                    theForm.hide();
                    $("#flashmessages").html(data.flashmessage);
                    setupAlertHide();
                }
            });
        }
    });

    $("#exerciseCancelAction" + exerciseId).show()
            .off("click").on("click", function (e) {
        var theText = $("#exerciseText" + exerciseId);
        theText.show();
        theForm.hide();
        $("#exerciseTextInput" + exerciseId).val(theText.text());
        $("#exerciseDeleteAction" + exerciseId).show();
        $("#exerciseEditAction" + exerciseId).show();
        $("#exerciseReorderAction" + exerciseId).show();
        $("#exerciseSaveAction" + exerciseId).hide();
        $("#exerciseCancelAction" + exerciseId).hide();
    });
}

global.editTitleText = function() {
    $("#toolTitle").hide();
    var titleForm = $("#toolTitleForm");
    titleForm.show();
    titleForm.find("#toolTitleInput").focus()
            .off("keypress").on("keypress", function (e) {
        if (e.which === 13) {
            e.preventDefault();
            $.ajax({
                type: "POST",
                dataType: "json",
                url: titleForm.prop("action"),
                data: titleForm.serialize(),
                success: function (data) {
                    $(".title-text-span").text($("#toolTitleInput").val());
                    if ($("#mainTypeSelect") && $("#mainTypeSelect")[0]) {
                        $(".mainType-text-span").text($("#mainTypeSelect")[0].options[$("#mainTypeSelect")[0].value].label);
                    }
                    var titleText = $("#toolTitle");
                    titleText.show();
                    titleForm.hide();
                    $("#toolTitleCancelLink").hide();
                    $("#toolTitleSaveLink").hide();
                    $("#flashmessages").html(data.flashmessage);
                    setupAlertHide();
                }
            });
        }
    });
    $("#toolTitleSaveLink").show()
            .off("click").on("click", function (e) {
        $.ajax({
            type: "POST",
            dataType: "json",
            url: titleForm.prop("action"),
            data: titleForm.serialize(),
            success: function (data) {
                $(".title-text-span").text($("#toolTitleInput").val());
                if ($("#mainTypeSelect").length > 0) {
                    let value = $("#mainTypeSelect")[0].value;
                    let options = $("#mainTypeSelect")[0].options;
                    $(".mainType-text-span").text(options[value].label);
                }
                var titleText = $("#toolTitle");
                titleText.show();
                titleForm.hide();
                $("#toolTitleCancelLink").hide();
                $("#toolTitleSaveLink").hide();
                $("#flashmessages").html(data.flashmessage);
                setupAlertHide();
            }
        });
    });
    $("#toolTitleCancelLink").show()
            .off("click").on("click", function (e) {
        var titleText = $("#toolTitle");
        titleText.show();
        titleForm.hide();
        $("#toolTitleInput").val($(".title-text-span").text());
        $("#toolTitleCancelLink").hide();
        $("#toolTitleSaveLink").hide();
    });
}

//unused?
global.moveExerciseUp = function(exerciseId, testId) {
    $.ajax({
        type: "POST",
        url: "actions/ReorderExercise.php?" + _TSUGI.ajax_session,
        dataType: 'text',
        data: {
            exercise_id: exerciseId,
            test_id: testId
        },
        success: function (data) {
            var theExerciseMoved = $("#exerciseRow" + exerciseId);
            theExerciseMoved.hide();
            var currentNumber = theExerciseMoved.data("exercise-number");
            if (currentNumber === 1) {
                // Move to bottom
                $("#newExerciseRow").before(theExerciseMoved);
            } else {
                // Move up one
                theExerciseMoved.prev().before(theExerciseMoved);
            }
            // Fix up exercise numbers
            var exerciseNum = 1;
            $(".exercise-number").each(function () {
                $(this).text(exerciseNum + ".");
                $(this).parent().data("exercise-number", exerciseNum);
                exerciseNum++;
            });

            theExerciseMoved.fadeIn("fast");

            $("#flashmessages").html(data.flashmessage);
            setupAlertHide();
        }
    });
}

global.answerExercise = function(exerciseId, exerciseNum) {
    var answerForm = $("#answerForm" + exerciseId);
    window.codeEditor.save()
    const solutionCode = codeEditor.getValue();
    $.ajax({
        type: "POST",
        url: answerForm.prop("action"),
        data: answerForm.serialize() + '&exerciseNum=' + exerciseNum + '&' + _TSUGI.ajax_session,
        success: function (data) {

            $('.answer-output pre').html(data);
            $('#answerSavedText').html(solutionCode);

            //If the answer is not empty and it is the first time it has been answered, the usage modal opens
            if (data) {
                $('.usage-modal').modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true,

                })
            }
            document.querySelector('.exercises-nav-header .active .answer-info-box')

            updateExerciseBadge(data);

            $("#answerSavedText").text(data.answerText);
            $("#modified").text(formatDate(new Date()));
        },
        error: function (data) {
            alert('ERROR');
        }
    });
}

const updateExerciseBadge = (value) => {
    if(!value){
        return;
    }
    const val = `${value}`.toLowerCase();

    const activeItem = '.exercises-nav-header .active .answer-info-box';
    const activeItemIcon = '.exercises-nav-header .active .answer-info-box .fa';

    const studentFooterItem = '.student-footer .answer-info-box';
    const studentFooterItemLabel = '.student-footer .result-text';
    const studentFooterItemIcon = '.student-footer .answer-info-box .fa';

    const elements = document.querySelectorAll(`${activeItem}, ${studentFooterItem}`);
    const elementIcons = document.querySelectorAll(`${activeItemIcon}, ${studentFooterItemIcon}`);

    //console.log({elements});

    elements.forEach(el => {
        el.classList.remove('bg-green-600')
        el.classList.remove('bg-red-600')
        el.classList.remove('bg-yellow-500')
    })
    elementIcons.forEach(el => {
        el.classList.remove('fa-check')
        el.classList.remove('fa-times')
        el.classList.remove('fa-file')
    })
    document.querySelector(studentFooterItemLabel).textContent = value

    if(val.startsWith('correct')) {
        elements.forEach(el => {
            el.classList.add('bg-green-600')
        })
        elementIcons.forEach(el => {
            el.classList.add('fa-check')
        })
    } else if (val.includes('incorrect')) {
        elements.forEach(el => {
            el.classList.add('bg-red-600')
        })
        elementIcons.forEach(el => {
            el.classList.add('fa-times')
        })
    } else {
        elements.forEach(el => {
            el.classList.add('bg-yellow-500')
        })
        elementIcons.forEach(el => {
            el.classList.add('fa-file')
        })
    }
}

global.formatDate = function(dateVal) {
    var newDate = new Date(dateVal);
    var sMonth = padValue(newDate.getMonth() + 1);
    var sDay = padValue(newDate.getDate());
    var sYear = newDate.getFullYear();
    var sHour = newDate.getHours();
    var sMinute = padValue(newDate.getMinutes());
    var sSecond = padValue(newDate.getSeconds());
    var sAMPM = "AM";
    var iHourCheck = parseInt(sHour);

    if (iHourCheck > 12) {
        sAMPM = "PM";
        sHour = iHourCheck - 12;
    }
    else if (iHourCheck === 0) {
        sHour = "12";
    }
    sHour = padValue(sHour);

    return `${sDay}/${sMonth}/${sYear} - ${sHour}:${sMinute}:${sSecond} ${sAMPM}`

    // return sMonth + "/" + sDay + "/" + sYear + " - " + sHour + ":" + sMinute + " " + sAMPM;
}

global.padValue = function(value) {
    return (value < 10) ? "0" + value : value;
}

global.setupAlertHide = function() {
    // On load hide any alerts after 3 seconds
    /*setTimeout(function() {
     $(".alert-banner").slideUp();
     }, 3000);*/
}

global.getCKEditor = function(elementName) {
    let editor;
    for (var i in CKEDITOR.instances) {
        if (elementName == CKEDITOR.instances[i].name)
            editor = CKEDITOR.instances[i];
    }
    if (!editor)
        editor = CKEDITOR.replace(elementName);
    return editor;
}

global.updateCKeditorElements = function() {
    for (var i in CKEDITOR.instances) {
        CKEDITOR.instances[i].updateElement();
    }
}

global.resetCKeditorElements = function() {
    for (var i in CKEDITOR.instances) {
        let element = $('#' + i);
        if(element && element[0]) {
            CKEDITOR.instances[i].setData(element[0].value);
        }
    }
}

global.resetForm = function($form) {
    $form.find('input:text, input:password, input:file, textarea').val(''); // agregar select
    $form.find('input:radio, input:checkbox')
        .removeAttr('checked').removeAttr('selected');
    resetCKeditorElements();
}

//This method calls the action that sends the usage to the repository
global.sendUsage = function(exerciseId) {
    var usageForm = $("#usageForm"+exerciseId);

    //url = actions/SendUsage.php
    $.ajax({
        type: "POST",
        dataType: "text",
        url: usageForm.prop("action"),
        data: usageForm.serialize() + '&exerciseId='+exerciseId+'&' + _TSUGI.ajax_session,
        success: function(data) {
            $('#usageModal'+exerciseId).modal('hide');
            setTimeout(() => {
                $('#usageModal'+exerciseId).remove()
                // location.reload();
            },350)
        },
        error: function(data){
            alert('ERROR');
        }
    });
}

//this method adds the keyword to the search parameters
global.keyword = function() {
    var keyword = $("#keywordText").val();
    if (keyword) {
        var object;
        var body;
        if ($("#tab-test").hasClass("active")) {
            object = "test";
            body = ".import-body";
        } else if ($("#tab-exercise").hasClass("active")) {
            object = "exercise";
            body = ".import-body-exercises";
        }

        $.ajax({
            type: "GET",
            url: "actions/import/ImportLtiContextsValue.php?value=" + keyword + "&action=add&object=" + object + "&" + _TSUGI.ajax_session,
            success: function (data) {
                $(body).html(data);
            },
            error: function (data) {
                console.error(data.responseText);
            }
        });

    }
}

//this method adds the punctuation to the search parameters
global.score = function() {
    var score = $("#customRange1").val();
         var object;
        var body;
        if ($("#tab-test").hasClass("active")) {
            object = "test";
            body = ".import-body";
        } else if ($("#tab-exercise").hasClass("active")) {
            object = "exercise";
            body = ".import-body-exercises";
        }

        //if the punctuation is 0 the parameter is cleared
        if(score==0){
            score="delete";
        }
    $.ajax({
        type: "GET",
        url: "actions/import/ImportLtiContextsValue.php?value=" + score + "&action=add&object=" + object +"&" + _TSUGI.ajax_session,
        success: function (data) {
            $(body).html(data);
        },
        error: function (data) {
            console.error(data.responseText);
        }
    });
}

global.deleteExercise = function(exerciseId, skipconfirm = false) {
    $('#confirm').modal('show')
            $('#confirm').on('click', '#delete', function (e) {
                $.ajax({
                    type: "POST",
                    url: "actions/DeleteExercise.php?" + _TSUGI.ajax_session,
                    dataType: "text",
                    data: {
                        exercise_id: exerciseId,
                    },
                    success: function (data) {
                        $("#exerciseRow" + exerciseId).remove();
                        // Fix up exercise numbers
                        var exerciseNum = 1;
                        $(".exercise-number").each(function () {
                            $(this).text(exerciseNum);
                            $(this).parent().data("exercise-number", exerciseNum);
                            exerciseNum++;
                        });
                        // Fix new exercise number
                        $("#newExerciseRow").data("exercise-number", exerciseNum);
                        $("#newExerciseNumber").text(exerciseNum + ".");

                        $("#flashmessages").html(data.flashmessage);
                        setupAlertHide();
                    },
                    error: function (data) {
                        alert("error");
                    }
                });
            })
            .on('click', '#cancel', function (e) {
                e.preventDefault();
                $('#confirm' + exerciseId).modal.model('close');
            });
}

//this method updates the exercise numbers
global.updateList = function(exerciseId, oldIndex, newIndex) {
    $.ajax({
        type: "POST",
        url: "actions/ReorderExercise.php?" + _TSUGI.ajax_session,
        dataType: 'text',
        data: {
            exerciseId: exerciseId,
            oldIndex: oldIndex,
            newIndex: newIndex
        },
        success: function (data) {
            var exerciseNum = 1;
            $(".exercise-number").each(function () {
                $(this).text(exerciseNum+".-");
                $(this).parent().data("exercise-number", exerciseNum);
                exerciseNum++;
            });
            $("#flashmessages").html(data.flashmessage);
            setupAlertHide();
        },
        error: function (data) {
            alert(data);
        }
    });
    return false;
}


global.showCreateModal = function() {
    var language = $("#typeSelect").val();

    $.ajax({
        type: "GET",
        url: "actions/newExerciseForm.php?language=" + language + "&" + _TSUGI.ajax_session,
        success: function (data) {
            $('#createBody').html(data);
        },
        error: function (data) {
            console.error(data.responseText);
        }
    });
    $('#createModal').modal('show');
}

global.typeChange = function(){
     var language = $("#typeSelect").val();

     $.ajax({
        type: "GET",
        url: "actions/newExerciseForm.php?language=" +language+"&"+ _TSUGI.ajax_session,
        success: function(data) {
            $('#createBody').html(data);
        },
        error: function(data) {
            console.error(data.responseText);
        }
    });

}

global.showNewExerciseRow = function() {

    const invalidClassName = "invalid-field";
    const statementField = getCKEditor('exercise[statement]').getData();
    const solutionField =  codeEditor.getValue();
    const tittleField = document.getElementById("exerciseTitleText");
    const inputField = document.getElementById("input");
    const outputField = document.getElementById("output");
    const statementLabel = document.querySelector('label[for="exercise[statement]"]');
    const solutionLabel = document.querySelector('label[for="exercise[exercise_solution]"]');
    const libraryField = document.getElementById('filesToUpload');

    var theForm = $("#exerciseTextForm-1");
    updateCKeditorElements();
    window.codeEditor.save();

    let booleanValues = [];

    let fieldValues = [
        {
            key: 'title',
            value: tittleField.value,
            element: tittleField
        },
        {
            key: 'statement',
            value: statementField,
            element: statementLabel
        },
        {
            key: 'solution',
            value: solutionField,
            element: solutionLabel
        },
        {
            key: 'input',
            value: inputField.value,
            element: inputField
        },
        {
            key: 'output',
            value: outputField.value,
            element: outputField
        },
        {
            key: 'filesToUpload',
            value: libraryField.files,
            element: libraryField
        }
    ]

    fieldValues.forEach((el , i) => {
        if(!el.value){
            el.element.classList.add(invalidClassName)
        }else{
            el.element.classList.remove(invalidClassName)
        }
        booleanValues[i] = !!el.value
    })

    if(booleanValues.every(el => el)){
        $.ajax({
            type: "POST",
            dataType: "json",
            url: theForm.prop("action"),
            data: theForm.serialize() +'&' + _TSUGI.ajax_session,
            success: function (data) {
             resetForm(theForm);
             location = location.href.replace("create-exercise.php?", "exercises-list.php?")
            },
            error: function (data) {
                console.error('FAIL');
                console.error(data);
            }
        });
    }else{
        $("#requiredAlert").removeClass("hidden");
    }
}

global.exerciseCancelAction = function() {
    console.log("Dentro del Cancel");
    CKEDITOR.instances['exercise[statement]'].setData('');
    codeEditor.setValue("");
    var theForm = $("#exerciseTextForm-1");
    resetForm(theForm);
}

global.showImportExercise = function() {
    var theForm = $("#importExercisesForm");

    $('#newExerciseRow').html("");
    $.ajax({
        type: "POST",
        dataType: "json",
        url: theForm.prop("action"),
        data: theForm.serialize() + '&' + _TSUGI.ajax_session,
        success: function (data) {
            resetForm(theForm);
            location.reload();
        },
        error: function (data) {
            console.error(data);
        }
    });
}

global.showModal = function(id){
    var modalCode = document.getElementById("modalCode" + id);
    var btnModalCode = document.getElementById("btnModalCode" + id);


    modalCode.style.display = "block";


    window.onclick = function(event) {
        if (event.target == modalCode) {
            modalCode.style.display = "none";
        }
    }
}