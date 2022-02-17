<?php


namespace yii2\metrics\filters;


use Yii;
use Closure;
use yii\base\Action;
use yii\base\Application;
use yii\base\Behavior;
use yii\base\Component;
use yii\console\Request;
use yii\console\Response;
use yii\base\ActionFilter;
use yii2\metrics\MetricsTrait;
use yii\base\InvalidConfigException;

class MetricsFilter extends Behavior
{
    use MetricsTrait;

    /**
     * @var mixed
     */
    private $user;
    /**
     * @var float
     */
    private $startAt;

    /**
     * @var string 应用名
     */
    public $appName;

    public function events()
    {
        return [
            Application::EVENT_BEFORE_REQUEST => 'beforeAction',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        if (empty($this->appName)) {
            throw new InvalidConfigException("appName is empty...");
        }
    }

    /**
     * @return yii\web\Request|\Swoole\Http\Request
     */
    private function getRequest()
    {
        return Yii::$app->getRequest();
    }

    /**
     * @return yii\web\Response|\yii\console\Response
     */
    private function getResponse()
    {
        return Yii::$app->getResponse();
    }

    /**
     * {@inheritdoc}
     * @throws \Throwable
     */
    public function beforeAction()
    {
        $this->startAt = microtime(true);
        register_shutdown_function([$this, 'afterAction']);
    }

    public function afterAction()
    {
        $this->initRedis();
        $registry = \Prometheus\CollectorRegistry::getDefault();
        $registry
            ->getOrRegisterHistogram($this->getNamespace(), $this->getName(), $this->getHelp(), array_keys($this->getLabels()))
            ->observe(microtime(true) - $this->startAt, $this->getLabels());
    }

    private function getNamespace(): string
    {
        return sprintf('%s_%s', strtolower(YII_APP_NAME), strtolower(str_replace('-', '_', Yii::$app->id)));
    }

    private function getName(): string
    {
        return 'http_requests';
    }

    private function getHelp(): string
    {
        return 'http requests histogram!';
    }

    private function getLabels(): array
    {
        if ($this->getResponse() instanceof Response) {
            $statusCode = $this->getResponse()->exitStatus;
        } else {
            $statusCode = $this->getResponse()->statusCode;
        }
        if ($this->getRequest() instanceof Request) {
            list($route, $params) = $this->getRequest()->resolve();
            $request_path = $route;
            $request_method = $route;
        } else {
            $request_path = $this->getRequest()->getPathInfo();
            $request_method = $this->getRequest()->method;
        }
        $labels = array_merge([
            'request_status' => $statusCode,
            'request_path'   => $request_path,
            'request_method' => $request_method,

        ], $this->getBaseMetrics());
        Yii::debug(["metrics labels" => $labels], __METHOD__);
        return $labels;
    }

    private function getBuckets(): array
    {
        return [0.05, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.8, 1.0, 2.0, 5.0, 10.0, 20.0];
    }
}