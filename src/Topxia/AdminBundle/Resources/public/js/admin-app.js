define(function(require, exports, module) {
	window.$ = window.jQuery = require('jquery');

	require('placeholder');

	require('bootstrap');
	require('common/bootstrap-modal-hack2');

	var Notify = require('common/bootstrap-notify');

	$('[data-toggle="tooltip"]').tooltip();
	exports.load = function(name) {
		if (window.app.jsPaths[name.split('/', 1)[0]] == undefined) {
			name = window.app.basePath + '/bundles/topxiaadmin/js/controller/' + name;
		}

		seajs.use(name, function(module) {
			if ($.isFunction(module.run)) {
				module.run();
			}
		});

	};

	$('.shortcuts').on('click', '.shortcut-add', function() {
		Notify.success('已添加当前页面为常用链接！');

		var title = $(document).attr("title");

		title = title.split('|');

		var params = {
			title: title[0],
			url: window.location.pathname + window.location.search
		};
		$.post($(this).data('url'), params, function() {
			window.location.reload();
		});
	});

	$('.shortcuts').on('click', '.glyphicon-remove-circle', function() {
		Notify.success('删除常用链接成功！');
		$.post($(this).data('url'), function() {
			window.location.reload();
		});
	});

	window.app.load = exports.load;

	if (app.controller) {
		exports.load(app.controller);
	}

	$(document).ajaxSend(function(a, b, c) {
		if (c.type == 'POST') {
			b.setRequestHeader('X-CSRF-Token', $('meta[name=csrf-token]').attr('content'));
		}
	});

    if (app.scheduleCrontab) {
        $.post(app.scheduleCrontab);
    }	

});