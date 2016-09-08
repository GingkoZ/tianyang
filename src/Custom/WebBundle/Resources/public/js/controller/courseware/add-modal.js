define(function(require, exports, module) {
    var Notify = require('common/bootstrap-notify');
    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    
    exports.run = function() {

        $("#block-btn",window.parent.document).on("click",function(){
            
            var selectNum = $(":checked",window.parent.document).length; 

            if(selectNum < 1){
                alert("未选中题干");
                return false;
            }
        });



        $(".checknum",window.parent.document).on("click",function(){

            var num = parseInt($("input[name='mediaLessonExerciseCount']",window.parent.document).val());
            if(num>4){
                   alert('选择题目已超过5道');
                   Notify.danger('选择题目已超过5道');
                   $("#block-btn",window.parent.document).attr("disabled","true"); 

                }else{
                   
                    $("#block-btn",window.parent.document).removeAttr("disabled");
                    num=parseInt(num)+parseInt($(":checked",window.parent.document).length);
                   
                   if(num<=5){

                        $("big",window.parent.document).text(num);

                   }else{
                        
                        alert("选择题目已超过5道");
                        Notify.danger('选择题目已超过5道');
                        $("#block-btn",window.parent.document).attr("disabled","true");
                   }
            }
      
        })

        var player = smvp.players.get();

        $(".closeExercise",window.parent.document).on("click",function(){

          player.play();

        })

        $(".close",window.parent.document).on("click",function(){

            player.play();
            
        })


        $('.search-btn',window.parent.document).on('click', function() {
            $(this).button('search').addClass('disabled');
            $.get($(this).data('url'), $('#search-form',window.parent.document).serialize(), function(html){
                $('.modal-content',window.parent.document).html($(html).find('.modal-content').html());
                $('.search-btn',window.parent.document).removeClass('disabled').button('reset');
           });

        });

        
        $('#search-form',window.parent.document).on("keyup keypress", function(e) {
          var code = e.keyCode || e.which; 
          if (code  == 13) {               
            $('.search-btn',window.parent.document).click();
            e.preventDefault();
            return false;
          }
        });
    };

});