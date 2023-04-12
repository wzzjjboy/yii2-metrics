<?php

namespace yii2\metrics\storage;

use yii\redis\Connection;

class Redis
{
    /**
     * @var Connection
     */
    private $redis;

    /**
     * @var
     */
    private $prefix;

    /**
     * Redis constructor.
     * @param mixed[] $options
     */
    public function __construct( Connection $redis)
    {
        $redis->select(0);
        $this->redis = $redis;
    }

    /**
     * 由于php没有办法常驻内存，所以指标的存储需要借助其它芥子
     * 该方法负责清理已经存储的数据
     * 注：会清理所有数据，谨慎调用
     * @return void
     */
    public function wipeStorage($prefix): void
    {
        $searchPattern = $prefix;
        $searchPattern .= '*';
        $cursor = 0;
        do {
            $data = $this->redis->scan($cursor,"MATCH",$searchPattern, "COUNT", 10000);
            $cursor = $data[0] ?? 0;
            $keys = $data[1] ?? [];
            if ($keys) {
                $this->redis->del(...$keys);
                \Yii::debug(["del redis metrics keys" => $keys]);
            }
        }while($data && $cursor);
    }

}