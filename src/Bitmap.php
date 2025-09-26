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
use Generator;
use RuntimeException;

/**
 * https://roaringbitmap.github.io/CRoaring/
 * library.h文件有每个函数的描述
 */
class Bitmap
{
    /**
     * 32 位 64 位的 位图对象反序列化模版
     * @var array|string[]
     */
    protected static array $unSerializeTpl = [
        Library::BIT_32 => 'O:14:"Roaring\Bitmap":1:{s:9:"bitmapBit";i:32;}',
        Library::BIT_64 => 'O:14:"Roaring\Bitmap":1:{s:9:"bitmapBit";i:64;}',
    ];

    /**
     * 表示是32 位 还是 64 位
     * @var int 32 or 64
     */
    protected int $bit = 0;

    /**
     * 指向底层bitmap对象的指针
     * @var object|null
     */
    protected object|null $bitmap = null;

    /**
     * 构造函数
     * @param int $bit 32 or 64
     * @param string|null $bitmapBytes
     */
    final public function __construct(int $bit = Library::BIT_32, string|null $bitmapBytes = null)
    {
        $this->bit = $bit;
        if ($bitmapBytes === null) {
            $this->bitmap = Library::getInstance($this->bit)->create();
            if (is_null($this->bitmap)) {
                throw new RuntimeException("bitmap create failed");
            }
        } else {
            $length = strlen($bitmapBytes);
            $buf = Library::getFFI()->new("char[" . ($length + 1) . "]");
            FFI::memcpy($buf, $bitmapBytes, $length);
            $buf[$length] = "\0";
            $ptr = FFI::addr($buf[0]);
            $this->bitmap = Library::getInstance($this->bit)->portable_deserialize($ptr, $length);
            if (is_null($this->bitmap)) {
                throw new RuntimeException("bitmap portable_deserialize failed");
            }
        }
    }

    /**
     * 析构函数
     */
    final public function __destruct()
    {
        if ($this->bitmap) {
            Library::getInstance($this->bit)->free($this->bitmap);
            $this->bitmap = null;
        }
    }

    /**
     * 转为字节码
     * @return string
     */
    public function toBytes(): string
    {
        $size = Library::getInstance($this->bit)->portable_size_in_bytes($this->bitmap);
        $buf = Library::getFFI()->new("char[$size]");
        $ptr = FFI::addr($buf[0]);
        $size = Library::getInstance($this->bit)->portable_serialize($this->bitmap, $ptr);
        return FFI::string($buf, $size);
    }

    /**
     * 序列化
     * @return array
     */
    final public function __serialize(): array
    {
        return [
            'bitmapBit' => $this->bit,
            'bitmapBytes' => base64_encode($this->toBytes()),
        ];
    }

    /**
     * 反序列化
     * @param array $data
     * @return void
     */
    final public function __unserialize(array $data): void
    {
        $this->bit = $data['bitmapBit'];
        if (isset($data['bitmapBytes'])) {
            $data['bitmapBytes'] = base64_decode($data['bitmapBytes']);
            $length = strlen($data['bitmapBytes']);
            $buf = Library::getFFI()->new("char[" . ($length + 1) . "]");
            FFI::memcpy($buf, $data['bitmapBytes'], $length);
            $buf[$length] = "\0";
            $ptr = FFI::addr($buf[0]);
            $this->bitmap = Library::getInstance($this->bit)->portable_deserialize($ptr, $length);
            if (is_null($this->bitmap)) {
                throw new RuntimeException("bitmap portable_deserialize failed");
            }
        }
    }

    /**
     * 克隆位图
     * @return void
     */
    final public function __clone()
    {
        $this->bitmap = Library::getInstance($this->bit)->copy($this->bitmap);
    }

    /**
     * 优化存储结构
     * @return bool
     */
    public function runOptimize(): bool
    {
        return Library::getInstance($this->bit)->run_optimize($this->bitmap);
    }

    /**
     * 清空位图内容，移除所有辅助分配
     * @return $this
     */
    public function clear(): self
    {
        Library::getInstance($this->bit)->clear($this->bitmap);
        return $this;
    }

    /**
     * 添加单个值到位图
     * @param int $x
     * @param int ...$vals 更多的值
     * @return $this
     */
    public function add(int $x, int ...$vals): self
    {
        Library::getInstance($this->bit)->add($this->bitmap, $x);
        return $this->addMany($vals);
    }

    /**
     * 批量添加多个值，比重复调用 add 更快
     * @param array|int[] $vals
     * @return self
     */
    public function addMany(array $vals): self
    {
        $card = count($vals);
        if ($card === 0) {
            return $this;
        }
        $buff = Library::getFFI()->new(sprintf('uint%d_t[%d]', $this->bit, $card));
        for ($i = 0; $i < $card; $i++) {
            $buff[$i] = $vals[$i];
        }
        $ptr = FFI::addr($buff[0]);
        Library::getInstance($this->bit)->add_many($this->bitmap, $card, $ptr);
        return $this;
    }

    /**
     * 添加值并返回是否为新值（已存在返回 false）
     * @param int $x
     * @return bool
     */
    public function addChecked(int $x): bool
    {
        return Library::getInstance($this->bit)->add_checked($this->bitmap, $x);
    }

    /**
     * 添加指定范围 [min, max) 内的所有值
     * @param int $min
     * @param int $max
     * @return $this
     */
    public function addRange(int $min, int $max): self
    {
        Library::getInstance($this->bit)->add_range($this->bitmap, $min, $max);
        return $this;
    }

    /**
     * 从位图中删除单个值
     * @param int $x
     * @return $this
     */
    public function remove(int $x): self
    {
        Library::getInstance($this->bit)->remove($this->bitmap, $x);
        return $this;
    }

    /**
     * 批量删除多个值
     * @param array|int[] $x
     * @return self
     */
    public function removeMany(array $x): self
    {
        $card = count($x);
        if ($card === 0) {
            return $this;
        }
        $buff = Library::getFFI()->new(sprintf('uint%d_t[%d]', $this->bit, $card));
        for ($i = 0; $i < $card; $i++) {
            $buff[$i] = $x[$i];
        }
        $ptr = FFI::addr($buff[0]);
        Library::getInstance($this->bit)->remove_many($this->bitmap, $card, $ptr);
        return $this;
    }

    /**
     * 删除值并返回是否成功删除（不存在返回 false）
     * @param int $x
     * @return bool
     */
    public function removeChecked(int $x): bool
    {
        return Library::getInstance($this->bit)->remove_checked($this->bitmap, $x);
    }

    /**
     * 删除指定范围 [min, max) 内的所有值
     * @param int $min
     * @param int $max
     * @return $this
     */
    public function removeRange(int $min, int $max): self
    {
        Library::getInstance($this->bit)->remove_range($this->bitmap, $min, $max);
        return $this;
    }

    /**
     * 获取位图中元素个数（基数）
     * @return int
     */
    public function getCardinality(): int
    {
        return Library::getInstance($this->bit)->get_cardinality($this->bitmap);
    }

    /**
     * 获取范围 [range_start, range_end) 内的元素数量
     * @param int $range_start
     * @param int $range_end
     * @return int
     */
    public function rangeCardinality(int $range_start, int $range_end): int
    {
        return Library::getInstance($this->bit)->range_cardinality($this->bitmap, $range_start, $range_end);
    }

    /**
     * 检查值是否存在于位图中
     * @param int $val
     * @return bool
     */
    public function contains(int $val): bool
    {
        return Library::getInstance($this->bit)->contains($this->bitmap, $val);
    }

    /**
     * 检查范围 [range_start, range_end) 内所有值是否存在
     * @param int $range_start
     * @param int $range_end
     * @return bool
     */
    public function containsRange(int $range_start, int $range_end): bool
    {
        return Library::getInstance($this->bit)->contains_range($this->bitmap, $range_start, $range_end);
    }

    /**
     * 返回位图中小于等于 x 的元素个数
     * @param int $x
     * @return int
     */
    public function rank(int $x): int
    {
        return Library::getInstance($this->bit)->rank($this->bitmap, $x);
    }

    /**
     * 获取指定排名（从0开始）的元素，成功时返回元素，失败返回null
     * @param int $rank
     * @return int|null
     */
    public function select(int $rank): ?int
    {
        $val = Library::getFFI()->new(sprintf('uint%d_t', $this->bit));
        $ptr = FFI::addr($val);
        $ok = Library::getInstance($this->bit)->select($this->bitmap, $rank, $ptr);
        if ($ok) {
            return $val->cdata;
        }
        return null;
    }

    /**
     * 返回位图中的最小值，位图为空时返回int的最大值
     * bit32 返回 UINT32_MAX
     * bit64 返回 PHP_INT_MAX
     * @return int
     */
    public function minimum(): int
    {
        if ($this->bit === Library::BIT_32) {
            return Library::getInstance($this->bit)->minimum($this->bitmap);
        }
        //整型数 int 的字长和平台有关， PHP 不支持无符号的 int， 所以当bitmap为空时，只能用 PHP_INT_MAX
        return Library::getInstance($this->bit)->is_empty($this->bitmap) ? PHP_INT_MAX : Library::getInstance($this->bit)->minimum($this->bitmap);
    }

    /**
     * 返回位图中的最大值，位图为空时返回 0
     * @return int
     */
    public function maximum(): int
    {
        return Library::getInstance($this->bit)->maximum($this->bitmap);
    }

    /**
     * 比较两个位图是否包含相同元素
     * @param Bitmap $bitmap
     * @return bool
     */
    public function equals(Bitmap $bitmap): bool
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
        }
        return Library::getInstance($this->bit)->equals($this->bitmap, $bitmap->bitmap);
    }

    /**
     * 检查两个位图是否有交集
     * @param Bitmap $bitmap
     * @return bool
     */
    public function intersect(Bitmap $bitmap): bool
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
        }
        return Library::getInstance($this->bit)->intersect($this->bitmap, $bitmap->bitmap);
    }

    /**
     * 检查位图是否为空（基数为零）
     * @return bool
     */
    public function isEmpty(): bool
    {
        return Library::getInstance($this->bit)->is_empty($this->bitmap);
    }

    /**
     * 计算两个位图的并集，返回新位图
     * @param Bitmap|string $bitmap 位图对象或位图字节码
     * @return Bitmap
     */
    public function or(Bitmap|string $bitmap): Bitmap
    {
        if (is_string($bitmap)) {
            if ($bitmap === '') {
                return clone $this;
            }
            $bitmap = new self($this->bit, $bitmap);
        } else {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
        }
        $ptr = Library::getInstance($this->bit)->or($this->bitmap, $bitmap->bitmap);
        if (is_null($ptr)) {
            throw new RuntimeException("bitmap or failed");
        }
        $bp = unserialize(self::$unSerializeTpl[$this->bit]);
        $bp->bitmap = $ptr;
        return $bp;
    }

    /**
     * 原地计算并集，计算结果保存到this中
     * @param Bitmap|string $bitmap 位图对象或位图字节码
     * @return Bitmap
     */
    public function orInPlace(Bitmap|string $bitmap): self
    {
        if (is_string($bitmap)) {
            if ($bitmap === '') {
                return $this;
            }
            $bitmap = new self($this->bit, $bitmap);
        } else {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
        }
        Library::getInstance($this->bit)->or_inplace($this->bitmap, $bitmap->bitmap);
        return $this;
    }

    /**
     * 计算两个位图并集的元素总数
     * @param Bitmap|string $bitmap 位图对象或位图字节码
     * @return int
     */
    public function orCardinality(Bitmap|string $bitmap): int
    {
        if (is_string($bitmap)) {
            if ($bitmap === '') {
                return $this->getCardinality();
            }
            $bitmap = new self($this->bit, $bitmap);
        } else {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
        }
        return Library::getInstance($this->bit)->or_cardinality($this->bitmap, $bitmap->bitmap);
    }

    /**
     * 计算两个位图的对称差集（异或），返回新位图
     * @param Bitmap|string $bitmap 位图对象或位图字节码
     * @return Bitmap
     */
    public function xOr(Bitmap|string $bitmap): Bitmap
    {
        if (is_string($bitmap)) {
            if ($bitmap === '') {
                return clone $this;
            }
            $bitmap = new self($this->bit, $bitmap);
        } else {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
        }
        $ptr = Library::getInstance($this->bit)->xor($this->bitmap, $bitmap->bitmap);
        if (is_null($ptr)) {
            throw new RuntimeException("bitmap or failed");
        }
        $bp = unserialize(self::$unSerializeTpl[$this->bit]);
        $bp->bitmap = $ptr;
        return $bp;
    }

    /**
     * 原地计算异或，计算结果保存到this中
     * @param Bitmap|string $bitmap 位图对象或位图字节码
     * @return Bitmap
     */
    public function xOrInPlace(Bitmap|string $bitmap): self
    {
        if (is_string($bitmap)) {
            if ($bitmap === '') {
                return $this;
            }
            $bitmap = new self($this->bit, $bitmap);
        } else {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
        }
        Library::getInstance($this->bit)->xor_inplace($this->bitmap, $bitmap->bitmap);
        return $this;
    }

    /**
     * 计算两个位图对称差集的元素总数
     * @param Bitmap|string $bitmap 位图对象或位图字节码
     * @return int
     */
    public function xOrCardinality(Bitmap|string $bitmap): int
    {
        if (is_string($bitmap)) {
            if ($bitmap === '') {
                return $this->getCardinality();
            }
            $bitmap = new self($this->bit, $bitmap);
        } else {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
        }
        return Library::getInstance($this->bit)->xor_cardinality($this->bitmap, $bitmap->bitmap);
    }

    /**
     * 计算两个位图的交集，返回新位图
     * @param Bitmap|string $bitmap 位图对象或位图字节码
     * @return Bitmap
     */
    public function and(Bitmap|string $bitmap): Bitmap
    {
        if (is_string($bitmap)) {
            if ($bitmap === '') {
                return new self($this->bit);
            }
            $bitmap = new self($this->bit, $bitmap);
        } else {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
        }
        $ptr = Library::getInstance($this->bit)->and($this->bitmap, $bitmap->bitmap);
        if (is_null($ptr)) {
            throw new RuntimeException("bitmap or failed");
        }
        $bp = unserialize(self::$unSerializeTpl[$this->bit]);
        $bp->bitmap = $ptr;
        return $bp;
    }

    /**
     * 原地计算交集，计算结果保存到this中
     * @param Bitmap|string $bitmap 位图对象或位图字节码
     * @return Bitmap
     */
    public function andInPlace(Bitmap|string $bitmap): self
    {
        if (is_string($bitmap)) {
            if ($bitmap === '') {
                return $this->clear();
            }
            $bitmap = new self($this->bit, $bitmap);
        } else {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
        }
        Library::getInstance($this->bit)->and_inplace($this->bitmap, $bitmap->bitmap);
        return $this;
    }

    /**
     * 计算两个位图交集的元素总数
     * @param Bitmap|string $bitmap 位图对象或位图字节码
     * @return int
     */
    public function andCardinality(Bitmap|string $bitmap): int
    {
        if (is_string($bitmap)) {
            if ($bitmap === '') {
                return 0;
            }
            $bitmap = new self($this->bit, $bitmap);
        } else {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
        }
        return Library::getInstance($this->bit)->and_cardinality($this->bitmap, $bitmap->bitmap);
    }

    /**
     * 计算两个位图的差集，返回新位图
     * @param Bitmap|string $bitmap 位图对象或位图字节码
     * @return Bitmap
     */
    public function andNot(Bitmap|string $bitmap): Bitmap
    {
        if (is_string($bitmap)) {
            if ($bitmap === '') {
                return clone $this;
            }
            $bitmap = new self($this->bit, $bitmap);
        } else {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
        }
        $ptr = Library::getInstance($this->bit)->andnot($this->bitmap, $bitmap->bitmap);
        if (is_null($ptr)) {
            throw new RuntimeException("bitmap or failed");
        }
        $bp = unserialize(self::$unSerializeTpl[$this->bit]);
        $bp->bitmap = $ptr;
        return $bp;
    }

    /**
     * 原地计算差集，计算结果保存到this中
     * @param Bitmap|string $bitmap 位图对象或位图字节码
     * @return Bitmap
     */
    public function andNotInPlace(Bitmap|string $bitmap): self
    {
        if (is_string($bitmap)) {
            if ($bitmap === '') {
                return $this;
            }
            $bitmap = new self($this->bit, $bitmap);
        } else {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
        }
        Library::getInstance($this->bit)->andnot_inplace($this->bitmap, $bitmap->bitmap);
        return $this;
    }

    /**
     * 计算两个位图差集的元素总数
     * @param Bitmap|string $bitmap 位图对象或位图字节码
     * @return int
     */
    public function andNotCardinality(Bitmap|string $bitmap): int
    {
        if (is_string($bitmap)) {
            if ($bitmap === '') {
                return $this->getCardinality();
            }
            $bitmap = new self($this->bit, $bitmap);
        } else {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
        }
        return Library::getInstance($this->bit)->andnot_cardinality($this->bitmap, $bitmap->bitmap);
    }

    /**
     * 获取迭代器
     * @param int $size foreach循环返回，每次返回的最大元素个数
     * @return Generator
     */
    public function iterate(int $size = 100): Generator
    {
        $buff = Library::getFFI()->new(sprintf('uint%d_t[%d]', $this->bit, $size));
        $ptr = FFI::addr($buff[0]);
        try {
            $iterator = Library::getInstance($this->bit)->iterator_create($this->bitmap);
            $card = $this->getCardinality();
            $read = 0;
            while ($read < $card) {
                $length = Library::getInstance($this->bit)->iterator_read($iterator, $ptr, $size);
                $ret = [];
                for ($i = 0; $i < $length; $i++) {
                    $ret[] = $buff[$i];
                }
                $read += $length;
                yield $ret;
            }
        } finally {
            !empty($iterator) && Library::getInstance($this->bit)->iterator_free($iterator);
        }
    }

    /**
     * 转为数组
     * @return array
     */
    public function toArray(): array
    {
        $card = $this->getCardinality();
        if ($card === 0) {
            return [];
        }
        $buff = Library::getFFI()->new(sprintf('uint%d_t[%d]', $this->bit, $card));
        $ptr = FFI::addr($buff[0]);
        Library::getInstance($this->bit)->to_uint_array($this->bitmap, $ptr);
        $ret = [];
        for ($i = 0; $i < $card; $i++) {
            $ret[] = $buff[$i];
        }
        return $ret;
    }
}