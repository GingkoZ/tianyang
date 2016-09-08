define(function(require, exports, module) {

	var Validator = require('bootstrap.validator');
    var Notify = require('common/bootstrap-notify');
	require('common/validator-rules').inject(Validator);
    require('webuploader');
    
	exports.run = function() {
        var $form = $('#knowledge-form');
		var $modal = $form.parents('.modal');
        var $list = $('.knowledge-list');

		var validator = new Validator({
            element: $form,
            autoSubmit: false,
            onFormValidated: function(error, results, $form) {
                if (error) {
                    return ;
                }

                $('#knowledge-create-btn').button('submiting').addClass('disabled');

                $.post($form.attr('action'), $form.serialize(), function(result){
                    $modal.modal('hide');

                    var zTree = $.fn.zTree.getZTreeObj("knowledge-tree");
                    var node = result.tid ? zTree.getNodeByTId(result.tid) : null;

                    if(result.organization.parentId == 0){
                        window.location.reload();
                        return ;
                    }

                    if(result.type) {
                        node.name = result.organization.name;
                        node.id = result.organization.id;
                        zTree.updateNode(node);
                    } else {
                        node = zTree.addNodes(node,  {id:(result.organization.id), pId:result.organization.parentId, name:result.organization.name});
                    }
            
                    Notify.success('保存组织机构成功！');
				}).fail(function() {
                    Notify.danger("保存组织机构失败，请重试！");
                });

            }
        });

        validator.addItem({
            element: '#knowledge-name-field',
            required: true,
            rule: 'maxlength{max:100}'
        });

        // validator.addItem({
        //     element: '#knowledge-code-field',
        //     required: true,
        //     rule: 'alphanumeric not_all_digital remote'
        // });

        validator.addItem({
            element: '#knowledge-weight-field',
            required: false,
            rule: 'integer'
        });

	};

});