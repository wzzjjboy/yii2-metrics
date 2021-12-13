# yii2框架接入prometheus



接入步骤：

1. 定义常量 YII_APP_NAME，该常量用于底层区分应用，由于我们经常使用yii2的高级框架，我建议定义在common配置文件下面common/config/main.php

```php
defined('YII_APP_NAME') or define('YII_APP_NAME', 'myYiiApplication');
```

2. 在基类里面引入MetricsTrait, 该Trait提供了设置Behavior的快捷方法

```php
<?php
use yii\rest\Controller;
use yii2\metrics\MetricsTrait;
class CommonController extends Controller {
     use MetricsTrait;
     
     public function behaviors()
    {
        $behaviors = parent::behaviors();
        $this->fillMetricsBehavior($behaviors);
        return $behaviors;
    }
}
```

3. 添加支持/metrics路由

```php
<?php
namespace backend\controllers;

use yii\rest\Controller;
use yii2\metrics\MetricsTrait;

class MetricsController extends Controller {
	use MetricsTrait;
  public function behaviors(): array
  {
      $behaviors = parent::behaviors();
      $this->fillIpControllerBehavior($behaviors, ['127.0.0.1']);//允许访问访路由的ip白名单
      return $behaviors;
     }
}
```

4. 上报rabbitmq任务数数据

```php
use yii\console\Controller;
use yii2\metrics\MetricsTrait;

class  CrontabController extends Controller {
      /**
     * 上报rabbitMq任务剩余情况到prometheus
     */
    public function actionRabbitMqMetrics()
    {
        //此该填写了backend模块的id
        $factory = new \yii2\metrics\rabbitMq\Factory(YII_APP_NAME, 'app-backend');
        try {
            foreach ($factory->getIterator() as $task) {
                \yii2\crontab\base\Crontab::instance()->withTargetData($task)->withHandler($task)->run();
            }
        }catch (\Exception $exception) {
            echo sprintf("actionCheckQueues has exception msg:%s, at file:%s, at line: %d, trace: %s\n",
                $exception->getMessage(), $exception->getFile(), $exception->getLine(), '');
        }
    }

}
```

5. 添加计划任务

```shell
# 商户上报mq任务数量
* * * * * php yii crontab/rabbit-mq-metrics
```

      
