define(function(require, exports, module) {

    exports.run = function() {
    	require('../organization/organization-check-modal').run();
        $("input[type=file]").change(function(){$(this).parents(".uploader").find(".filename").val($(this).val());});
        $("input[type=file]").each(function(){
            if($(this).val()==""){$(this).parents(".uploader").find(".filename").val("");}
        });
        $('#start-import-btn').on("click",function(){
            $('#start-import-btn').button('submiting').addClass('disabled');
        });

    };

});