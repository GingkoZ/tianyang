<?php
namespace Topxia\Service\Task\Impl;

use Topxia\Service\Task\TaskService;
use Topxia\Service\Common\BaseService;

class TaskServiceImpl extends BaseService implements TaskService
{
    public function getTask($id)
    {
        return $this->getTaskDao()->getTask($id);
    }

    public function getTaskByParams(array $conditions)
    {
        $tasks = $this->getTaskDao()->searchTasks($conditions, array('taskStartTime', 'ASC'), 0, 1);
        return $tasks ? $tasks[0] : null;
    }

    public function findUserTasksByBatchIdAndTaskType($userId, $batchId, $taskType)
    {
        return $this->getTaskDao()->findUserTasksByBatchIdAndTaskType($userId, $batchId, $taskType);
    }

    public function findUserCompletedTasks($userId, $batchId)
    {
        return $this->getTaskDao()->findUserCompletedTasks($userId, $batchId);
    }

    public function addTask(array $fields)
    {
        return $this->getTaskDao()->addTask($fields);
    }

    public function updateTask($id, array $fields)
    {
        return $this->getTaskDao()->updateTask($id, $fields);
    }

    public function deleteTask($id)
    {
        return $this->getTaskDao()->deleteTask($id);
    }

    public function deleteTasksByBatchIdAndTaskTypeAndUserId($batchId, $taskType, $userId)
    {
        return $this->getTaskDao()->deleteTasksByBatchIdAndTaskTypeAndUserId($batchId, $taskType, $userId);
    }

    public function searchTasks($conditions, $orderBy, $start, $limit)
    {
        return $this->getTaskDao()->searchTasks($conditions, $orderBy, $start, $limit);
    }

    public function searchTaskCount($conditions)
    {
        return $this->getTaskDao()->searchTaskCount($conditions);
    }

    public function finishTask(array $targetObject, $taskType)
    {
        $user   = $this->getCurrentUser();
        $userId = $user->id;

        if ($targetObject['type'] == 'homework' || $targetObject['type'] == 'testpaper') {
            $userId = $targetObject['userId'];
        }

        $conditions = array(
            'userId'     => $userId,
            'taskType'   => $taskType,
            'targetId'   => $targetObject['id'],
            'targetType' => $targetObject['type'],
            'status'     => 'active'
        );
        $getTask = $this->getTaskByParams($conditions);

        if ($getTask) {
            $canFinished = $this->_canFinished($getTask, $targetObject);

            if ($canFinished) {
                $updateInfo = array('status' => 'completed', 'completedTime' => time());
                return $this->updateTask($getTask['id'], $updateInfo);
            }
        }

        return array();
    }

    private function _canFinished($task, $targetObject)
    {
        $canFinished = true;

        if ($task['required'] && ($targetObject['type'] == 'homework' || $targetObject['type'] == 'testpaper')) {
            if ($targetObject['passedStatus'] == 'unpassed' || $targetObject['passedStatus'] == 'none') {
                $canFinished = false;
            }
        }

        return $canFinished;
    }

    protected function getTaskDao()
    {
        return $this->createDao('Task.TaskDao');
    }
}
