define(function(require, exports, module) {

    var Notify = require('common/bootstrap-notify');
    require("jquery.bootstrap-datetimepicker");
    var validator = require('bootstrap.validator');

    exports.run = function() {

        var $datePicker = $('#datePicker');
        var $table = $('#user-table');

        $table.on('click', '.lock-user, .unlock-user', function() {
            var $trigger = $(this);

            if (!confirm('真的要' + $trigger.attr('title') + '吗？')) {
                return ;
            }

            $.post($(this).data('url'), function(html){
                Notify.success($trigger.attr('title') + '成功！');
                 var $tr = $(html);
                $('#' + $tr.attr('id')).replaceWith($tr);
            }).error(function(){
                Notify.danger($trigger.attr('title') + '失败');
            });
        });

        $table.on('click', '.send-passwordreset-email', function(){
            Notify.info('正在发送密码重置验证邮件，请稍等。', 60);
            $.post($(this).data('url'),function(response){
                Notify.success('密码重置验证邮件，发送成功！');
            }).error(function(){
                Notify.danger('密码重置验证邮件，发送失败');
            });
        });

        $table.on('click', '.send-emailverify-email', function(){
            Notify.info('正在发送Email验证邮件，请稍等。', 60);
            $.post($(this).data('url'),function(response){
                Notify.success('Email验证邮件，发送成功！');
            }).error(function(){
                Notify.danger('Email验证邮件，发送失败');
            });
        });

                var $userSearchForm = $('#user-search-form');

                $('#user-export').on('click', function() {
                   var self = $(this);
                   var data = $userSearchForm.serialize();
                   self.attr('data-url', self.attr('data-url')+"?"+data);
                });

        $("#startDate").datetimepicker({
            autoclose: true,
        }).on('changeDate',function(){
            $("#endDate").datetimepicker('setStartDate',$("#startDate").val().substring(0,16));
        });

        $("#startDate").datetimepicker('setEndDate',$("#endDate").val().substring(0,16));

        $("#endDate").datetimepicker({
            autoclose: true,
        }).on('changeDate',function(){

            $("#startDate").datetimepicker('setEndDate',$("#endDate").val().substring(0,16));
        });

        $("#endDate").datetimepicker('setStartDate',$("#startDate").val().substring(0,16));
    };

});