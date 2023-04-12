<?php


namespace yii2\metrics\filters;


use Yii;
use yii\base\Behavior;
use yii\base\Module;
use yii\console\Request;
use yii\console\Response;
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
            Module::EVENT_BEFORE_ACTION => 'beforeAction',
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
        $this->startAt = $this->getMillisecond();
        register_shutdown_function([$this, 'afterAction']);
    }

    public function afterAction()
    {
        //过滤无效的请求
        if ($this->isInvalidRequest()){
            return;
        }
        $this->initRedis();
        $registry = \Prometheus\CollectorRegistry::getDefault();
        list($method, $path, $statusCode) = $this->getMetrics();
        $registry
            ->getOrRegisterCounter(
                $this->getNamespace(),
                'code_total',
                'http server requests error count.',
                ['path', 'code','method']
            )
            ->inc(["path" => $path, "code" => $statusCode ,"method" => $method]);
        $registry
            ->getOrRegisterHistogram(
                $this->getNamespace(),
                'duration_ms',
                'http server requests duration(ms).',
                ['path'],
                [100, 250, 500, 1000, 1500, 2000, 2500, 3000]
            )
            ->observe($this->getMillisecond() - $this->startAt, ['path' => $path]);
    }

    /**
     * 时间戳 - 精确到毫秒
     * @return float
     */
    public function getMillisecond() {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }

    /**
     * 判断当前请求是否有效
     * @return bool true:无效 false:有效
     */
    private function isInvalidRequest(): bool {
        if ($this->getRequest() instanceof Request) { //console request
            return false;
        } else { //http request
            if (empty(Yii::$app->getRequest()->getUrl())) { //过滤空路由
                return true;
            }
            if (!Yii::$app->getResponse()->isNotFound){
                return false;
            }
        }
        return true;
    }

    private function debug($data) {
        if (is_object($data)) {
            $data = print_r($data, true);
        }
        if (!is_array($data)) {
            $data = [$data];
        }
        file_put_contents(Yii::$app->getRuntimePath() . "/test.log", json_encode(array_merge([date("Y-m-d H:i:s") ,'path' => Yii::$app->getRequest()->getUrl()],$data), JSON_UNESCAPED_UNICODE). "\n",FILE_APPEND);
    }

    private function getNamespace(): string
    {
        return 'http_server_requests';
    }

    private function getMetrics(): array
    {
        if ($this->getRequest() instanceof Request) {
            $statusCode = $this->getResponse()->exitStatus;
            list($request_path) = $this->getRequest()->resolve();
            $request_method = "";
        } else {
            $statusCode = $this->getResponse()->statusCode;
            $request_path = $this->getRequest()->getPathInfo();
            $request_method = $this->getRequest()->method;
            $request_path = '/' . ltrim($request_path, '/');
        }
        return [$request_method, $request_path, $statusCode];
    }
}