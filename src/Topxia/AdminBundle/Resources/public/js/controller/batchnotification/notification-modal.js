define(function(require, exports, module) {

    require('ckeditor');
    var Uploader = require('upload');
    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    var Notify = require('common/bootstrap-notify');
    exports.run = function() {
        $("#notification-operate-publish").click(function(){
            $('#hidden').val('publish');
        });
        // group: 'default'
        var ckeditor=CKEDITOR.replace('richeditor-body-field', {
            toolbar: 'Full',
            filebrowserImageUploadUrl: $('#richeditor-body-field').data('imageUploadUrl'),
            filebrowserFlashUploadUrl: $('#richeditor-body-field').data('flashUploadUrl'),
            height: 300
        });
        var validator = new Validator({
            element: '#notification-form',
        });
        
        validator.addItem({
            element: '[name=content]',
            required: true
        });
        validator.addItem({
            element: '[name=title]',
            rule: 'maxlength{max:50}',
            required: true
        });
        validator.on('formValidate', function(elemetn, event) {
            ckeditor.updateElement();
        });
        validator.on('formValidated', function(error, results, $form) {
            if (error) {
                return false;
            }
            if($('#hidden').val() == 'publish'){
                $('#notification-operate-publish').button('loading').addClass('disabled');
                Notify.success('发布成功！');
            }
            else{
                $('#notification-operate-save').button('loading').addClass('disabled');
                Notify.success('保存成功！');
            }
        });
     }; 
});