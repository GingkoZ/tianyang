define(function(require, exports, module) {
    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    var SmsSender = require('../widget/sms-sender');
    
    exports.run = function() {
        var validator = new Validator({
                element: '#settings-find-pay-password-form',
                onFormValidated: function(error){
                    if (error) {
                        return false;
                    }
                        $('#password-save-btn').button('submiting').addClass('disabled');
                    }
            });

            validator.addItem({
                element: '[name="mobile"]',
                required: true,
                rule: 'phone',
                onItemValidated: function(error, message, eleme) {
                    if (error) {
                        $('.js-sms-send').addClass('disabled');
                        return;
                    } else {
                        $('.js-sms-send').removeClass('disabled');
                    }
                }            
            });

            if ($("#getcode_num").length > 0){
            
                $("#getcode_num").click(function(){ 
                    $(this).attr("src",$("#getcode_num").data("url")+ "?" + Math.random()); 
                }); 

                validator.addItem({
                    element: '[name="captcha_num"]',
                    required: true,
                    rule: 'alphanumeric remote',
                    onItemValidated: function(error, message, eleme) {
                        if (message == "验证码错误"){
                            $('.js-sms-send').addClass('disabled');
                            $("#getcode_num").attr("src",$("#getcode_num").data("url")+ "?" + Math.random()); 
                        } else {
                            $('.js-sms-send').removeClass('disabled');
                        }
                    }                
                });
            };


            if($('input[name="sms_code"]').length>0){
                validator.addItem({
                    element: '[name="sms_code"]',
                    required: true,
                    triggerType: 'submit',
                    rule: 'integer fixedLength{len:6} remote',
                    display: '短信验证码'           
                });
            }
            
            /*var smsSender = new SmsSender({
                element: '.js-sms-send',
                url: $('.js-sms-send').data('url'),
                smsType:'sms_forget_pay_password'       
            });*/
        
    };

});