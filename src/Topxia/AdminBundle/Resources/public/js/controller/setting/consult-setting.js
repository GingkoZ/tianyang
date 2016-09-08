define(function(require, exports, module) {

    var Notify = require('common/bootstrap-notify');
    var WebUploader = require('edusoho.webuploader');
    exports.run = function() {

        var $form = $("#consult-setting-form");
        var uploader = new WebUploader({
            element: '#consult-upload'
        });

        uploader.on('uploadSuccess', function(file, response ) {
            var url = $("#consult-upload").data("gotoUrl");

            $.post(url, response ,function(data){
                $("#consult-container").html('<img src="' + data.url + '">');
                $form.find('[name=webchatURI]').val(data.path);
                $("#consult-webchat-del").show();
                Notify.success('上传微信二维码成功！');
            });    
        });

        $('[data-role=item-add]').on('click',function(){
            var nextIndex = $(this).attr('data-length');
            nextIndex = parseInt(nextIndex); 
            if( nextIndex > 9 ) {
                Notify.danger('最多设置10个..');
                return;
            }
            var $parent = $('#'+$(this).attr('data-parentId'));
            var $first = $parent.children(':first');
            var $template = $('[data-role=template]');

            var fisrtplaceholder = $first.find('input:first').attr('placeholder');
            var middleplaceholder = $first.find('input:eq(1)').attr('placeholder');
            var lastvalue = $first.find('input:eq(2)').attr('value');
            var firstname = $first.find('input:first').attr('name');
            var middlename = $first.find('input:eq(1)').attr('name');
            var lastname = $first.find('input:eq(2)').attr('name');
            firstname = firstname.replace(/\d/, nextIndex);
            middlename = middlename.replace(/\d/, nextIndex);
            lastname = lastname.replace(/\d/, nextIndex);
            $template.find('input:first').attr('placeholder', fisrtplaceholder);
            $template.find('input:eq(1)').attr('placeholder', middleplaceholder);
            $template.find('input:eq(2)').attr('value',lastvalue);
            $template.find('input:first').attr('name', firstname);
            $template.find('input:eq(1)').attr('name', middlename);
            $template.find('input:eq(2)').attr('name', lastname);
            $parent.append($template.html());

            $('[data-role=item-delete]').on('click',function(){
                $(this).parent().parent().remove();
            });
            
            nextIndex = nextIndex + 1;
            $(this).attr('data-length', nextIndex);
        });
        
        $('[data-role=phone-item-delete]').on('click',function(){
            $(this).closest('.has-feedback').remove();
        });
        $('[data-role=phone-item-add]').on('click',function(){
            var nextIndex = $(this).attr('data-length');
            nextIndex = parseInt(nextIndex); 
            if( nextIndex > 9 ) {
                Notify.danger('最多设置10个..');
                return;
            }
            var $parent = $('#'+$(this).attr('data-parentId'));
            var $first = $parent.children(':first');
            var $template = $('[data-role=phone-template]');

            var fisrtplaceholder = $first.find('input:first').attr('placeholder');
            var middleplaceholder = $first.find('input:eq(1)').attr('placeholder');
            var firstname = $first.find('input:first').attr('name');
            var middlename = $first.find('input:eq(1)').attr('name');
            firstname = firstname.replace(/\d/, nextIndex);
            middlename = middlename.replace(/\d/, nextIndex);
            $template.find('input:first').attr('placeholder', fisrtplaceholder);
            $template.find('input:eq(1)').attr('placeholder', middleplaceholder);
            $template.find('input:first').attr('name', firstname);
            $template.find('input:eq(1)').attr('name', middlename);
            $parent.append($template.html());
            $('[data-role=phone-item-delete]').on('click',function(){
                $(this).closest('.has-feedback').remove();
            });
            
            nextIndex = nextIndex + 1;
            $(this).attr('data-length', nextIndex);
        });

        $('[data-parentId=consult-qqgroup]').on('click',function(){
            var nextIndex = $(this).attr('data-length');
            nextIndex = parseInt(nextIndex);
            if( nextIndex > 9 ) {
                Notify.danger('最多设置10个..');
                return;
            }
            var $parent = $('#'+$(this).attr('data-parentId'));
            var $first = $parent.children(':first');
            var $template = $('[data-role=qqGroupTemplate]');

            var firstPlaceholder = $first.find('input:eq(0)').attr('placeholder');
            var midPlaceholder = $first.find('input:eq(1)').attr('placeholder');
            var lastPlaceholder = $first.find('input:eq(2)').attr('placeholder');
            var firstName = $first.find('input:eq(0)').attr('name');
            var midName = $first.find('input:eq(1)').attr('name');
            var lastName = $first.find('input:eq(2)').attr('name');
            firstName = firstName.replace(/\d/, nextIndex);
            midName = midName.replace(/\d/, nextIndex);
            lastName = lastName.replace(/\d/, nextIndex);
            $template.find('input:eq(0)').attr('placeholder', firstPlaceholder);
            $template.find('input:eq(1)').attr('placeholder', midPlaceholder);
            $template.find('input:eq(2)').attr('placeholder', lastPlaceholder);

            $template.find('input:eq(0)').attr('name', firstName);
            $template.find('input:eq(1)').attr('name', midName);
            $template.find('input:eq(2)').attr('name', lastName);

            $parent.append($template.html());

            $('[data-role=item-delete]').on('click',function(){
                $(this).parent().parent().remove();
            });

            nextIndex = nextIndex + 1;
            $(this).attr('data-length', nextIndex);
        });
        
        $('[data-role=item-delete]').on('click',function(){
                $(this).parent().parent().remove();
        });

        $('#consult-webchat-del').on('click',function(){
            if (!confirm('确认要删除吗？')) return false;
            $.post($(this).data('url'),function(response){
               $("#consult-container").html('');
               $('[name=webchatURI]').val('');
               $("#consult-webchat-del").hide();
            });
        });
    }
});