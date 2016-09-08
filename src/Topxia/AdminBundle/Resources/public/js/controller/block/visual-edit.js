define(function(require, exports, module) {
    var Notify = require('common/bootstrap-notify');
    var Widget = require('widget');
    var EduWebUploader  = require('edusoho.webuploader');
    require('webuploader');
    require('jquery.sortable');
    require('colorpicker');
    exports.run = function() {


        if( $(".poster-btn").length>0 ){
            var selector = $(".poster-btn");
            initFirstTab(selector);
            bindSortPoster();
            $('.colorpicker-input').colorpicker();
        }

        $('#btn-tabs .btn').click(function(){
            $(this).removeClass('btn-default').addClass('btn-primary')
                            .siblings('.btn-primary').removeClass('btn-primary').addClass('btn-default');
        })

        var editForm = Widget.extend({
            uploaders: [],
            events: {
                'click .js-add-btn': 'onClickAddBtn',
                'click .js-remove-btn': 'onClickRemoveBtn',
                'click a.js-title-label': 'onClickTitleLabel',
                'click .js-img-preview': 'onClickPicPreview',
                'change .js-label-input': 'onChangeLabel'
            },

            setup: function() {
                //this._bindImgPreview(this.element);
                this._bindUploader(this.element);
                this._initForm();
                this._bindCollapseEvent(this.element);
                this._bindSortable(this.element);
            },
            _initForm: function() {
                $form = this.element;

                $form.data('serialize', $form.serialize());
                $(window).on('beforeunload',function(){
                    if ($form.serialize() != $form.data('serialize')) {
                        return "还有没有保存的数据,是否要离开此页面?";
                    }
                });

                this.$('#block-save-btn').on('click', function(){
                    $form.data('serialize', $form.serialize());
                });
            },
            onClickAddBtn: function(e) {
                var $target = $(e.currentTarget);
                var $panelGroup = $target.prev('.panel-group');
                var $panels = $panelGroup.children('.panel.panel-default');
                if ($panels.length >= $panelGroup.data('count')) {
                    Notify.danger('最多只能添加' + $panelGroup.data('count') + '个!');
                } else {
                    $model = $($panels[0]).clone();
                    $model.find('input').attr('value', '').val('');
                    $model.find('textarea').attr('html', '');
                    $model.find('.title-label').html('');
                    $model.find('.js-img-preview').attr('href', '');
                    var headingId = new Date().getTime() + '-heading';
                    $model.find('.panel-heading').attr('id', headingId);
                    var collapseId = new Date().getTime() + '-collapse';
                    $model.find('.panel-collapse').attr('aria-labelledby', headingId).attr('id', collapseId);
                    $model.find('[data-toggle=collapse]').attr('aria-expanded', false).attr('href', "#"+collapseId).attr('aria-controls', collapseId);
                    $model.find('input[data-role=radio-yes]').attr('checked', false);
                    $model.find('input[data-role=radio-no]').attr('checked', true);
                    var code = $panelGroup.data('code');
                    var uploadId = new Date().getTime();
                    $model.find('.webuploader-container').attr('id',  'item-' + code + 'uploadId-' + (uploadId));
                    $panelGroup.append($model);
                    this.refreshIndex($panelGroup);
                }


            },
            onClickRemoveBtn: function(e) {
                if (confirm("你确定要删除吗?")) {
                    var $target = $(e.currentTarget);
                    var $panelGroup = $target.closest('.panel-group');
                    var $parent = $target.closest('.panel.panel-default');
                    var $panels = $panelGroup.children('.panel.panel-default');
                    if ($panels.length == 1) {
                        Notify.danger("必须要有一个!");
                    } else {
                        $parent.remove();
                        this.refreshIndex($panelGroup);
                    }
                }
                e.stopPropagation();
            },
            onClickTitleLabel: function(e) {
                var $target = $(e.currentTarget);
                if (!$target.data('noLink')) {
                    e.stopPropagation();
                }
            },
            onClickPicPreview: function(e) {
                var $target = $(e.currentTarget);
                e.stopPropagation();
            },
            onChangeLabel: function(e) {
                var $target = $(e.currentTarget);
                $target.closest('.panel.panel-default').find('.js-title-label').html($target.val());
            },
            refreshIndex: function($panelGroup) {
                this._destoryUploader(this.element);
                $prefixCode = $panelGroup.data('prefix');
                $panels = $panelGroup.children('.panel.panel-default');
                $panels.each(function(index, object){
                    $(this).find('input[type=text]').each(function(element){
                        $(this).attr('value', $(this).val());
                    });
                    $(this).find('input[type=radio]').each(function(element){
                        if ($(this).prop('checked')) {
                            $(this).attr('checked', 'checked');
                        }
                    });

                    $(this).find('.webuploader-container').html('上传');
                    var replace = $(this)[0].outerHTML.replace(/\bdata\[.*?\]\[.*?\]/g, $prefixCode + "[" + index + "]");
                    $(this).replaceWith(replace);
                });

                this._bindUploader($panelGroup);
                this._bindCollapseEvent($panelGroup);
            },
            _bindImgPreview: function($element) {
                $element.find('.js-img-preview').colorbox({rel:'group1', photo:true, current:'{current} / {total}', title:function() {
                    return $(this).data('title');
                }});
            },
            _bindUploader: function($element) {
                var thiz = this;
                $element.find('.img-upload').each(function(){
                   var self = $(this);
                   var uploader = WebUploader.create({
                       swf: require.resolve("webuploader").match(/[^?#]*\//)[0] + "Uploader.swf",
                       server: $(this).data('url'),
                       pick: '#'+$(this).attr('id'),
                       formData: {'_csrf_token': $('meta[name=csrf-token]').attr('content') },
                       accept: {
                           title: 'Images',
                           extensions: 'gif,jpg,jpeg,png',
                           mimeTypes: 'image/*'
                       }
                    });

                    uploader.on( 'fileQueued', function( file ) {
                        Notify.info('正在上传，请稍等！', 0);
                        uploader.upload();
                    });

                    uploader.on( 'uploadSuccess', function( file, response ) {
                        self.closest('.form-group').find('input[data-role=img-url]').val(response.url);
                        Notify.success('上传成功！', 1);
                   });

                    uploader.on( 'uploadError', function( file, response ) {
                        Notify.danger('上传失败，请重试！');
                    });

                    var id =$(this).attr('id');
                    thiz.uploaders[id] = uploader;
               });

            },
            _bindCollapseEvent: function($element) {
                $element.find('[data-role=collapse]').each(function(){
                    $(this).on('shown.bs.collapse', function(e){
                        $(e.target).siblings('.panel-heading').find('.js-expand-icon').removeClass('glyphicon-chevron-down').addClass('glyphicon-chevron-up');
                        $(e.target).find('.webuploader-container div:eq(1)').css({width:46, height:30});
                    });
                    $(this).on('hidden.bs.collapse', function(e){
                        $(e.target).siblings('.panel-heading').find('.js-expand-icon').removeClass('glyphicon-chevron-up').addClass('glyphicon-chevron-down');
                        $(e.target).find('.webuploader-container div:eq(1)').css({width:1, height:1});
                    });
                });

            },
            _bindSortable: function($element)
            {
                var self = this;
                $element.find('.panel-group').each(function(){
                    var $group = $(this);
                    $(this).sortable({
                        itemSelector: '.panel.panel-default',
                        handle: '.js-move-seq',
                        serialize: function(parent, children, isContainer) {
                            return isContainer ? children : parent.attr('id');
                        },
                        onDrop: function ($item, container, _super, event) {
                            $item.removeClass("dragged").removeAttr("style");
                            $("body").removeClass("dragging");
                            self.refreshIndex($group);
                        }
                    });
                })
            },
            _destoryUploader: function($element) {

            }
        });
        new editForm({
            'element': '#block-edit-form'
        });
    };

    function initFirstTab(selector){
        var href =selector.attr('href');
        var id = href.substr(1,href.length-1);
        var imgSelf = $("#"+id).find(".img-mode-upload");
        var htmlSelf = $("#"+id).find(".html-mode-upload");

        bindImgUpLoader(imgSelf);
        bindHtmlUpLoader(htmlSelf);
    }

    $("#block-edit-form").on('click', '.imgMode', function(){
        $(this).parent().parent().siblings(".edit-mode-html").css("display","none");
        $(this).parent().parent().siblings(".edit-mode-img").removeAttr("style");
        $(this).parent().siblings().find('.htmlMode').removeAttr('checked');
        $(this).parent().siblings('.mode-value').val("img")
        var self = $(this).parent().parent().siblings(".edit-mode-img").find('.img-mode-upload');
        bindImgUpLoader(self);
    });

    $("#block-edit-form").on('click', '.htmlMode', function () {
        $(this).parent().parent().siblings(".edit-mode-img").css("display","none");
        $(this).parent().parent().siblings(".edit-mode-html").removeAttr("style");
        $(this).parent().siblings().find('.imgMode').removeAttr('checked');
        $(this).parent().siblings('.mode-value').val("html");
        var self = $(this).parent().parent().siblings(".edit-mode-html").find('.html-mode-upload');
        bindHtmlUpLoader(self);
    });

    $("#block-edit-form").on('click', ".layout-input", function () {
        $(this).parent().siblings().find('.layout-input').removeAttr('checked');
        $(this).parent().siblings('.layout-value').val($(this).val());
    });

    $("#block-edit-form").on('click', '.status-input', function () {
        $(this).parent().siblings().find('.status-input').removeAttr('checked');
        $(this).parent().siblings('.status-value').val($(this).val());
    });

    $("#btn-tabs").on('click', '.poster-btn', function(){
        var href = $(this).attr('href');
        var id = href.substr(1,href.length-1);
        var imgSelf = $("#"+id).find(".img-mode-upload");
        var htmlSelf = $("#"+id).find(".html-mode-upload");
        bindImgUpLoader(imgSelf);
        bindHtmlUpLoader(htmlSelf);
    });

    function bindImgUpLoader(self){
        var uploader = new EduWebUploader({
            element : self
        });

        uploader.on('uploadSuccess', function(file, response ) {
            self.closest('.form-group').find('.img-mrl').html(response.url);
            self.closest('.form-group').find(".img-mtl").attr("src",response.url);
            self.closest('.form-group').find(".img-value").val(response.url);
            Notify.success('上传成功！', 1);
        });
    }

    function bindHtmlUpLoader(self){
        var uploader = new EduWebUploader({
            element : self
        });

        uploader.on('uploadSuccess', function(file, response ) {
            var html = self.closest('.edit-mode-html').find('.html-mrl').append("<p>" + response.url + "</p>");
            Notify.success('上传成功！', 1);
        });
    }

    function bindSortPoster(){
        var $group = $("#btn-tabs");
        $("#btn-tabs").sortable({
            itemSelector : '.poster-table',
            onDrop: function ($item, container, _super, event) {
                $item.removeClass("dragged").removeAttr("style");
                $("body").removeClass("dragging");
                $group.children('.poster-table').each(function(index, object){
                    var href = $(this).find('.poster-btn').attr('href');
                    var id = href.substr(1,href.length-1);
                    $("#" + id).children('div').each(function(){
                        $(this).find('input[type=text]').each(function(element){
                            $(this).attr('value', $(this).val());
                        });

                        $(this).find("input[type=radio]").each(function(){
                            if ($(this).prop('checked')) {
                                $(this).attr('checked', 'checked');
                            }
                        });

                        var replace = $(this)[0].outerHTML.replace(/\bdata\[.*?\]\[.*?\]/g,   "data[posters][" + index + "]");
                        $(this).replaceWith(replace);
                    });
                    $(this).find('.poster-btn').text("海报" + (index+1));
                    $(this).find('input[type=hidden]').val("海报" + (index+1));
                    var nameReplace = $(this)[0].outerHTML.replace(/\bdata\[.*?\]\[.*?\]/g,   "data[posters][" + index + "]");
                    $(this).replaceWith(nameReplace);

                });
                selectBtn = $item.find('.poster-btn');
                initFirstTab(selectBtn);
                $('.colorpicker-input').colorpicker();
            }
        });

    };
});