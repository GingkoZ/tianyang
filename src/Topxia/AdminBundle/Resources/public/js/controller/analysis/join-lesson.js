define(function(require, exports, module) {
    var Morris=require("morris");
    require("jquery.bootstrap-datetimepicker");
    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    var autoSubmitCondition=require("./autoSubmitCondition.js");
    var now = new Date();
    exports.run = function() {

        if($('#data').length > 0){
                    var data = eval ("(" + $('#data').attr("value") + ")");

                    Morris.Line({
                      element: 'line-data',
                      data: data,
                      xkey: 'date',
                      ykeys: ['count'],
                      labels: ['加入学习数'],
                      xLabels:"day"
                    });
        }


         $("[name=endTime]").datetimepicker({
            language: 'zh-CN',
            autoclose: true,
            format: 'yyyy-mm-dd',
            minView: 'month'
        });
        $('[name=endTime]').datetimepicker('setEndDate', now);
        $('[name=endTime]').datetimepicker('setStartDate', $('#joinLessonStartDate').attr("value"));
        
        $("[name=startTime]").datetimepicker({
            language: 'zh-CN',
            autoclose: true,
            format: 'yyyy-mm-dd',
            minView: 'month'
        });
        $('[name=startTime]').datetimepicker('setEndDate', now);
        $('[name=startTime]').datetimepicker('setStartDate', $('#joinLessonStartDate').attr("value"));

        var validator = new Validator({          
            element: '#operation-form'});

        validator.addItem({
            element: '[name=startTime]',
            required: true,
            rule:'date_check'
        });

        validator.addItem({
            element: '[name=endTime]',
            required: true,
            rule:'date_check'
        });

        validator.addItem({
            element: '[name=analysisDateType]',
            required: true
        });
        autoSubmitCondition.autoSubmitCondition();
    };

});