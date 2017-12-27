<?php

namespace Swoole\Thrift;

use Thrift;
use Thrift\Server\TSimpleServer;
use Swoole\Core\Di;
use Swoole\Core\Logger;

class Server extends TSimpleServer
{

    public function onReceive($serv, $fd, $from_id, $data)
    {
        if (!$this->PDOPing(\ORM::get_db('default'))) {
            \ORM::set_db(null, 'default');
            if (is_callable([$serv, 'reload'])) {
                call_user_func([$serv, 'reload']);
            }
        }

        $config = Di::get('config');
        $processor_class = $config['processor'];
        $handler_class = $config['handler'];

        $handler = new $handler_class();
        $processor = new $processor_class($handler);

        $socket = new Socket();
        $socket->setHandle($fd);
        $socket->buffer = $data;
        $socket->server = $serv;
        $protocol = new Thrift\Protocol\TBinaryProtocol($socket, false, false);

        try {
            $protocol->fname = $config['name'];
            $processor->process($protocol, $protocol);
        } catch (\Exception $e) {
            Logger::write(date('Y-m-d H:i:s').' ['.$e->getCode().'] '. $e->getMessage().PHP_EOL);
        }
    }

    /**
     * 检查连接是否可用
     * @param \PDO $conn 数据库连接
     * @return Object
     */
    private function PDOPing(\PDO $conn)
    {
        try {
            $conn->getAttribute(\PDO::ATTR_SERVER_INFO);
        } catch (\PDOException $e) {
            if ($e->getCode() == 'HY000') {
                Logger::write(date('Y-m-d H:i:s').' ['.$e->getCode().'] '. $e->getMessage().PHP_EOL);
            }
            return 0;
        }

        return $conn;
    }
}
