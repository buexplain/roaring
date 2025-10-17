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
     * @var CData|null
     */
    protected ?CData $bitmap = null;
    /**
     * 临时位图对象
     * @var CData|null
     */
    protected ?CData $temporaryBitmap = null;
    /**
     * 临时缓冲区对象
     * @var CData|null
     */
    protected ?CData $temporaryBuff = null;

    /**
     * 底层bitmap库对象
     * @var Library|null
     */
    protected ?Library $library = null;

    /**
     * @param string|null $bitmap 位图字节码 或者 位图字节码base64后的字符串 或者 null 或者 空字符串
     * @param int $bit 32 or 64
     */
    final public function __construct(string $bitmap = null, int $bit = Library::BIT_32)
    {
        $this->library = Library::getInstance($bit);
        $this->bit = $bit;
        if ($bitmap === null || $bitmap === '') {
            $this->bitmap = $this->library->create();
            if (is_null($this->bitmap)) {
                throw new RuntimeException("bitmap create failed");
            }
        } else {
            $bitmap = self::tryBase64Decode($bitmap);
            $bPtr = $this->library->create();
            $this->portableDeserialize($bPtr, $bitmap, true);
            $this->bitmap = $bPtr;
        }
    }

    protected function getTemporaryBuff(int $length): CData
    {
        if (is_null($this->temporaryBuff)) {
            $this->temporaryBuff = Library::getFFI()->new("char[" . ($length + 1) . "]");
            return $this->temporaryBuff;
        }
        $size = FFI::sizeof($this->temporaryBuff);
        if ($size < ($length + 1)) {
            $this->temporaryBuff = Library::getFFI()->new("char[" . ((int)($length * 1.25) + 1) . "]");
            return $this->temporaryBuff;
        }
        FFI::memset($this->temporaryBuff, 0, $length + 1);
        return $this->temporaryBuff;
    }

    /**
     * 获取临时位图对象
     * @return CData
     */
    protected function getTemporaryBitmap(): CData
    {
        if ($this->temporaryBitmap === null) {
            $this->temporaryBitmap = $this->library->create();
        }
        $this->library->clear($this->temporaryBitmap);
        return $this->temporaryBitmap;
    }

    protected function portableDeserialize(CData $bPtr, string $bitmap, bool $free)
    {
        $length = strlen($bitmap);
        $buf = $this->getTemporaryBuff($length);
        FFI::memcpy($buf, $bitmap, $length);
        $buf[$length] = "\0";
        $ptr = FFI::addr($buf[0]);
        $ok = $this->library->portable_deserialize($bPtr, $ptr, $length);
        if (!$ok) {
            if ($free) {
                $this->library->free($bPtr);
            }
            throw new RuntimeException("bitmap portable_deserialize failed");
        }
    }

    /**
     * 尝试进行base64解码，如果不是base64编码则返回原字符串
     * @param string $str
     * @return string
     */
    protected static function tryBase64Decode(string $str): string
    {
        // 检查字符串长度是否为4的倍数，且符合base64格式规范（包含可能的填充字符=）
        if (strlen($str) % 4 !== 0 || !preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $str)) {
            return $str;
        }
        // 尝试进行base64解码，第二个参数true表示严格模式
        $ret = base64_decode($str, true);
        // 如果解码失败或解码后为空字符串，则返回原字符串
        if ($ret === false || $ret === '') {
            return $str;
        }
        // 解码成功则返回解码后的内容
        return $ret;
    }

    /**
     * 析构函数
     */
    final public function __destruct()
    {
        if (is_object($this->bitmap)) {
            $this->library->free($this->bitmap);
            $this->bitmap = null;
        }
        if (is_object($this->temporaryBitmap)) {
            $this->library->free($this->temporaryBitmap);
            $this->temporaryBitmap = null;
        }
        if (is_object($this->temporaryBuff)) {
            $this->temporaryBuff = null;
        }
        $this->library = null;
    }

    /**
     * 转为字节码
     * @return string
     */
    final public function toBytes(): string
    {
        $size = $this->library->portable_size_in_bytes($this->bitmap);
        $buf = Library::getFFI()->new("char[$size]");
        $ptr = FFI::addr($buf[0]);
        $size = $this->library->portable_serialize($this->bitmap, $ptr);
        return FFI::string($buf, $size);
    }

    /**
     * 转为base64
     * @return string
     */
    public function toBase64(): string
    {
        return base64_encode($this->toBytes());
    }

    /**
     * 序列化
     * @return array
     */
    final public function __serialize(): array
    {
        return [
            'bitmapBit' => $this->bit,
            'bitmapBytes' => $this->toBase64(),
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
        $this->library = Library::getInstance($this->bit);
        if (isset($data['bitmapBytes'])) {
            $data['bitmapBytes'] = base64_decode($data['bitmapBytes']);
            $bPtr = $this->library->create();
            $this->portableDeserialize($bPtr, $data['bitmapBytes'], true);
            $this->bitmap = $bPtr;
        }
    }

    /**
     * 克隆位图
     * @return void
     */
    final public function __clone()
    {
        $this->bitmap = $this->library->copy($this->bitmap);
    }

    /**
     * 创建一个指定长度的缓冲区
     * @param int $size
     * @return FFI\CData|null
     */
    protected function newBuff(int $size): ?FFI\CData
    {
        return Library::getFFI()->new(sprintf('uint%d_t[%d]', $this->bit, $size));
    }

    /**
     * 优化存储结构
     * @return bool
     */
    public function runOptimize(): bool
    {
        return $this->library->run_optimize($this->bitmap);
    }

    /**
     * 清空位图内容
     * @return $this
     */
    public function clear(): self
    {
        $this->library->clear($this->bitmap);
        return $this;
    }

    /**
     * 添加单个值到位图
     * @param int ...$x
     * @return $this
     */
    public function add(int ...$x): self
    {
        $card = count($x);
        if ($card === 1) {
            $this->library->add($this->bitmap, $x[0]);
            return $this;
        }
        $buff = $this->newBuff($card);
        for ($i = 0; $i < $card; $i++) {
            $buff[$i] = $x[$i];
        }
        $ptr = FFI::addr($buff[0]);
        $this->library->add_many($this->bitmap, $card, $ptr);
        return $this;
    }

    /**
     * 批量添加多个值，比重复调用 add 更快
     * @param array|int[] $vals
     * @return $this
     */
    public function addMany(array $vals): self
    {
        $card = count($vals);
        if ($card === 0) {
            return $this;
        }
        $i = 0;
        $buff = $this->newBuff($card);
        foreach ($vals as $val) {
            $buff[$i] = $val;
            ++$i;
        }
        $ptr = FFI::addr($buff[0]);
        $this->library->add_many($this->bitmap, $card, $ptr);
        return $this;
    }

    /**
     * 添加值并返回是否为新值（已存在返回 false）
     * @param int $x
     * @return bool
     */
    public function addChecked(int $x): bool
    {
        return $this->library->add_checked($this->bitmap, $x);
    }

    /**
     * 添加指定范围 [min, max) 内的所有值
     * @param int $min
     * @param int $max
     * @return $this
     */
    public function addRange(int $min, int $max): self
    {
        $this->library->add_range($this->bitmap, $min, $max);
        return $this;
    }

    /**
     * 从位图中删除单个值
     * @param int $x
     * @return $this
     */
    public function remove(int $x): self
    {
        $this->library->remove($this->bitmap, $x);
        return $this;
    }

    /**
     * 批量删除多个值
     * @param array|int[] $x
     * @return $this
     */
    public function removeMany(array $x): self
    {
        $card = count($x);
        if ($card === 0) {
            return $this;
        }
        $buff = $this->newBuff($card);
        $i = 0;
        foreach ($x as $val) {
            $buff[$i] = $val;
            ++$i;
        }
        $ptr = FFI::addr($buff[0]);
        $this->library->remove_many($this->bitmap, $card, $ptr);
        return $this;
    }

    /**
     * 删除值并返回是否成功删除（不存在返回 false）
     * @param int $x
     * @return bool
     */
    public function removeChecked(int $x): bool
    {
        return $this->library->remove_checked($this->bitmap, $x);
    }

    /**
     * 删除指定范围 [min, max) 内的所有值
     * @param int $min
     * @param int $max
     * @return $this
     */
    public function removeRange(int $min, int $max): self
    {
        $this->library->remove_range($this->bitmap, $min, $max);
        return $this;
    }

    /**
     * 获取位图中元素个数（基数）
     * @return int
     */
    public function getCardinality(): int
    {
        return $this->library->get_cardinality($this->bitmap);
    }

    /**
     * 获取范围 [range_start, range_end) 内的元素数量
     * @param int $range_start
     * @param int $range_end
     * @return int
     */
    public function rangeCardinality(int $range_start, int $range_end): int
    {
        return $this->library->range_cardinality($this->bitmap, $range_start, $range_end);
    }

    /**
     * 检查值是否存在于位图中
     * @param int $val
     * @return bool
     */
    public function contains(int $val): bool
    {
        return $this->library->contains($this->bitmap, $val);
    }

    /**
     * 检查范围 [range_start, range_end) 内所有值是否存在
     * @param int $range_start
     * @param int $range_end
     * @return bool
     */
    public function containsRange(int $range_start, int $range_end): bool
    {
        return $this->library->contains_range($this->bitmap, $range_start, $range_end);
    }

    /**
     * 返回位图中小于等于 x 的元素个数
     * @param int $x
     * @return int
     */
    public function rank(int $x): int
    {
        return $this->library->rank($this->bitmap, $x);
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
        $ok = $this->library->select($this->bitmap, $rank, $ptr);
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
            return $this->library->minimum($this->bitmap);
        }
        //整型数 int 的字长和平台有关， PHP 不支持无符号的 int， 所以当bitmap为空时，只能用 PHP_INT_MAX
        return $this->library->is_empty($this->bitmap) ? PHP_INT_MAX : $this->library->minimum($this->bitmap);
    }

    /**
     * 返回位图中的最大值，位图为空时返回 0
     * @return int
     */
    public function maximum(): int
    {
        return $this->library->maximum($this->bitmap);
    }

    /**
     * 比较两个位图是否包含相同元素
     * @param Bitmap|string|null $bitmap
     * @return bool
     */
    public function equals($bitmap): bool
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return false;
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        return $this->library->equals($this->bitmap, $bPtr);
    }

    /**
     * 检查两个位图是否有交集
     * @param Bitmap|string|null $bitmap
     * @return bool
     */
    public function intersect($bitmap): bool
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return false;
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        return $this->library->intersect($this->bitmap, $bPtr);
    }

    /**
     * 检查位图是否为空（基数为零）
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->library->is_empty($this->bitmap);
    }

    /**
     * 计算两个位图的并集，返回新位图
     * @param Bitmap|string|null $bitmap 位图对象或位图字节码
     * @return Bitmap
     */
    public function or($bitmap): Bitmap
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return clone $this;
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        $ptr = $this->library->or($this->bitmap, $bPtr);
        if (is_null($ptr)) {
            throw new RuntimeException("bitmap or failed");
        }
        $bp = unserialize(self::$unSerializeTpl[$this->bit]);
        $bp->bitmap = $ptr;
        return $bp;
    }

    /**
     * 计算多个位图的并集，返回新位图
     * @param Bitmap|string|null ...$bitmap
     * @return Bitmap
     */
    public function orMany(...$bitmap): Bitmap
    {
        $ret = clone $this;
        foreach ($bitmap as $item) {
            $ret = $ret->orInPlace($item);
        }
        return $ret;
    }

    /**
     * 原地计算两个位图并集，计算结果保存到this中
     * @param Bitmap|string|null $bitmap 位图对象或位图字节码
     * @return $this
     */
    public function orInPlace($bitmap): self
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return $this;
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        $this->library->or_inplace($this->bitmap, $bPtr);
        return $this;
    }

    /**
     * 原地计算多个位图并集，计算结果保存到this中
     * @param Bitmap|string|null ...$bitmap
     * @return $this
     */
    public function orManyInPlace(...$bitmap): self
    {
        foreach ($bitmap as $item) {
            $this->orInPlace($item);
        }
        return $this;
    }

    /**
     * 计算两个位图并集的元素总数
     * @param Bitmap|string|null $bitmap 位图对象或位图字节码
     * @return int
     */
    public function orCardinality($bitmap): int
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return $this->getCardinality();
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        return $this->library->or_cardinality($this->bitmap, $bPtr);
    }

    /**
     * 计算两个位图的对称差集（异或），返回新位图
     * @param Bitmap|string|null $bitmap 位图对象或位图字节码
     * @return Bitmap
     */
    public function xOr($bitmap): Bitmap
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return clone $this;
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        $ptr = $this->library->xor($this->bitmap, $bPtr);
        if (is_null($ptr)) {
            throw new RuntimeException("bitmap or failed");
        }
        $bp = unserialize(self::$unSerializeTpl[$this->bit]);
        $bp->bitmap = $ptr;
        return $bp;
    }

    /**
     * 原地计算两个位图的对称差集（异或），计算结果保存到this中
     * @param Bitmap|string|null $bitmap 位图对象或位图字节码
     * @return $this
     */
    public function xOrInPlace($bitmap): self
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return $this;
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        $this->library->xor_inplace($this->bitmap, $bPtr);
        return $this;
    }

    /**
     * 计算两个位图对称差集的元素总数
     * @param Bitmap|string|null $bitmap 位图对象或位图字节码
     * @return int
     */
    public function xOrCardinality($bitmap): int
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return $this->getCardinality();
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        return $this->library->xor_cardinality($this->bitmap, $bPtr);
    }

    /**
     * 计算两个位图的交集，返回新位图
     * @param Bitmap|string|null $bitmap 位图对象或位图字节码
     * @return Bitmap
     */
    public function and($bitmap): Bitmap
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return new self(null, $this->bit);
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        $ptr = $this->library->and($this->bitmap, $bPtr);
        if (is_null($ptr)) {
            throw new RuntimeException("bitmap or failed");
        }
        $bp = unserialize(self::$unSerializeTpl[$this->bit]);
        $bp->bitmap = $ptr;
        return $bp;
    }

    /**
     * 分别与每个位图计算交集，并将所有交集结果进行并集计算，返回新位图
     * A∩(B₁∪B₂)，在A中，且（在B₁或在B₂）
     * @param Bitmap|string|null ...$bitmap
     * @return Bitmap
     */
    public function unionAndMany(...$bitmap): Bitmap
    {
        $ret = new self(null, $this->bit);
        if (count($bitmap) === 0) {
            return $ret;
        }
        $ret->orManyInPlace(...$bitmap);
        return $this->and($ret);
    }

    /**
     * 嵌套与每个位图计算交集，返回新位图
     * A∩B₁∩B₂，同时属于 A、B₁、B₂
     * @param Bitmap|string|null ...$bitmap
     * @return Bitmap
     */
    public function iteratedAndMany(...$bitmap): Bitmap
    {
        if (count($bitmap) === 0) {
            return new self(null, $this->bit);
        }
        $ret = clone $this;
        foreach ($bitmap as $item) {
            $ret->andInPlace($item);
        }
        return $ret;
    }

    /**
     * 原地计算两个位图的交集，计算结果保存到this中
     * @param Bitmap|string|null $bitmap 位图对象或位图字节码
     * @return $this
     */
    public function andInPlace($bitmap): self
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return $this->clear();
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        $this->library->and_inplace($this->bitmap, $bPtr);
        return $this;
    }

    /**
     * 分别与每个位图计算交集，并将所有交集结果进行并集计算，计算结果保存到this中
     * A∩(B₁∪B₂)，在A中，且（在B₁或在B₂）
     * @param Bitmap|string|null ...$bitmap
     * @return $this
     */
    public function unionAndManyInPlace(...$bitmap): self
    {
        if (count($bitmap) === 0) {
            $this->clear();
            return $this;
        }
        $ret = new self(null, $this->bit);
        $ret->orManyInPlace(...$bitmap);
        return $this->andInPlace($ret);
    }

    /**
     * 嵌套与每个位图计算交集，计算结果保存到this中
     * A∩B₁∩B₂，同时属于 A、B₁、B₂
     * @param Bitmap|string|null ...$bitmap
     * @return $this
     */
    public function iteratedAndManyInPlace(...$bitmap): self
    {
        if (count($bitmap) === 0) {
            return $this->clear();
        }
        foreach ($bitmap as $item) {
            $this->andInPlace($item);
        }
        return $this;
    }

    /**
     * 计算两个位图交集的元素总数
     * @param Bitmap|string|null $bitmap 位图对象或位图字节码
     * @return int
     */
    public function andCardinality($bitmap): int
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return 0;
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        return $this->library->and_cardinality($this->bitmap, $bPtr);
    }

    /**
     * 计算两个位图的差集，返回新位图
     * @param Bitmap|string|null $bitmap 位图对象或位图字节码
     * @return Bitmap
     */
    public function andNot($bitmap): Bitmap
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return clone $this;
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        $ptr = $this->library->andnot($this->bitmap, $bPtr);
        if (is_null($ptr)) {
            throw new RuntimeException("bitmap or failed");
        }
        $bp = unserialize(self::$unSerializeTpl[$this->bit]);
        $bp->bitmap = $ptr;
        return $bp;
    }

    /**
     * 分别与每个位图计算差集，并将所有差集结果进行并集计算，返回新位图
     * (A\B₁)∪(A\B₂)，只要没参与B₁或没参与B₂，就入选；排除那些同时参与了B₁和B₂的人，其余A中的人都保留
     * @param Bitmap|string|null ...$bitmap
     * @return Bitmap
     */
    public function unionAndNotMany(...$bitmap): Bitmap
    {
        if (count($bitmap) === 0) {
            return clone $this;
        }
        $ret = new self(null, $this->bit);
        foreach ($bitmap as $item) {
            $ret->orInPlace($this->andNot($item));
        }
        return $ret;
    }

    /**
     * 嵌套与每个位图计算差集，返回新位图
     * A\B₁\B₂，不在B₁且不在B₂
     * @param Bitmap|string|null ...$bitmap
     * @return Bitmap
     */
    public function iteratedAndNotMany(...$bitmap): Bitmap
    {
        if (count($bitmap) === 0) {
            return clone $this;
        }
        $ret = clone $this;
        foreach ($bitmap as $item) {
            $ret->andNotInPlace($item);
        }
        return $ret;
    }

    /**
     * 原地计算两个位图的差集，计算结果保存到this中
     * @param Bitmap|string|null $bitmap 位图对象或位图字节码
     * @return $this
     */
    public function andNotInPlace($bitmap): self
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return $this;
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        $this->library->andnot_inplace($this->bitmap, $bPtr);
        return $this;
    }

    /**
     * 分别与每个位图计算差集，并将所有差集结果进行并集计算，计算结果保存到this中
     * (A\B₁)∪(A\B₂)，只要没参与B₁或没参与B₂，就入选；排除那些同时参与了B₁和B₂的人，其余A中的人都保留
     * @param Bitmap|string|null ...$bitmap
     * @return $this
     */
    public function unionAndNotManyInPlace(...$bitmap): self
    {
        if (count($bitmap) === 0) {
            return $this;
        }
        $self = clone $this;
        $this->clear();
        foreach ($bitmap as $item) {
            $this->orInPlace($self->andNot($item));
        }
        return $this;
    }

    /**
     * 嵌套与每个位图计算差集，计算结果保存到this中
     * A\B₁\B₂，不在B₁且不在B₂
     * @param Bitmap|string|null ...$bitmap
     * @return $this
     */
    public function iteratedAndNotManyInPlace(...$bitmap): self
    {
        if (count($bitmap) === 0) {
            return $this;
        }
        foreach ($bitmap as $item) {
            $this->andNotInPlace($item);
        }
        return $this;
    }

    /**
     * 计算两个位图差集的元素总数
     * @param Bitmap|string|null $bitmap 位图对象或位图字节码
     * @return int
     */
    public function andNotCardinality($bitmap): int
    {
        if (is_object($bitmap)) {
            if ($this->bit !== $bitmap->bit) {
                throw new RuntimeException("bitmap bit not equal");
            }
            $bPtr = $bitmap->bitmap;
        } else {
            if ($bitmap === null || $bitmap === '') {
                return $this->getCardinality();
            }
            $bPtr = $this->getTemporaryBitmap();
            $this->portableDeserialize($bPtr, self::tryBase64Decode($bitmap), false);
        }
        return $this->library->andnot_cardinality($this->bitmap, $bPtr);
    }

    /**
     * 获取迭代器
     * @param int $size foreach循环返回，每次返回的最大元素个数
     * @return Generator
     */
    public function iterate(int $size = 100): Generator
    {
        $buff = $this->newBuff($size);
        $ptr = FFI::addr($buff[0]);
        try {
            $iterator = $this->library->iterator_create($this->bitmap);
            $card = $this->getCardinality();
            $read = 0;
            while ($read < $card) {
                $length = $this->library->iterator_read($iterator, $ptr, $size);
                $ret = [];
                for ($i = 0; $i < $length; $i++) {
                    $ret[] = $buff[$i];
                }
                $read += $length;
                yield $ret;
            }
        } finally {
            if (isset($iterator) && is_object($iterator)) {
                $this->library->iterator_free($iterator);
            }
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
        $buff = $this->newBuff($card);
        $ptr = FFI::addr($buff[0]);
        $this->library->to_uint_array($this->bitmap, $ptr);
        $ret = [];
        for ($i = 0; $i < $card; $i++) {
            $ret[] = $buff[$i];
        }
        return $ret;
    }
}