<?php


namespace yii2\mq_task\components;

use yii\db\Exception;
use yii\helpers\Inflector;
use  yii\redis\Connection;

class RedisConnection extends Connection {

    public function __call($name, $params)
    {
        $redisCommand = strtoupper(Inflector::camel2words($name, false));
        if (in_array($redisCommand, $this->redisCommands)) {
            try{
                return $this->executeCommand($redisCommand, $params);
            }catch (Exception $exception){
                $this->close();
                return $this->executeCommand($redisCommand, $params);
            }

        } else {
            return parent::__call($name, $params);
        }
    }
}