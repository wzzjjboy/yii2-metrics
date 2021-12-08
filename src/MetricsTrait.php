<?php


namespace yii2\metrics;


use Prometheus\Storage\Redis;
use Yii;
use yii\redis\Connection;
use Prometheus\RenderTextFormat;
use yii2\metrics\filters\MetricsFilter;

trait MetricsTrait
{
    public function initRedis()
    {
        $redis = Yii::$app->get("redis");
        if ($redis instanceof Connection) {
            $redisHost = $redis->hostname;
            $redisPort = $redis->port;
            $redisPassword = $redis->password;
            Redis::setPrefix(sprintf("%s_%s_%s_", "PROMETHEUS", strtoupper(YII_APP_NAME), strtoupper(Yii::$app->id)));
            \Prometheus\Storage\Redis::setDefaultOptions(
                [
                    'host' => $redisHost,
                    'port' => $redisPort ?: 6379,
                    'password' => $redisPassword,
                    'timeout' => 0.1, // in seconds
                    'read_timeout' => '10', // in seconds
                    'persistent_connections' => false
                ]
            );
        }
    }

    public function actionIndex()
    {
        $this->initRedis();
        $registry = \Prometheus\CollectorRegistry::getDefault();
        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());
        header('Content-type: ' . RenderTextFormat::MIME_TYPE);
        echo $result;
        exit(0);
    }

    public function actionTs()
    {
        /** @var Connection $redis */
        $redis = Yii::$app->redis;
        $redis->select(0);
        $cursor = 0;
        do {
            list($cursor, $keys) = $redis->scan($cursor, 'MATCH',  'PROMETHEUS_*');
            $cursor = (int) $cursor;
            if (!empty($keys)) {
                $redis->executeCommand('DEL', $keys);
            }
        } while ($cursor !== 0);
    }

    public function fillMetricsBehavior(&$behaviors, $appName = YII_APP_NAME)
    {
        $behaviors['metrics'] = [
            'class' => MetricsFilter::class,
            'appName' => $appName,
        ];
    }

    public function getBaseMetrics()
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
}