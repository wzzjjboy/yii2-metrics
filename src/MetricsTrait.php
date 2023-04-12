<?php


namespace yii2\metrics;


use Yii;
use Prometheus\Storage\Redis;
use yii\filters\AccessControl;
use yii\redis\Connection;
use Prometheus\RenderTextFormat;
use yii\web\NotFoundHttpException;

trait MetricsTrait
{
    private $application;

    private $modulesName;

    /**
     * @param string $application
     */
    public function setApplication($application): void
    {
        $this->application = $application;
    }

    /**
     * @param string $modulesName
     */
    public function setModulesName($modulesName): void
    {
        $this->modulesName = $modulesName;
    }

    private function getPrefix ()
    {
        if (is_null($this->application)) {
            $this->application = YII_APP_NAME;
        }
        if (is_null($this->modulesName)) {
            $this->modulesName = Yii::$app->id;
        }

        return sprintf("%s_%s_%s", "PROMETHEUS", strtoupper($this->application), strtoupper($this->modulesName));
    }

    public function initRedis()
    {
        $redis = Yii::$app->get("redis");
        if ($redis instanceof Connection) {
            $redisHost = $redis->hostname;
            $redisPort = $redis->port;
            $redisPassword = $redis->password;
            Redis::setPrefix($this->getPrefix());
            \Prometheus\Storage\Redis::setDefaultOptions(
                [
                    'host' => $redisHost,
                    'port' => $redisPort ?: 6379,
                    'password' => $redisPassword,
                    'timeout' => 1, // in seconds
                    'read_timeout' => '10', // in seconds
                    'persistent_connections' => false
                ]
            );
        }
    }


    public function actionIndex()
    {
        $this->getMetrics();
    }

    protected function getMetrics()
    {
        $this->initRedis();
        $registry = \Prometheus\CollectorRegistry::getDefault();
        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());
        header('Content-type: ' . RenderTextFormat::MIME_TYPE);
        echo $result;
        exit(0);
    }


    protected function getBaseMetrics()
    {
        return [
            'hostname'       => gethostname(),
            'instance'       => sprintf("%s:80", $this->getServerIp()),
            'ip'             => $this->getServerIp(),
        ];
    }

    /**
     * Get current server ip.
     *
     * @return string
     */
    private function getServerIp(): string
    {
        if (!empty($_SERVER['SERVER_ADDR'])) {
            $ip = $_SERVER['SERVER_ADDR'];
        } elseif (!empty($_SERVER['SERVER_NAME'])) {
            $ip = gethostbyname($_SERVER['SERVER_NAME']);
        } else {
            // for php-cli(phpunit etc.)
            $ip = defined('PHPUNIT_RUNNING') ? '127.0.0.1' : gethostbyname(gethostname());
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ?: '127.0.0.1';
    }

    /**
     * 增加ip白名单
     * @param $behaviors
     * @param array $ips
     */
    public function fillIpControllerBehavior(&$behaviors, $ips = ['*'])
    {
        $behaviors['metrics_access'] = [
            'class' => AccessControl::class,
            'only' => ['index'],
            'rules' => [
                [
                    'ips' => $ips,//这里填写允许访问的IP
                    'allow' => true,
                ],
            ],
            'denyCallback' => function($rule, $action){
                throw new NotFoundHttpException();
            }
        ];
    }
}