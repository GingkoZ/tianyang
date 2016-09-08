define(function(require, exports, module) {
	var Notify = require('common/bootstrap-notify');
	var Ztree = require('../../ztree/3.5.14/ztree');
	
	// require('../../ztree/3.5.14/zTreeStyle.css');
	require('../../ztree/3.5.14/ztree.css');
	

	exports.run = function() {
		var $tree = $('#knowledge-tree');
		var setting = {
			async: {
				enable: true,
				url:$tree.data('url'),
				autoParam:["id"]
				//dataFilter: filter
			},
			view: {
				expandSpeed:"",
				// addHoverDom: addHoverDom,
				// removeHoverDom: removeHoverDom,
				selectedMulti: false,
				showLine: false,
				showIcon: false,
				addDiyDom: addDiyDom
			}
		};

		// function onAsyncSuccess(event, treeId, treeNode, msg) {
		// 	if(typeof(treeNode) == "undefined" ) {
		// 		var zTree = $.fn.zTree.getZTreeObj(treeId);
		// 		var nodes = zTree.getNodes();
		// 		for(var i=0; i< nodes.length;i++) {
		// 			zTree.expandNode(nodes[i],true, false, false);
		// 		}
		// 	}
		// }
		
		function addDiyDom(treeId, treeNode) {
			
		    var html = '<div class="actions ">';
		    html += '';
		    html += '<button class="btn btn-link btn-sm" id="addBtn_'+treeNode.tId+'" title="添加"><span class="glyphicon glyphicon-plus"></span></button>';
		    html += '<button class="btn btn-link btn-sm" id="editBtn_'+treeNode.tId+'" title="编辑"><span class="glyphicon glyphicon-edit"></span></button>';
		   	html += '<button class="btn btn-link btn-sm" id="removeBtn_'+treeNode.tId+'" title="删除"><span class="glyphicon glyphicon-remove"></span></button>';
		   
		   /* html += '<button class="btn btn-link btn-sm" id="removeBtn_'+treeNode.tId+'"><span class="glyphicon glyphicon-remove-circle"> 删除</span></button>';*/
		    html += '</div>';
            html +='<div class="tree-node" ><a href="javascript:;" class="selfOrguserList" id="self_level_user_'+treeNode.tId+'">'+treeNode.self_level_users+'</a></div>';
			html +='<div class="tree-node" ><a href="javascript:;" class="allUserList" id="all_user_'+treeNode.tId+'"> '+treeNode.all_users+'</a></div>';
			html +='<div class="tree-node" >'+treeNode.all_childs+'</div>';
		   

		  	$('#' + treeNode.tId + '_a').after(html);
		  	var addBtn = $("#addBtn_"+treeNode.tId),
		  		editBtn = $("#editBtn_"+treeNode.tId),
		  		removeBtn = $("#removeBtn_"+treeNode.tId),
		  		selfOrguserList = $("#self_level_user_"+treeNode.tId),
		  		allUserList = $("#all_user_"+treeNode.tId);
		  		

		  	if(addBtn) {
		  		addBtn.bind("click", function(){
		  			var zTree = $.fn.zTree.getZTreeObj("knowledge-tree");
		  			var seq = treeNode.children ? treeNode.children.length+1:1;
		  			var newUrl = $('#add-knowledge').data('turl')+'?pid='+treeNode.id+'&tid='+treeNode.tId+'&seq='+seq;
		  			$('#add-knowledge').data('url', newUrl);
		  			$('#add-knowledge').click();
		  		});
		  	}

		  	if(editBtn) {
		  		editBtn.bind("click", function(){
		  			var pid = treeNode.parentId,
		  				id = treeNode.id,
		  				newUrl = $('#edit-knowledge').data('turl')+'?id='+id+'&pid='+pid+'&tid='+treeNode.tId;

		  			$('#edit-knowledge').data('url', newUrl);
		  			$('#edit-knowledge').click();
		  		});
		  	}


		  	if(removeBtn) {
		  		removeBtn.bind("click", function(){
					var zTree = $.fn.zTree.getZTreeObj("knowledge-tree");
					zTree.selectNode(treeNode);
					if(treeNode.isParent){
						Notify.danger("当前节点不是底层节点，不能删除！");
						return ;
					}
					if(confirm("确认删除当前组织机构 ： " + treeNode.name + " 吗？")) {
						$.ajax({ 
							type: 'POST', 
							url: $tree.data('durl'), 
							data: {id:treeNode.id}, 
							success: function(result){
		                    	Notify.success('删除组织机构成功！');
		                    	zTree.removeNode(treeNode);
							}, 
							error:function() {
		                    	Notify.danger("删除组织机构失败！");
		                	},
							async:false 
						});
					} else {
						return false;
					}
		  		});
	  		}

  			if(allUserList){
		  		allUserList.bind("click",function(){
		  			if(treeNode.all_users == 0){
		  				return ;
		  			}
		  			var orgId = treeNode.id,
		  			newUrl = $('#userList').data('turl')+'?orgId='+orgId+'&leave=all';

		  			$('#userList').data('url', newUrl);
		  			$('#userList').click();
		  		});
		  	}


	  		if(selfOrguserList){
		  		selfOrguserList.bind("click",function(){
		  			if(treeNode.self_level_users == 0){
		  				return ;
		  			}
		  			var orgId = treeNode.id,
		  			newUrl = $('#userList').data('turl')+'?orgId='+orgId+'&leave=self';

		  			$('#userList').data('url', newUrl);
		  			$('#userList').click();
		  			
		  		});
		  	}


	  		
		};

	

		// function onDrop(event, treeId, treeNodes, targetNode, moveType, isCopy) {
		// 	alert("onDrop");
		// 	var pid = treeNodes[0].pId,
		// 		id = treeNodes[0].id,
		// 		zTree = $.fn.zTree.getZTreeObj(treeId),
		// 		parentNode = treeNodes[0].getParentNode(),
		// 		childNodes = parentNode ? parentNode.children :  zTree.getNodesByParam('pId', null);
		// 	var seq = [];
		// 	for (var i = 0; i <= childNodes.length - 1; i++) {
		// 		seq[i] =  childNodes[i].id;
		// 	};

		// 	$.ajax({ 
		// 			type: 'POST', 
		// 			url: $tree.data('surl'), 
		// 			data: {id:id, pid:pid, seq:seq}, 
		// 			success: function(result){
		// 				treeNodes[0].parentId = pid;
		// 			}, 
		// 			error:function() {
  //               	},
		// 			async:true 
		// 		});
			
		// }

		// function addHoverDom(treeId, treeNode) {
		// 	// alert("addHoverDom");
		// 	var sObj = $("#" + treeNode.tId + "_span");
		// 	if (treeNode.editNameFlag || $("#addBtn_"+treeNode.tId).length>0) return;
		// 	var addStr = "<span class='button add' id='addBtn_" + treeNode.tId
		// 		+ "' title='增加知识点' onfocus='this.blur();'></span>";
		// 	sObj.after(addStr);
		// 	var btn = $("#addBtn_"+treeNode.tId);
		// 	if (btn) btn.bind("click", function(){
		// 		var zTree = $.fn.zTree.getZTreeObj("knowledge-tree");
		// 		var seq = treeNode.children ? treeNode.children.length+1:1;
		// 		var newUrl = $('#add-knowledge').data('turl')+'?pid='+treeNode.id+'&tid='+treeNode.tId+'&seq='+seq;
		// 		$('#add-knowledge').data('url', newUrl);
		// 		$('#add-knowledge').click();
		// 		return false;
		// 	});
		// };
		// function removeHoverDom(treeId, treeNode) {
		// 	// $("#addBtn_"+treeNode.tId).unbind().remove();
		// 	$('.actions').html('');
		// };

		$.fn.zTree.init($("#knowledge-tree"), setting);


	}
});