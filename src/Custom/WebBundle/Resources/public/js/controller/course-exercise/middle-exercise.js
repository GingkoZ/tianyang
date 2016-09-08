define(function(require, exports, module) {
	exports.run = function() {

		$(".deleteExercise",window.parent.document).on('click',function(){
			var t=confirm("确定要删除吗?");
            if(!t){return;}

            $ele = $(event.currentTarget);
            $ele.data('url') && $.post($ele.data('url'), function(){
                $ele.parents('tr').remove();
            });
		});
	
	}
});
