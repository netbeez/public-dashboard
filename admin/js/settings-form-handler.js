/**
 * Client Side Form Handler
 */

$(function() {
    $(".agent-list .selection-list-item").on("click", function(){
        $(this).toggleClass("selected");

        var agentId = $(this).data('value');

        if($(this).hasClass("selected")){
            if(!$("#selected-agent-" + agentId).hasClass("selected")){
                $("#selected-agent-" + $(this).data('value')).addClass("selected");
            }
        } else {
            $("#selected-agent-" + $(this).data('value')).removeClass("selected").removeClass("maintenance-status-active");
        }

        updateSelectionCount("agent");
    });

    $(".target-list .selection-list-item").on("click", function(){
        $(this).toggleClass("selected");

        var targetId = $(this).data('value');

        if($(this).hasClass("selected")){
            if(!$("#selected-target-" + targetId).hasClass("selected")){
                $("#selected-target-" + $(this).data('value')).addClass("selected");
            }
        } else {
            $("#selected-target-" + $(this).data('value')).removeClass("selected").removeClass("maintenance-status-active");
        }

        updateSelectionCount("target");
    });

    $("#select-all-agents").on("click", function(){
        $(".agent-list .selection-list-item").each(function(){
            if(!$(this).hasClass("selected")){
                $(this).addClass("selected");
            }

            var agentId = $(this).data('value');

            if($(this).hasClass("selected")){
                if(!$("#selected-agent-" + agentId).hasClass("selected")){
                    $("#selected-agent-" + $(this).data('value')).addClass("selected");
                }
            }
        });

        updateSelectionCount("agent");
    });

    $("#select-all-targets").on("click", function(){
        $(".target-list .selection-list-item").each(function(){
            if(!$(this).hasClass("selected")){
                $(this).addClass("selected");
            }

            var targetId = $(this).data('value');

            if($(this).hasClass("selected")){
                if(!$("#selected-target-" + targetId).hasClass("selected")){
                    $("#selected-target-" + $(this).data('value')).addClass("selected");
                }
            }
        });

        updateSelectionCount("target");
    });

    $(".selected-agents-list .deselect-option").on("click", function(){
        var agentId = $(this).parent().data('value');
        $(this).parent().removeClass("selected");
        $(this).parent().removeClass("maintenance-status-active");

        $(".agent-list .id-" + agentId).removeClass("selected");

        updateSelectionCount("agent");
    });

    $(".selected-targets-list .deselect-option").on("click", function(){
        var targetId = $(this).parent().data('value');
        $(this).parent().removeClass("selected");
        $(this).parent().removeClass("maintenance-status-active");

        $(".target-list .id-" + targetId).removeClass("selected");

        updateSelectionCount("target");
    });

    $("#deselect-all-agents").on("click", function(){
        $(".selected-agents-list .selected-item.selected").each(function(){
            $(this).removeClass("selected");
            $(this).removeClass("maintenance-status-active");
        });

        $(".agent-list .selection-list-item.selected").each(function(){
            $(this).removeClass("selected");
        });

        updateSelectionCount("agent");
    });

    $("#deselect-all-targets").on("click", function(){
        $(".selected-targets-list .selected-item.selected").each(function(){
            $(this).removeClass("selected");
            $(this).removeClass("maintenance-status-active");
        });

        $(".target-list .selection-list-item.selected").each(function(){
            $(this).removeClass("selected");
        });

        updateSelectionCount("target");
    });

    $(".selected-item .maintenance-status-toggle").on("click", function(){
        $(this).parent().toggleClass("maintenance-status-active");
    });

    $("#agent-search").on("click", function(){
        var text = $(".agents-filter").val();
        $(".agent-list .selection-list-item").hide().each(function(){
           if($(this).text().toUpperCase().indexOf(text.toUpperCase()) != -1){
               $(this).show();
           }
        });
    });

    $("#target-search").on("click", function(){
        var text = $(".targets-filter").val();
        $(".target-list .selection-list-item").hide().each(function(){
           if($(this).text().toUpperCase().indexOf(text.toUpperCase()) != -1){
               $(this).show();
           }
        });
    });


    $("#admin-settings-form").submit(function(event) {

        var settingsData = {};

        settingsData['selected_agents'] = [];
        settingsData['agents_under_maintenance'] = [];
        settingsData['selected_targets'] = [];
        settingsData['targets_under_maintenance'] = [];

        $(".selected-agents-list .selected-item.selected").each(function(){
            settingsData['selected_agents'].push(parseInt($(this).data('value')));
        });

        $(".selected-agents-list .selected-item.maintenance-status-active").each(function(){
            settingsData['agents_under_maintenance'].push($(this).data('value'));
        });

        $(".selected-targets-list .selected-item.selected").each(function(){
            settingsData['selected_targets'].push($(this).data('value'));
        });

        $(".selected-targets-list .selected-item.maintenance-status-active").each(function(){
            settingsData['targets_under_maintenance'].push($(this).data('value'));

        });

        settingsData['selected_agents'] = _.uniq(settingsData['selected_agents']);
        settingsData['agents_under_maintenance'] = _.uniq(settingsData['agents_under_maintenance']);
        settingsData['selected_targets'] = _.uniq(settingsData['selected_targets']);
        settingsData['targets_under_maintenance'] = _.uniq(settingsData['targets_under_maintenance']);
        settingsData['refresh_interval'] = $("#refresh-interval").val();


        $.ajax({
            url: 'settings-form-handler.php',
            type: 'POST',
            data: settingsData
        }).done(function(data){
            console.log(data);
            $("#save-button-icon")
                .removeClass("fa-square-o")
                .addClass("fa-check-square-o");
        });

        event.preventDefault();
    });

    function updateSelectionCount(componentType){
        var selectedCount = 0;
        $(".selected-" + componentType + "s-list .selected-item.selected").each(function(){
            selectedCount++;
        });

        $("#num-of-" + componentType + "s-selected").text(selectedCount);

        if(selectedCount > 0){
            setSelectionSuccess(true, componentType);
            $("#save-settings").prop("disabled", false);
        } else if (selectedCount == 0){
            setSelectionSuccess(false, componentType);
            $("#save-settings").prop("disabled", true);
        }
    }

    function setSelectionSuccess(success, componentType) {
        if(success) {
            $("#" + componentType + "s-selected-list").animate({backgroundColor: "#D0FF8B"}, 300);
            $("#" + componentType + "s-selected-list #selected-components-list-title").removeClass('label-important').addClass('label-success');
        } else {
            $("#" + componentType + "s-selected-list").animate({backgroundColor: "#FFBEB1"}, 300);
            $("#" + componentType + "s-selected-list #selected-components-list-title").removeClass('label-success').addClass('label-important');
        }
    }

});
