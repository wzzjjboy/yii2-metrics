<?php


namespace yii2\metrics\filters;


use Yii;
use Closure;
use yii\base\Action;
use yii\web\Request;
use yii\web\Response;
use yii\base\ActionFilter;
use yii2\metrics\MetricsTrait;
use yii\base\InvalidConfigException;

class MetricsFilter extends ActionFilter
{
    use MetricsTrait;

    /**
     * @var Request
     */
    private $request;
    /**
     * @var Response
     */
    private $response;
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

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        if (empty($this->appName)) {
            throw new InvalidConfigException("appName is empty...");
        }
        if ($this->request === null) {
            $this->request = Yii::$app->getRequest();
        }
        if ($this->response === null) {
            $this->response = Yii::$app->getResponse();
        }

        $this->initRedis();
    }

    /**
     * {@inheritdoc}
     * @param Action $action
     * @throws \Throwable
     */
    public function beforeAction($action)
    {
        $this->startAt = microtime(true);

        if ($this->user === null && Yii::$app->getUser()) {
            $this->user = Yii::$app->getUser()->getIdentity(false);
        }
        if ($this->user instanceof Closure) {
            $this->user = call_user_func($this->user, $action);
        }
        Yii::debug('before save metrics', __METHOD__);

        return true;
    }

    /**
     * This method is invoked right after an action is executed.
     * You may override this method to do some postprocessing for the action.
     * @param Action $action the action just executed.
     * @param mixed $result the action execution result
     * @return mixed the processed action result.
     * @throws \Prometheus\Exception\MetricsRegistrationException
     */
    public function afterAction($action, $result)
    {
        $registry = \Prometheus\CollectorRegistry::getDefault();
        $registry
            ->getOrRegisterHistogram($this->getNamespace(), $this->getName(), $this->getHelp(), array_keys($this->getLabels()))
            ->observe(microtime(true) - $this->startAt, $this->getLabels());
        return $result;
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
        $labels = array_merge([
            'request_status' => $this->response->statusCode,
            'request_path'   => $this->request->getPathInfo(),
            'request_method' => $this->request->method,

        ], $this->getBaseMetrics());
        Yii::debug(["metrics labels" => $labels], __METHOD__);
        return $labels;
    }

    private function getBuckets(): array
    {
        return [0.05, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.8, 1.0, 2.0, 5.0, 10.0, 20.0];
    }
}