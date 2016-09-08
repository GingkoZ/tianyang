seajs.config({
	alias: {
        'jquery': 'jquery/1.11.2/jquery',
        '$': 'jquery/1.11.2/jquery',
        '$-debug': 'jquery/1.11.2/jquery',
        "jquery.form": "jquery-plugin/form/3.44.0/form",
        "jquery.sortable": "jquery-plugin/sortable/0.9.10/sortable.js",
        "jquery.raty": "jquery-plugin/raty/2.5.2/raty",
        "jquery.cycle2": "jquery.cycle2/2.1.6/index",
        "jquery.perfect-scrollbar": "jquery-plugin/perfect-scrollbar/0.4.8/perfect-scrollbar",
        "jquery.select2": "jquery-plugin/select2/3.4.1/select2",
        "jquery.select2-css": "jquery-plugin/select2/3.4.1/select2.css",
        "jquery.jcrop": "jquery-plugin/jcrop/0.9.12/jcrop",
        "jquery.jcrop-css": "jquery-plugin/jcrop/0.9.12/jcrop.css",
        "jquery.nouislider": "jquery-plugin/nouislider/5.0.0/nouislider",
        "jquery.nouislider-css": "jquery-plugin/nouislider/5.0.0/nouislider.css",
        'jquery.bootstrap-datetimepicker': "jquery-plugin/bootstrap-datetimepicker/1.0.0/datetimepicker",
        "jquery.countdown": "jquery.countdown/2.0.4/index",
        "jquery.colorbox": "jquery.colorbox/1.6.0/index",
        "plupload": "jquery-plugin/plupload-queue/2.0.0/plupload",
        "jquery.plupload-queue-css": "jquery-plugin/plupload-queue/2.0.0/css/queue.css",
        "jquery.plupload-queue": "jquery-plugin/plupload-queue/2.0.0/queue",
        "jquery.plupload-queue-zh-cn": "jquery-plugin/plupload-queue/2.0.0/i18n/zh-cn",
        "jquery.waypoints": "jquery-plugin/waypoints/2.0.5/waypoints.min",
        "jquery.easy-pie-chart": "jquery-plugin/jquery.easy-pie-chart/jquery.easypiechart.min",
        "mediaelementplayer": "gallery2/mediaelement/2.14.2/mediaelement-and-player",
        'bootstrap': 'gallery2/bootstrap/3.1.1/bootstrap',
        'echo.js': 'echo.js/1.7.0/index',
        'swiper': 'swiper/2.7.6/index',
        'autocomplete': 'arale/autocomplete/1.2.2/autocomplete',
        'upload': 'arale/upload/1.1.0/upload',
        'bootstrap.validator': 'common/validator',
        'class': 'arale/class/1.1.0/class',
        'base': 'arale/base/1.1.1/base',
        'widget': 'arale/widget/1.1.1/widget',
        'position': 'arale/position/1.0.1/position',
        'overlay': 'arale/overlay/1.1.4/overlay',
        'mask': 'arale/overlay/1.1.4/mask',
        'sticky': 'arale/sticky/1.3.1/sticky',
        'cookie': 'arale/cookie/1.0.2/cookie',
        'messenger': 'arale/messenger/2.0.0/messenger',
        "templatable": "arale/templatable/0.9.1/templatable",
        'placeholder': 'arale/placeholder/1.1.0/placeholder',
        'json': 'gallery/json/1.0.3/json',
        "handlebars": "gallery/handlebars/1.0.2/handlebars",
        "backbone": "gallery/backbone/1.0.0/backbone",
        "swfobject": "gallery/swfobject/2.2.0/swfobject.js",
        'moment': 'gallery/moment/2.5.1/moment',
        'morris': 'gallery/morris/0.5.0/morris',
        'store': 'store/1.3.16/store',
        'video-js': 'gallery2/video-js/4.2.1/video-js',
        'swfupload': 'gallery2/swfupload/2.2.0/swfupload',
        'webuploader': 'gallery2/webuploader/0.1.2/webuploader',
        'screenfull': 'screenfull/2.0.0/screenfull',
        'ckeditor': 'ckeditor/4.6.7/ckeditor',
        'edusoho.linkselect': 'edusoho/linkselect/1.0/linkselect-debug.js',
        'edusoho.chunkupload': 'edusoho/chunkupload/1.0.1/chunk-upload.js',
        'edusoho.uploadpanel': 'edusoho/uploadpanel/1.0/upload-panel.js',
        'edusoho.uploadProgressBar': 'edusoho/uploadprogressbar/1.0/upload-progress-bar.js',
        'edusoho.webuploader': 'edusoho/webuploader/1.0.2/web-uploader.js',
        'edusoho.imagecrop': 'edusoho/imagecrop/1.0.0/image-crop.js',
        'edusoho.autocomplete': 'edusoho/autocomplete/1.0.0/autocomplete.js',
        'colorpicker': 'jquery-plugin/colorpicker/js/bootstrap-colorpicker',
        'fullcalendar': 'fullcalendar/lang-all.js',
        'momentmin':'fullcalendar/lib/moment.min.js',
        'video-player': 'balloon-video-player/1.0.0/index'
    },

	// 预加载项
	preload: [this.JSON ? '' : 'json'],

	// 路径配置
	paths: app.jsPaths,

	// 变量配置
	vars: {
		'locale': 'zh-cn'
	},

	charset: 'utf-8',

	debug: app.debug
});

var __SEAJS_FILE_VERSION = '?v' + app.version + '.20160304';

seajs.on('fetch', function(data) {
	if (!data.uri) {
		return ;
	}

	if (data.uri.indexOf(app.mainScript) > 0) {
		return ;
	}

    if (/\:\/\/.*?\/assets\/libs\/[^(common)]/.test(data.uri)) {
        return ;
    }

    data.requestUri = data.uri + __SEAJS_FILE_VERSION;

});

seajs.on('define', function(data) {
	if (data.uri.lastIndexOf(__SEAJS_FILE_VERSION) > 0) {
	    data.uri = data.uri.replace(__SEAJS_FILE_VERSION, '');
	}
});
