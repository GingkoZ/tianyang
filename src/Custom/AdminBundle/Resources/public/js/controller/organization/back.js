define(function(require, exports, module) {
	var Notify = require('common/bootstrap-notify');
	var Ztree = require('../../ztree/3.5.14/ztree');
	require('../../ztree/3.5.14/zTreeStyle.css');

	exports.run = function() {
		var $tree = $('#organization-tree');
		// var $modal = $form.parents('.modal');
		// var setting = {
		// 	view: {
		// 		dblClickExpand: false,
		// 		showLine: true,
		// 		selectedMulti: false
		//    },
		// 	async: {
		// 		enable: true,
		// 		url:$tree.data('url'),
		// 		autoParam:["id"]
		// 		//dataFilter: filter
		// 	},
		// 	check: {
		// 		enable: true,
		// 		chkStyle: "radio",
		// 		radioType: "all"
		// 	}
		// };
		var setting = {
			view: {
				selectedMulti: false,
				showLine: false,
				showIcon: false,
				dblClickExpand: false
			},
			check: {
				enable: true,
				chkStyle: "radio",
				radioType: "all"
			},
			data: {
				simpleData: {
					enable: true
				}
			},
			callback: {
				onClick: onClick,
				beforeCheck: beforeCheck,
				onCheck: onCheck
			},
			async: {
				enable: true,
				url:$tree.data('url'),
				autoParam:["id"]
				//dataFilter: filter
			}
		};

		function beforeCheck(treeId, treeNode) {
			// alert("beforeCheck");
		}

		function onCheck(e, treeId, treeNode) {
			$("#organization_name").val(treeNode.name);
			$("#organization_id").val(treeNode.id);
		}
		$("#organization-node-checked").on('click',function(){
			var treeObj=$.fn.zTree.getZTreeObj("organization-tree");
            nodes=treeObj.getCheckedNodes(true);
            if( nodes.length ){
				$("#modal").modal('hide');
            }else{
            	Notify.danger("请选择一个组织");
				return;
            }
            
			
		});

		// $table.on('click', '.publish-course', function(){
  //  			if (!confirm('您确认要发布此课程吗？')) return false;
  //   			$.post($(this).data('url'), function(html){
  //   				var $tr = $(html);
  //    				$table.find('#' + $tr.attr('id')).replaceWith(html);
   
  //    		});


		$.fn.zTree.init($("#organization-tree"), setting);


	}
});