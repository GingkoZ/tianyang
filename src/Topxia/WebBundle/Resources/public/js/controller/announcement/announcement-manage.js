define(function(require, exports, module) {

	exports.run = function() {
		$('a[data-role="announcement-modal"]').click(function(){
			$("#modal").html("");
			$("#modal").load($(this).data('url'));
		})
	}
});