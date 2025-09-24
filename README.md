# roaring

基于[https://github.com/RoaringBitmap/CRoaring](https://github.com/RoaringBitmap/CRoaring)实现的php版本bitmap

## 安装

```bash
composer require buexplain/roaring
```

## 示例

```php
require "vendor/autoload.php";
use Roaring\Bitmap;

//求并集
$a = new Bitmap();
$b = new Bitmap();
$a->addMany([1, 2]);
$b->addMany([2, 3]);
$c = $a->or($b);
print_r($c->toArray()); //[1, 2, 3]

//求交集
$a = new Bitmap();
$b = new Bitmap();
$a->addMany([1, 2, 3]);
$b->addMany([2, 3, 4]);
$c = $a->and($b);
print_r($c->toArray()); //[2, 3]

//求差集
$a = new Bitmap();
$b = new Bitmap();
$a->addMany([1, 2, 3]);
$b->addMany([1, 3, 4]);
$c = $a->andNot($b);
print_r($c->toArray()); //[2]

//求对称差集
$a = new Bitmap();
$b = new Bitmap();
$a->addMany([1, 2, 3]);
$b->addMany([3, 4, 5]);
$c = $a->xOr($b);
print_r($c->toArray()); //[1, 2, 4, 5]

//批量迭代整个bitmap
$a = new Bitmap();
$a->addRange(0, 100);
$generator = $a->iterate(10);
foreach ($generator as $v) {
    print_r($v); //每次循环最多获取10个值
}
```

## 注意事项

PHP的整型数 int 的字长和平台有关， PHP 不支持无符号的 int，基于这个原因，要注意以下两点：

1. 使用64位的bitmap时，能写入的最大值是`PHP_INT_MAX`常量值。
2. 当反序列化的数据来自其它语言的实现时，因为php对`uint64`支持范围不完整，所以可能会出现异常情况，这一点尤其要注意。

## centos下安装php的ffi扩展

### 编译安装

```bash
/usr/local/bin/php8.2.10/bin/phpize
./configure --with-php-config=/usr/local/bin/php8.2.10/bin/php-config
make && make install
```

### 打开配置文件中的ffi扩展开关

```bash
php --ini
vi /usr/local/bin/php8.2.10/etc/php.ini
```

### 错误解决

1. `No package 'libffi' found`，执行命令：`yum install libffi-devel`
2. `错误：只允许在 C99 模式下使用‘for’循环初始化声明`，升级gcc