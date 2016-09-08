define(function(require, exports, module) {

    var Notify = require('common/bootstrap-notify');

    exports.run = function() {
        require('/bundles/customadmin/js/controller/organization/organization-check-modal').run();
        require('/bundles/topxiaweb/js/controller/course-manage/header').run();

        function onCheck(e, treeId, treeNode) {
            alert("123");
            $("#citySel").val(treeNode.name);
            $("#organization_id").val(treeNode.id);
        }

        var $list = $("#course-student-list");

        $list.on('click', '.student-remove', function(){
            var $tr = $(this).parents('tr');
            if (!confirm('您真的要移除该学员吗？')) {
                return ;
            }

            $.post($(this).data('url'), function(){
                Notify.success('移除学员成功！');
                $tr.remove();
            }).error(function(){
                Notify.danger('移除学员失败，请重试！');
            });
        });



        $("#course-student-list").on('click', '.follow-student-btn, .unfollow-student-btn', function() {
            
            var $this = $(this);

            $.post($this.data('url'), function(){
                $this.hide();
                if ($this.hasClass('follow-student-btn')) {
                    $this.parent().find('.unfollow-student-btn').show();
                } else {
                    $this.parent().find('.follow-student-btn').show();
                }
            });
            
        });


    }

});