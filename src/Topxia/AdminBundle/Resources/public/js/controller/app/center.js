define(function(require, exports, module) {

    exports.run = function() {
        var $element = $('#app-table-container');

        require('../../util/short-long-text')($element);

        $('input:checkbox[name="appTypeChoices"]').on("change", function() {
            var element = $(this);
            var hidden = $("#type").attr("value");
            if (element.attr("id") == "installedApps" && element.prop('checked')) {
                window.location.href = $(this).data('url');
            } else {
                window.location.href = $(this).data('url');
            }
        });

        $.post('/admin/app/upgrades_count', function(count){

            if (count > 0) {

                $('.app-upgrade').append("<span class=\"badge mls\" style=\"background-color:#FF3333\">"+count+"</span>");
            }

        });
    };

});