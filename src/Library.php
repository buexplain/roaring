<?php
/**
 * Copyright 2025 buexplain@qq.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Roaring;

use FFI;
use FFI\CData;
use RuntimeException;

/**
 * 静态类，封装了 C 语言库的调用。这里的所有注释是ai自动从library.h文件提取的 。
 * c语言库里面的32和64位的函数是对称编写的，所以这里的@method注释的函数名字不包含位信息，在调用的时候会根据位信息进行函数名字转换。
 *
 * @method static CData create()                                         创建一个新的空位图，失败时返回 NULL。
 * @method static CData copy(CData $r)                                   复制一个位图，失败时返回 NULL。
 * @method static bool  run_optimize(CData $r)                           优化存储结构（启用游程编码），至少有一个游程容器时返回 true。
 * @method static void  clear(CData $r)                                  清空位图内容，移除所有辅助分配。
 * @method static void  free(CData $r)                                   释放位图内存。
 *
 * @method static void  add(CData $r, int $x)                            添加单个值到位图。
 * @method static void  add_many(CData $r, int $n_args, CData $vals)     批量添加多个值，比重复调用 add 更快。
 * @method static bool  add_checked(CData $r, int $x)                    添加值并返回是否为新值（已存在返回 false）。
 * @method static void  add_range(CData $r, int $min, int $max)          添加指定范围 [min, max) 内的所有值。
 * @method static void  remove(CData $r, int $x)                         从位图中删除单个值。
 * @method static void  remove_many(CData $r, int $n_args, CData $vals)  批量删除多个值。
 * @method static bool  remove_checked(CData $r, int $x)                 删除值并返回是否成功删除（不存在返回 false）。
 * @method static void  remove_range(CData $r, int $min, int $max)       删除指定范围 [min, max) 内的所有值。
 *
 * @method static int   get_cardinality(CData $r)                        获取位图中元素个数（基数）。
 * @method static int   range_cardinality(CData $r, int $range_start, int $range_end)  获取范围 [range_start, range_end) 内的元素数量。
 * @method static bool  contains(CData $r, int $val)                     检查值是否存在于位图中。
 * @method static bool  contains_range(CData $r, int $range_start, int $range_end)    检查范围 [range_start, range_end) 内所有值是否存在。
 * @method static int   rank(CData $r, int $x)                           返回位图中小于等于 x 的元素个数。
 * @method static bool  select(CData $r, int $rank, CData $element)      获取指定排名（从0开始）的元素，成功时返回 true。
 * @method static int   minimum(CData $r)                                返回位图中的最小值，位图为空时返回 UINT32_MAX or UINT64_MAX。
 * @method static int   maximum(CData $r)                                返回位图中的最大值，位图为空时返回 0。
 * @method static bool  equals(CData $r1, CData $r2)                     比较两个位图是否包含相同元素。
 * @method static bool  intersect(CData $r1, CData $r2)                  检查两个位图是否有交集。
 * @method static bool  is_empty(CData $r)                               检查位图是否为空（基数为零）。
 *
 * @method static CData or (CData $r1, CData $r2)                         计算两个位图的并集，返回新位图，失败时返回 NULL。
 * @method static void  or_inplace(CData $r1, CData $r2)                 原地计算并集，修改 r1。
 * @method static int   or_cardinality(CData $r1, CData $r2)             计算两个位图并集的元素总数。
 * @method static CData xor (CData $r1, CData $r2)                        计算两个位图的对称差集（异或），返回新位图，失败时返回 NULL。
 * @method static void  xor_inplace(CData $r1, CData $r2)                原地计算异或，修改 r1。
 * @method static int   xor_cardinality(CData $r1, CData $r2)            计算两个位图对称差集的元素总数。
 * @method static CData and (CData $r1, CData $r2)                        计算两个位图的交集，返回新位图，失败时返回 NULL。
 * @method static void  and_inplace(CData $r1, CData $r2)                原地计算交集，修改 r1。
 * @method static int   and_cardinality(CData $r1, CData $r2)            计算两个位图交集的元素总数。
 * @method static CData andnot(CData $r1, CData $r2)                     计算两个位图的差集（r1 - r2），返回新位图，失败时返回 NULL。
 * @method static void  andnot_inplace(CData $r1, CData $r2)             原地计算差集，修改 r1。
 * @method static int   andnot_cardinality(CData $r1, CData $r2)         计算两个位图差集的元素总数。
 *
 * @method static CData iterator_create(CData $r)                        创建迭代器对象，用于遍历位图中的值。
 * @method static int   iterator_read(CData $it, CData $buf, int $count) 从迭代器读取最多 count 个值到 buf，返回实际读取的元素数。
 * @method static void  iterator_free(CData $it)                         释放迭代器内存。
 *
 * @method static int   portable_size_in_bytes(CData $r)                 获取序列化位图所需的字节数。
 * @method static int   portable_serialize(CData $r, CData $buf)         将位图序列化到缓冲区，返回写入的字节数。
 * @method static CData portable_deserialize(CData $buf, int $maxbytes)                 从缓冲区反序列化位图，失败时返回 NULL。
 * @method static void  to_uint_array(CData $r, CData $ans)            将位图中所有元素导出为有序数组。
 */
class Library
{
    public const BIT_32 = 32;
    public const BIT_64 = 64;
    protected static FFI|null $ffi = null;
    protected string $bit = '';
    protected static array $instance = [];

    protected function __construct(int $bit)
    {
        $this->bit = sprintf('bp%d_', $bit);
    }

    protected static function initFFI(): void
    {
        if (!is_null(self::$ffi)) {
            return;
        }
        if (PHP_OS_FAMILY === 'Windows') {
            $library = __DIR__ . '/CRoaring/shared/library.dll';
        } else if (PHP_OS_FAMILY === 'Linux') {
            $library = __DIR__ . '/CRoaring/shared/library.so';
        } else {
            throw new RuntimeException("Unsupported operating system: " . PHP_OS_FAMILY . ": " . PHP_OS);
        }
        $header = file_get_contents(__DIR__ . '/CRoaring/shared/library.h');
        self::$ffi = FFI::cdef($header, $library);
    }

    public static function getInstance(int $bit): Library
    {
        if (isset(self::$instance[$bit])) {
            return self::$instance[$bit];
        }
        self::initFFI();
        $obj = new self($bit);
        self::$instance[$bit] = $obj;
        return $obj;
    }

    public function __call($name, $arguments)
    {
        $name = $this->bit . $name;
        return self::$ffi->$name(...$arguments);
    }
}