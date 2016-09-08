define(function(require, exports, module) {

    var ProgressBar = require('../course/ProgressBar');

	exports.run = function() {

        var progressBar = new ProgressBar({
            element: '#optimize-files-progress'
        });

        progressBar.show();
        $(document).queue('optimize-files_queue', function() {
                exec('检查优化文件',$('#optimize-url').data('url'), progressBar, 20, 100);
        });
        $(document).dequeue('optimize-files_queue');

        progressBar.on('completed', function() {
            progressBar.deactive();
            progressBar.text('优化文件成功');
            $("#optimize-hint").hide();
            $("#finish-optimize-btn").show();
        });
	}

    function exec(title, url, progressBar, startProgress, endProgress) {

            progressBar.setProgress(startProgress, '正在' + title);
            $.ajax(url, {
                async: true,
                dataType: 'json',
                type: 'POST'
            }).done(function(data, textStatus, jqXHR) {
                if (data.status == 'error') {
                    progressBar.error(makeErrorsText(title + '失败：', data.errors));
                }else if(data.success){
                    progressBar.setProgress(startProgress, data.message);
                    title =  data.message;
                    exec(title, url, progressBar, startProgress, endProgress);
                }else{
                    progressBar.setProgress(endProgress, title + '完成');
                    $(document).dequeue('optimize-files_queue');
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                progressBar.error( title +  '时，发生了未知错误。');
                $(document).clearQueue('optimize-files_queue');
            });
    }

    function makeErrorsText(title, errors) {
        var html = '<p>' + title + '<p>';
        html += '<ul>';
        $.each(errors, function(index, text) {
            html += '<li>' + text + '</li>';
        });
        html += '</ul>';
        return html;
    }
});
