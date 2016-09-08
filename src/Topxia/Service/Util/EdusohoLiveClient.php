<?php

namespace Topxia\Service\Util;

use \RuntimeException;
use Topxia\Service\CloudPlatform\CloudAPIFactory;

class EdusohoLiveClient
{

    /**
     * 创建直播
     *
     * @param  array  $args 直播参数，支持的参数有：title, speaker, startTime, endTime, authUrl, jumpUrl, errorJumpUrl
     * @return [type]       [description]
     */
    public function createLive(array $args)
    {
        return CloudAPIFactory::create('root')->post('/lives', $args);
    }

    public function updateLive(array $args)
    {
        return CloudAPIFactory::create('root')->patch('/lives/'.$args['liveId'], $args);
    }

    public function getCapacity()
    {
        $args = array(
            'timestamp' => time() . ''
        );
        return CloudAPIFactory::create('leaf')->get('/lives/capacity', $args);
    }

    public function getRoomUrl($args, $server='leaf')
    {
        return CloudAPIFactory::create($server)->post('/lives/'.$args['liveId'].'/room_url', $args);
    }

    public function deleteLive($liveId, $provider)
    {   
        $args = array(
            "liveId" => $liveId, 
            "provider" => $provider
        );
        return CloudAPIFactory::create('root')->delete('/lives/'.$liveId, $args);
    }

    public function getMaxOnline($liveId)
    {
        $args = array(
            'liveId' => $liveId
        );
        return CloudAPIFactory::create('leaf')->get('/lives/'.$liveId.'/max_online', $args);
    }

    public function entryLive($args)
    {
        return CloudAPIFactory::create('leaf')->post('/lives/'.$args['liveId'].'/entry_room', $args);
    }

    public function entryReplay($args, $server='leaf')
    {
        return CloudAPIFactory::create($server)->post('/lives/'.$args['liveId'].'/record_url', $args);
    }

    public function createReplayList($liveId, $title, $provider)
    {
        $args = array(
            "liveId" => $liveId, 
            "title" => $title,
            "provider" => $provider
        );
        return CloudAPIFactory::create('root')->post('/lives/'.$liveId.'/records', $args);
    }

}