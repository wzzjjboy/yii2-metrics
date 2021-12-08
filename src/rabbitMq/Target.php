<?php


namespace yii2\metrics\rabbitMq;


use common\utils\Http;
use console\crontab\interfaces\ITarget;

class Target implements ITarget {

    public $mqHost;

    public $mqPort;

    public $queryName;

    public $mqUsername;

    public $mqPassword;

    public function getTargetData() {
        $url = sprintf("http://%s:%d/api/queues/", $this->mqHost, "1" . ltrim(strval($this->mqPort), "1")) . '%2F/';
        $url =  $url .  str_replace('#', '%23', $this->queryName);
        $http = new Http();
        try{
            $rsp = $http->get($url, [], ['json' => true, 'auth' => ['type' => 'basic', 'username' =>$this->mqUsername, 'password' => $this->mqPassword]]);
            $rspArray = json_decode($rsp['data'], true);
            \Yii::debug(['query rabbit mq resp' => $rspArray], __METHOD__);
            return $rspArray;
        }catch (\Exception $exception){
            return false;
        }
    }
}