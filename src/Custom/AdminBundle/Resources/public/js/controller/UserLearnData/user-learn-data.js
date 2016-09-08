define(function(require, exports, module) {
    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    var Notify = require('common/bootstrap-notify');
	require('jquery.select2-css');
    require('jquery.select2');
    require("jquery.bootstrap-datetimepicker");
    exports.run = function() {

      orgLearnMore();

    function  orgLearnMore ()  {
      var toggleBtn = $("#org-learn-more");
      toggleBtn.data("toggle", true);

      toggleBtn.click(function(){

            if(toggleBtn.data("toggle")) {
              $(this).find(".glyphicon").removeClass("glyphicon-chevron-down").addClass("glyphicon-chevron-up");
              $("#org-learn-data-table").show();
               toggleBtn.data("toggle", false);
            } else {
              $(this).find(".glyphicon").removeClass("glyphicon-chevron-up").addClass("glyphicon-chevron-down");
              $("#org-learn-data-table").hide();
              toggleBtn.data("toggle", true);
            }      
      });


    }

    	$("#startTime, #endTime").datetimepicker({
            language: 'zh-CN',
            autoclose: true,
            format: 'yyyy-mm-dd',
            minView: 'month'
        });	
 		require('/bundles/customadmin/js/controller/organization/organization-check-modal').run();


     $('#course_name').select2({
            ajax: {
                url: app.arguments.courseMatchUrl + '#',
                dataType: 'json',
                quietMillis: 100,
                data: function(term, page) {
                    return {
                        q: term,
                        page_limit: 10
                    };
                },
                results: function(data) {

                    var results = [];

                    $.each(data, function(index, item) {

                        results.push({
                            id: item.id,
                            name: "(No."+item.id+") - "+item.name
                        });
                    });

                    return {
                        results: results
                    };

                }
            },
            initSelection: function(element, callback) {
                var data = [];
                data['id'] = element.data('id');
                data['name'] = element.data('name');
                element.val(element.data('id'));
                callback(data);
            },
            formatSelection: function(item) {
                return item.name;
            },
            formatResult: function(item) {
                return item.name;
            },
            width: 'off',
            multiple: false,
            placeholder: "请选择课程",
            createSearchChoice: function() {
                return null;
            }
        });

    };

});
