define(function(require, exports, module) {
	var Notify = require('common/bootstrap-notify');
	var Ztree = require('../../ztree/3.5.14/ztree');
	require('../../ztree/3.5.14/zTreeStyle.css');

	exports.run = function() {
		var $tree = $('#treeDemo');
		var setting = {
			check: {
				enable: true,
				chkStyle: "radio",
				radioType: "all"
			},
			view: {
				dblClickExpand: false,
				showLine: false,
				showIcon: false
			},
			data: {
				simpleData: {
					enable: true
				}
			},
			callback: {
				onClick: onClick,
				onCheck: onCheck
			},
			async: {
				enable: true,
				url:$tree.data('url'),
				autoParam:["id"]
				//dataFilter: filter
			}
		};



		function onClick(e, treeId, treeNode) {
			var zTree = $.fn.zTree.getZTreeObj("treeDemo");
			zTree.checkNode(treeNode, !treeNode.checked, null, true);
			return false;
		}

		function onCheck(e, treeId, treeNode) {
			// var zTree = $.fn.zTree.getZTreeObj("treeDemo"),
			// nodes = zTree.getCheckedNodes(true),
			// v = "";
			// for (var i=0, l=nodes.length; i<l; i++) {
			// 	v += nodes[i].name + ",";
			// }
			// if (v.length > 0 ) v = v.substring(0, v.length-1);
			// var cityObj = $("#citySel");
			// cityObj.attr("value", treeNode.name);
			$("#citySel").val(treeNode.name);
			$("#organization_id").val(treeNode.id);
		}

		function showMenu() {
			// var cityObj = $("#citySel");
			// var cityOffset = $("#citySel").offset();
			// $("#menuContent").css({left:cityOffset.left + "px", top:cityOffset.top + cityObj.outerHeight() + "px"}).slideDown("fast");
			$("#menuContent").slideDown("fast");
			$("body").bind("mousedown", onBodyDown);
		}
		function hideMenu() {
			var zTree = $.fn.zTree.getZTreeObj("treeDemo");
			nodes = zTree.getCheckedNodes(true);
			if( nodes.length ){
				
            }else{
            	$("#citySel").val("");
				$("#organization_id").val("");
            	Notify.danger("没有选择组织");
				
            }

			$("#menuContent").fadeOut("fast");
			$("body").unbind("mousedown", onBodyDown);
		}
		function onBodyDown(event) {
			if (!(event.target.id == "menuBtn" || event.target.id == "citySel" || event.target.id == "menuContent" || $(event.target).parents("#menuContent").length>0)) {
				hideMenu();
			}
		}
		$("#citySel").on('click',function(){
			showMenu();
			// console.log(1);
		});
		$.fn.zTree.init($("#treeDemo"), setting);
	}
});