<?php

namespace yii2\metrics\rabbitMq;

use Yii;

class Factory
{

    private $cnf;

    private $taskObj;

    /**
     * Factory constructor.
     * @param $appName string 统计的应用名
     * @param $yiiApplicationId string 统计的数据和api接口合并的应用名 如app-api 、app-bakcend 可以从Yii::$app->id获取
     * @throws \yii\base\InvalidConfigException
     */
    public function __construct($appName, $yiiApplicationId)
    {
        $tasks = Yii::$app->messageQueue->tasks;
        foreach ($tasks as $task => $_taskNum) {
            $component = Yii::$app->get($task);
            $this->cnf[$task]['host'] = $component->host;
            $this->cnf[$task]['port'] = $component->port;
            $this->cnf[$task]['username'] = $component->username;
            $this->cnf[$task]['password'] = $component->password;
            $this->cnf[$task]['exchange_name'] = $component->exchange_name;
            $this->cnf[$task]['queue_name'] = $component->queue_name;
            $this->cnf[$task]['routing_key'] = $component->routing_key;
        }
        foreach ($this->cnf as $task => $item) {
            $this->taskObj[$task] = $this->createTask($task, $item, $appName, $yiiApplicationId);
        }
    }

    /**
     * @return Task[]
     */
    public function getIterator()
    {
        return $this->taskObj;
    }

    /**
     * @param $task
     * @param $item
     * @param $appName
     * @param $yiiApplicationId
     * @return Task
     * @throws \yii\base\InvalidConfigException
     */
    private function createTask($task, $item, $appName, $yiiApplicationId)
    {
        return Yii::createObject([
            'class'          => Task::class,
            'task'           => $task,
            'mqHost'         => $item['host'],
            'mqPort'         => $item['port'],
            'queueName'      => $item['queue_name'],
            'mqUsername'     => $item['username'],
            'mqPassword'     => $item['password'],
            'date'           => date("Y-m-d H:i:s"),
            'appName'        => $appName,
            'yiiApplicationId' => $yiiApplicationId,
        ]);
    }
}