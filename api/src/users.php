<?php

use Symfony\Component\HttpFoundation\Request;
use Topxia\Api\Util\UserUtil;
use Topxia\Common\ArrayToolkit;
use Topxia\Service\Common\ServiceKernel;

$api = $app['controllers_factory'];

/*
## 用户模糊查询

GET /users

 ** 参数 **

| 名称  | 类型  | 必需   | 说明 |
| ---- | ----- | ----- | ---- |
| q | string | 是 | 用于匹配的字段值,分别模糊匹配手机,qq,昵称,每种匹配返回一个列表,每个列表最多五个 |

 ** 响应 **

```
{
"mobile": [
datalist
],
"qq": [
datalist
],
"nickname": [
datalist
]
}
```
 */
$api->get('/', function (Request $request) {
	$field = $request->query->get('q');
	$mobileProfiles = ServiceKernel::instance()->createService('User.UserService')->searchUserProfiles(array('mobile' => $field), array('id', 'DESC'), 0, 5);
	$qqProfiles = ServiceKernel::instance()->createService('User.UserService')->searchUserProfiles(array('qq' => $field), array('id', 'DESC'), 0, 5);

	$mobileList = ServiceKernel::instance()->createService('User.UserService')->findUsersByIds(ArrayToolkit::column($mobileProfiles, 'id'));
	$qqList = ServiceKernel::instance()->createService('User.UserService')->findUsersByIds(ArrayToolkit::column($qqProfiles, 'id'));
	$nicknameList = ServiceKernel::instance()->createService('User.UserService')->searchUsers(array('nickname' => $field), array('LENGTH(nickname)', 'ASC'), 0, 5);
	return array(
		'mobile' => filters($mobileList, 'user'),
		'qq' => filters($qqList, 'user'),
		'nickname' => filters($nicknameList, 'user'),
	);
});

/*
## 分页获取全部用户

GET /users/pages

 ** 参数 **

| 名称  | 类型  | 必需   | 说明 |
| ---- | ----- | ----- | ---- |

 ** 响应 **

```
{
'data': [
datalist
],
"total": {total}
}
```
 */
$api->get('/pages', function (Request $request) {
	$start = $request->query->get('start', 0);
	$limit = $request->query->get('limit', 10);
	$count = ServiceKernel::instance()->createService('User.UserService')->searchUserCount(array());
	$users = ServiceKernel::instance()->createService('User.UserService')->searchUsers(array(), array('createdTime', 'DESC'), $start, $limit);
	return array(
		'data' => filters($users, 'user'),
		'total' => $count,
	);
});

//根据id获取一个用户信息

$api->get('/{id}', function (Request $request, $id) {
	$user = convert($id, 'user');
	return filter($user, 'user');
});

/*
## 注册

POST /users/

 ** 参数 **

| 名称  | 类型  | 必需   | 说明 |
| ---- | ----- | ----- | ---- |
| email | string | 是 | 邮箱 |
| nickname | string | 是 | 昵称 |
| password | string | 是 | 密码 |

 ** 响应 **

```
{
"xxx": "xxx"
}
```
 */
$api->post('/', function (Request $request) {
	$fields = $request->request->all();
	$user = ServiceKernel::instance()->createService('User.UserService')->register($fields);
	return filter($user, 'user');
});

/*

## 登录

POST /users/login

 ** 参数 **

| 名称  | 类型  | 必需   | 说明 |
| ---- | ----- | ----- | ---- |
| nickname | string | 是 | 昵称 |
| password | string | 是 | 密码 |

 ** 响应 **

```
{
"xxx": "xxx"
}
```
 */
$api->post('/login', function (Request $request) {
	$fields = $request->request->all();
	$user = ServiceKernel::instance()->createService('User.UserService')->getUserByLoginField($fields['nickname']);
	if (empty($user)) {
		throw new \Exception('user not found');
	}

	if (!ServiceKernel::instance()->createService('User.UserService')->verifyPassword($user['id'], $fields['password'])) {
		throw new \Exception('password error');
	}

	$token = ServiceKernel::instance()->createService('User.UserService')->makeToken('mobile_login', $user['id']);
	setCurrentUser($user);
	return array(
		'user' => filter($user, 'user'),
		'token' => $token,
	);
});

/*

## 第三方登录

POST /users/bind_login

 ** 参数 **

| 名称  | 类型  | 必需   | 说明 |
| ---- | ----- | ----- | ---- |
| type | string | 是 | 第三方类型,值有qq,weibo,weixin,renren |
| id | string | 是 | 第三方处的用户id |
| name | string | 是 | 第三方处的用户昵称 |
| avatar | string | 是 | 第三方处的用户头像 |

 ** 响应 **

```
{
"user": "{user-data}"
"token": "{user-token}"
}
```

此处`token`为ES端记录通过接口登录的用户的唯一凭证

 */
$api->post('/bind_login', function (Request $request) {
	$type = $request->request->get('type');
	$id = $request->request->get('id');
	$name = $request->request->get('name');
	$avatar = $request->request->get('avatar', '');

	if (empty($type)) {
		throw new \Exception('type parameter error');
	}

	$userBind = ServiceKernel::instance()->createService('User.UserService')->getUserBindByTypeAndFromId($type, $id);
	if (empty($userBind)) {
		$oauthUser = array(
			'id' => $id,
			'name' => $name,
			'avatar' => $avatar,
			'createdIp' => $request->getClientIp(),
		);
		$token = array('userId' => $id);
		if (empty($oauthUser['id'])) {
			throw new \RuntimeException("获取用户信息失败，请重试。");
		}

		if (!ServiceKernel::instance()->createService('User.AuthService')->isRegisterEnabled()) {
			throw new \RuntimeException("注册功能未开启，请联系管理员！");
		}
		$userUtil = new UserUtil();
		$user = $userUtil->generateUser($type, $token, $oauthUser, $setData = array());
		if (empty($user)) {
			throw new \RuntimeException("登录失败，请重试！");
		}
		$token = ServiceKernel::instance()->createService('User.UserService')->makeToken('mobile_login', $user['id']);
		setCurrentUser($user);
		$user = $userUtil->fillUserAttr($user['id'], $oauthUser);
	} else {
		$user = ServiceKernel::instance()->createService('User.UserService')->getUser($userBind['toId']);
		$token = ServiceKernel::instance()->createService('User.UserService')->makeToken('mobile_login', $user['id']);
		setCurrentUser($user);
	}

	return array(
		'user' => filter($user, 'user'),
		'token' => $token,
	);
});

/*
## 登出

POST /users/logout

 ** 响应 **

```
{
"success": bool
}
```
 */
$api->post('/logout', function (Request $request) {
	$token = $request->request->get('token');
	$result = ServiceKernel::instance()->createService('User.UserService')->deleteToken('login', $token);
	return array(
		'success' => $result ? $result : false,
	);
});

//开通会员
/*
 ** 参数 **

| 名称  | 类型  | 必需   | 说明 |
| ---- | ----- | ----- | ---- |
| levelId | int | 是 | 会员等级id |
| boughtUnit | string | 是 | 开通时长 |
| boughtDuration | string | 是 | 付费方式 |

`boughtDuration`的值有:

 * month : 按月
 * year : 按年

 ** 响应 **

```
{
"success": bool
}
```
 */
$api->post('/{id}/vips', function (Request $request, $id) {
	$user = convert($id, 'user');
	$levelId = $request->request->get('levelId');
	$boughtDuration = $request->request->get('boughtDuration');
	$boughtUnit = $request->request->get('boughtUnit');

	$member = ServiceKernel::instance()->createService('Vip:Vip.VipService')->becomeMember(
		$user['id'],
		$levelId,
		$boughtDuration,
		$boughtUnit,
		$orderId = 0
	);

	return array(
		'success' => empty($member) ? false : true,
	);
});

/*
## （取消）关注用户
POST /users/{id}/followers

 ** 参数 **

| 名称  | 类型  | 必需   | 说明 |
| ---- | ----- | ----- | ---- |
| userId | int | 否 | 发起关注操作的用户id,未传则默认为当前用户 |
| method | string | 否 | 值为delete时为取消关注用户 |

 ** 响应 **

```
{
"success": bool
}
```
 */
$api->post('/{id}/followers', function (Request $request, $id) {
	$userId = $request->request->get('userId', '');
	$method = $request->request->get('method');
	$fromUser = empty($userId) ? getCurrentUser() : convert($userId, 'user');
	if (!empty($method) && $method == 'delete') {
		$result = ServiceKernel::instance()->createService('User.UserService')->unFollow($fromUser['id'], $id);
	} else {
		$result = ServiceKernel::instance()->createService('User.UserService')->follow($fromUser['id'], $id);
	}
	return array(
		'success' => empty($result) ? false : true,
	);
});

//获得用户的关注者
/*

 ** 响应 **

```
{
"xxx": "xxx"
}
```

 */
$api->get('/{id}/followers', function ($id) {
	$user = convert($id, 'user');
	$follwers = ServiceKernel::instance()->createService('User.UserService')->findAllUserFollower($user['id']);
	return filters($follwers, 'user');
});

//获得用户关注的人
/*

 ** 响应 **

```
{
"xxx": "xxx"
}
```

 */
$api->get('/{id}/followings', function ($id) {
	$user = convert($id, 'user');
	$follwings = ServiceKernel::instance()->createService('User.UserService')->findAllUserFollowing($user['id']);
	return filters($follwers, 'user');
});

//获得用户的好友关系
/*
## 获得用户的好友关系
GET /users/{id}/friendship

ddddd
| 名称  | 类型  | 必需   | 说明 |
| ---- | ----- | ----- | ---- |
| toIds | int | 否 | 被选方的的用户id,未传则默认为当前登录用户,多id格式为id-1,id-2,id-3|

 ** 响应 **

```
[
no-user,
none,
following,
follower,
friend,
...
]
```
返回数组，排序与传入id对应,好友关系的值有：

no-user : toId用户不存在
none : 双方无关系
following : id用户关注了toId用户
follower : toId用户关注了id用户
friend : 互相关注
 */
$api->get('/{id}/friendship', function (Request $request, $id) {
	$user = convert($id, 'user');
	$currentUser = getCurrentUser();
	$toIds = $request->query->get('toIds');
	if (!empty($toIds)) {
		$toIds = explode(',', $toIds);
	} else {
		$toIds = array($currentUser['id']);
	}
	foreach ($toIds as $toId) {
		$toUser = ServiceKernel::instance()->createService('User.UserService')->getUser($toId);
		if (empty($toUser)) {
			$result[] = 'no-user';
			continue;
		}
		//关注id的人
		$follwers = ServiceKernel::instance()->createService('User.UserService')->findAllUserFollower($user['id']);
		//id关注的人
		$follwings = ServiceKernel::instance()->createService('User.UserService')->findAllUserFollowing($user['id']);

		$toId = $toUser['id'];
		if (!empty($follwers[$toId])) {
			$result[] = !empty($follwings[$toId]) ? 'friend' : 'follower';
		} else {
			$result[] = !empty($follwings[$toId]) ? 'following' : 'none';
		}
	}

	return $result;
});

//获得用户虚拟币账户信息
$api->get('{id}/accounts', function ($id) {
	$user = convert($id, 'user');
	$accounts = ServiceKernel::instance()->createService('Cash.CashAccountService')->getAccountByUserId($user['id']);
	return $accounts;
});

/*
## 获取用户的话题
GET /users/{id}/coursethreads

[支持分页](global-parameter.md)

 ** 参数 **

| 名称  | 类型  | 必需   | 说明 |
| ---- | ----- | ----- | ---- |
| type | string | 否 | 类型,未传则取全部类型 |

`type`的值有：

 * question : 问答
 * discussion : 话题

 ** 响应 **

```
{
"xxx": "xxx"
}
```
 */

$api->get('{id}/coursethreads', function (Request $request, $id) {
	$user = convert($id, 'user');
	$start = $request->query->get('start', 0);
	$limit = $request->query->get('limit', 10);
	$type = $request->query->get('type', '');
	$conditions = empty($type) ? array() : array('type' => $type);
	$conditions['userId'] = $user['id'];
	$total = ServiceKernel::instance()->createService('Course.ThreadService')->searchThreadCount($conditions);
	$coursethreads = ServiceKernel::instance()->createService('Course.ThreadService')->searchThreads($conditions, 'created', $start, $limit);

	return array(
		'data' => $coursethreads,
		'total' => $total,
	);
});

/*
## 好友互粉
POST /users/friendship

 ** 参数 **

| 名称  | 类型  | 必需   | 说明 |
| ---- | ----- | ----- | ---- |
| fromId | int | 是 | 互粉用户A的Id |
| toId | int | 是 | 互粉用户B的Id |

 ** 响应 **

```
{
'success' => bool
}
```
 */
$api->post('/friendship', function (Request $request) {
	$fromId = $request->request->get('fromId', 0);
	$toId = $request->request->get('toId', 0);
	$result1 = ServiceKernel::instance()->createService('User.UserService')->follow($fromId, $toId);
	$result2 = ServiceKernel::instance()->createService('User.UserService')->follow($toId, $fromId);
	return array(
		'success' => ($result1 && $result2) ? true : false,
	);
});
return $api;