<?php


namespace yii2\metrics\rabbitMq;


use Yii;
use yii\base\BaseObject;
use yii2\crontab\interfaces\IHandler;
use yii2\crontab\interfaces\ITarget;
use yii2\metrics\MetricsTrait;

class Task extends BaseObject implements IHandler, ITarget {

    use MetricsTrait;

    public $task;
    public $mqHost;
    public $mqPort;
    public $queueName;
    public $mqUsername;
    public $mqPassword;
    public $date;

    /**
     * @var string 应用名
     */
    public $appName;

    public $yiiApplicationId;

    /**
     * @return ITarget
     * @throws \yii\base\InvalidConfigException
     */
    public function createTarget() {

    }

    public function handler(...$args)
    {
        if (!isset($args[0]) || empty($args[0])){
            Yii::warning("query rabbitMq status fetch unknown response...");
            return;
        }
        $resp = $args[0];
        $oId = Yii::$app->id;
        Yii::$app->id = $this->yiiApplicationId;
        $this->initRedis();
        Yii::$app->id = $oId;
        $labels = array_merge([
            'queues_name' => $this->queueName,
            'value_type' => [
                'message_count' => $resp['messages'],
                'message_nack_count' => $resp['messages_unacknowledged_ram'],
            ],
        ], $this->getBaseMetrics());
        $registry = \Prometheus\CollectorRegistry::getDefault();
        foreach ($labels['value_type'] as $type => $value) {
            $tLabels = $labels;
            $tLabels['value_type'] =  $type;
            $registry
                ->getOrRegisterGauge(strtolower(str_replace('-', '_', strtolower(YII_APP_NAME))), 'rabbit_mq', 'rabbit mq gauge',  array_keys($labels))
                ->set($value, array_values($tLabels));
        }
        return true;
    }

    public function getTargetData()
    {
        return Yii::createObject([
            'class'      => Target::class,
            'mqHost'     => $this->mqHost,
            'mqPort'     => $this->mqPort,
            'queryName'  => $this->queueName,
            'mqUsername' => $this->mqUsername,
            'mqPassword' => $this->mqPassword,
        ])->getTargetData();
    }
}