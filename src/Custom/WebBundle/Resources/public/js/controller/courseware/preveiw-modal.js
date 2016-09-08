define(function(require, exports, module) {
    var Notify = require('common/bootstrap-notify');

    exports.run = function() {

        $('#save-btn', window.parent.document).on('click',function(){
            $(this).hide();
            var self = $(this);
            var answer = [];
            $('[data-type]:checked',window.parent.document).each(function(index,item) {
                answer.push($(item).val());
            });

            $.post(self.data('url'),{answer:answer},function(response){
                if(response.answer){
                    $('#answer-show',window.parent.document).removeClass('hide red').addClass('green');
                    self.addClass('disabled');
                } else {
                    $('#answer-show', window.parent.document).removeClass('hide');
                }
            });
        });

        var player = smvp.players.get();
        $("#cancel-btn",window.parent.document).on("click",function(){

            player.play();
            
        })

        $(".close",window.parent.document).on("click",function(){

            player.play();
            
        })

    };

});