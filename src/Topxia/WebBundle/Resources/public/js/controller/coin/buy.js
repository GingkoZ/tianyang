define(function(require, exports, module) {
    
    var Notify = require('common/bootstrap-notify');
    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);

    exports.run = function() {
        var validator = new Validator({
            element: '#coin-buy',
            failSilently: true,
            onFormValidated: function(error){
                if (error) {
                    return false;
                }
                $('#coin-pay').button('submiting').addClass('disabled');
            }
        });

        validator.addItem({
            element: '[name="amount"]',
            required: true,
            rule: 'positive_integer' 
        });

        $(".check ").on('click',  function() {
            $(this).addClass('active').siblings().removeClass('active').find('.icon').addClass('hide');
            $(this).find('.icon').removeClass('hide');
            $("input[name='payment']").val($(this).attr("id"));
        });

    };

    
});