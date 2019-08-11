## 场景

- 常驻内存程序使用，解决长时间不操作造成的掉线、抛异常、造成的程序退出

## 原理

- 客户端连接在MYSQL设置的interactive_timeout之间内没有任何操作，会被主动断开，造成操作报错
- 捕捉数据库出现的CR_SERVER_GONE_ERROR、CR_SERVER_LOST两类报错，并自动重连，并重新执行之前想执行的SQL操作

## 使用

> 析构函数增加了max_retry_connect参数，申明最大重连次数，此参数默认值2

```php
<?php
use Janfish\Phalcon\Db\Adapter\Pdo\Mysql as Mysql;

$di->setShared('db', function () {
    return new Mysql([
        'adapter' => 'Mysql',
        'host' => 'localhost',
        'port' => '3306',
        'username' => 'root',
        'password' => 'root',
        'dbname' => 'test',
        'max_retry_connect' => 2,
    ]);
});
```

## 参考资料

https://dev.mysql.com/doc/refman/5.7/en/client-error-reference.html#error_cr_server_gone_error
https://dev.mysql.com/doc/refman/5.7/en/client-error-reference.html#error_cr_server_lost


