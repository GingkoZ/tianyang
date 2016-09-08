define(function(require, exports, module) {
    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    var Notify = require('common/bootstrap-notify');
	
    exports.run = function() {
 
 		var $modal = $('#student-learn-data-form').parents('.modal');
       

        var validator = new Validator({
            element: '#student-learn-data-form',
            autoSubmit: true,
            onFormValidated: function(error, results, $form) {
            

                var $btn = $("#student-learn-export-form-submit");
                $btn.button('submiting').addClass('disabled');
                
                $.post($form.attr('action'), $form.serialize(), function(html) {
                   
                    $modal.modal('hide');
                    Notify.success('导出成功!');
                }).error(function(){
                    Notify.danger('导出失败!');
                    $btn.button('reset').removeClass('disabled');
                });

            }
        });


  
    };

});