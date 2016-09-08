define(function(require, exports, module) {
    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    var Notify = require('common/bootstrap-notify');

    exports.run = function() {
 require('/bundles/customadmin/js/controller/organization/organization-check-modal').run();
        var $modal = $('#student-create-form').parents('.modal');
        var $table = $('#course-student-list');

        var validator = new Validator({
            element: '#student-create-form',
            autoSubmit: false,
            onFormValidated: function(error, results, $form) {
                if (error) {
                    return false;
                }

                var $btn = $("#student-create-form-submit");
                $btn.button('submiting').addClass('disabled');
                
                $.post($form.attr('action'), $form.serialize(), function(html) {
                    Notify.success('添加学员操作成功!');
                    document.location.reload();
                }).error(function(){
                    Notify.danger('添加学员操作失败!');
                    $btn.button('reset').removeClass('disabled');
                });

            }
        });



    };

});