define(function(require, exports, module) {

    exports.run = function() {

        $("input[type=file]").change(function(){$(this).parents(".uploader").find(".filename").val($(this).val());});
        $("input[type=file]").each(function(){
            if($(this).val()==""){$(this).parents(".uploader").find(".filename").val("");}
        });
        $('#start-import-btn').on("click",function(){
            $('#start-import-btn').button('submiting').addClass('disabled');
        });
         $("#publishSure").on("click",function(){

            $('#publishSure').button('submiting').addClass('disabled');

            $.post($("#publishSure").data("url"), function(html) {

                    $("#modal").modal('hide');
                    window.location.reload();

                }).error(function(){
            });
        });
    };

});