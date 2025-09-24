<?php

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
     * @var array|string[]
     */
    protected static array $unSerializeTpl = [
        Library::BIT_32 => 'O:14:"Roaring\Bitmap":1:{s:9:"bitmapBit";i:32;}',
        Library::BIT_64 => 'O:14:"Roaring\Bitmap":1:{s:9:"bitmapBit";i:64;}',
    ];

    protected int $bit = 0;

    /**
     * 指向底层bitmap对象的指针
     * @var object|null
     */
    protected object|null $bitmap = null;

    /**
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
            $buf = FFI::new("char[" . ($length + 1) . "]");
            FFI::memcpy($buf, $bitmapBytes, $length);
            $buf[$length] = "\0";
            $ptr = FFI::addr($buf[0]);
            $this->bitmap = Library::getInstance($this->bit)->portable_deserialize($ptr, $length);
            if (is_null($this->bitmap)) {
                throw new RuntimeException("bitmap portable_deserialize failed");
            }
        }
    }

    final public function __destruct()
    {
        if ($this->bitmap) {
            Library::getInstance($this->bit)->free($this->bitmap);
            $this->bitmap = null;
        }
    }

    public function toBytes(): string
    {
        $size = Library::getInstance($this->bit)->portable_size_in_bytes($this->bitmap);
        $buf = FFI::new("char[$size]");
        $ptr = FFI::addr($buf[0]);
        $size = Library::getInstance($this->bit)->portable_serialize($this->bitmap, $ptr);
        return FFI::string($buf, $size);
    }

    final public function __serialize(): array
    {
        return [
            'bitmapBit' => $this->bit,
            'bitmapBytes' => base64_encode($this->toBytes()),
        ];
    }

    final public function __unserialize(array $data): void
    {
        $this->bit = $data['bitmapBit'];
        if (isset($data['bitmapBytes'])) {
            $data['bitmapBytes'] = base64_decode($data['bitmapBytes']);
            $length = strlen($data['bitmapBytes']);
            $buf = FFI::new("char[" . ($length + 1) . "]");
            FFI::memcpy($buf, $data['bitmapBytes'], $length);
            $buf[$length] = "\0";
            $ptr = FFI::addr($buf[0]);
            $this->bitmap = Library::getInstance($this->bit)->portable_deserialize($ptr, $length);
            if (is_null($this->bitmap)) {
                throw new RuntimeException("bitmap portable_deserialize failed");
            }
        }
    }

    final public function __clone()
    {
        $this->bitmap = Library::getInstance($this->bit)->copy($this->bitmap);
    }

    public function runOptimize(): bool
    {
        return Library::getInstance($this->bit)->run_optimize($this->bitmap);
    }

    public function clear(): static
    {
        Library::getInstance($this->bit)->clear($this->bitmap);
        return $this;
    }

    public function add(int $x): self
    {
        Library::getInstance($this->bit)->add($this->bitmap, $x);
        return $this;
    }

    /**
     * @param array|int[] $vals
     * @return self
     */
    public function addMany(array $vals): self
    {
        $card = count($vals);
        $buff = FFI::new(sprintf('uint%d_t[%d]', $this->bit, $card));
        for ($i = 0; $i < $card; $i++) {
            $buff[$i] = $vals[$i];
        }
        $ptr = FFI::addr($buff[0]);
        Library::getInstance($this->bit)->add_many($this->bitmap, $card, $ptr);
        return $this;
    }

    public function addChecked(int $x): bool
    {
        return Library::getInstance($this->bit)->add_checked($this->bitmap, $x);
    }

    public function addRange(int $min, int $max): static
    {
        Library::getInstance($this->bit)->add_range($this->bitmap, $min, $max);
        return $this;
    }

    /**
     * @param int $x
     * @return $this
     */
    public function remove(int $x): self
    {
        Library::getInstance($this->bit)->remove($this->bitmap, $x);
        return $this;
    }

    /**
     * @param array|int[] $x
     * @return self
     */
    public function removeMany(array $x): self
    {
        $card = count($x);
        $buff = FFI::new(sprintf('uint%d_t[%d]', $this->bit, $card));
        for ($i = 0; $i < $card; $i++) {
            $buff[$i] = $x[$i];
        }
        $ptr = FFI::addr($buff[0]);
        Library::getInstance($this->bit)->remove_many($this->bitmap, $card, $ptr);
        return $this;
    }

    public function removeChecked(int $x): bool
    {
        return Library::getInstance($this->bit)->remove_checked($this->bitmap, $x);
    }

    public function removeRange(int $min, int $max): void
    {
        Library::getInstance($this->bit)->remove_range($this->bitmap, $min, $max);
    }

    public function getCardinality(): int
    {
        return Library::getInstance($this->bit)->get_cardinality($this->bitmap);
    }

    public function rangeCardinality(int $range_start, int $range_end): int
    {
        return Library::getInstance($this->bit)->range_cardinality($this->bitmap, $range_start, $range_end);
    }

    public function contains(int $val): bool
    {
        return Library::getInstance($this->bit)->contains($this->bitmap, $val);
    }

    public function containsRange(int $range_start, int $range_end): bool
    {
        return Library::getInstance($this->bit)->contains_range($this->bitmap, $range_start, $range_end);
    }

    public function rank(int $x): int
    {
        return Library::getInstance($this->bit)->rank($this->bitmap, $x);
    }

    public function select(int $rank): ?int
    {
        $val = FFI::new(sprintf('uint%d_t', $this->bit));
        $ptr = FFI::addr($val);
        $ok = Library::getInstance($this->bit)->select($this->bitmap, $rank, $ptr);
        if ($ok) {
            return $val->cdata;
        }
        return null;
    }

    public function minimum(): int
    {
        if ($this->bit === Library::BIT_32) {
            return Library::getInstance($this->bit)->minimum($this->bitmap);
        }
        //整型数 int 的字长和平台有关， PHP 不支持无符号的 int， 所以当bitmap为空时，只能用 PHP_INT_MAX
        return Library::getInstance($this->bit)->is_empty($this->bitmap) ? PHP_INT_MAX : Library::getInstance($this->bit)->minimum($this->bitmap);
    }

    public function maximum(): int
    {
        return Library::getInstance($this->bit)->maximum($this->bitmap);
    }

    public function equals(Bitmap $bitmap): bool
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
        }
        return Library::getInstance($this->bit)->equals($this->bitmap, $bitmap->bitmap);
    }

    public function intersect(Bitmap $bitmap): bool
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
        }
        return Library::getInstance($this->bit)->intersect($this->bitmap, $bitmap->bitmap);
    }

    public function isEmpty(): bool
    {
        return Library::getInstance($this->bit)->is_empty($this->bitmap);
    }

    /**
     * @param Bitmap $bitmap
     * @return Bitmap
     */
    public function or(Bitmap $bitmap): Bitmap
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
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
     * @param Bitmap $bitmap
     * @return void
     */
    public function orInPlace(Bitmap $bitmap): void
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
        }
        Library::getInstance($this->bit)->or_inplace($this->bitmap, $bitmap->bitmap);
    }

    /**
     * @param Bitmap $bitmap
     * @return int
     */
    public function orCardinality(Bitmap $bitmap): int
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
        }
        return Library::getInstance($this->bit)->or_cardinality($this->bitmap, $bitmap->bitmap);
    }

    /**
     * @param Bitmap $bitmap
     * @return Bitmap
     */
    public function xOr(Bitmap $bitmap): Bitmap
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
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
     * @param Bitmap $bitmap
     * @return void
     */
    public function xOrInPlace(Bitmap $bitmap): void
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
        }
        Library::getInstance($this->bit)->xor_inplace($this->bitmap, $bitmap->bitmap);
    }

    /**
     * @param Bitmap $bitmap
     * @return int
     */
    public function xOrCardinality(Bitmap $bitmap): int
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
        }
        return Library::getInstance($this->bit)->xor_cardinality($this->bitmap, $bitmap->bitmap);
    }

    /**
     * @param Bitmap $bitmap
     * @return Bitmap
     */
    public function and(Bitmap $bitmap): Bitmap
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
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
     * @param Bitmap $bitmap
     * @return void
     */
    public function andInPlace(Bitmap $bitmap): void
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
        }
        Library::getInstance($this->bit)->and_inplace($this->bitmap, $bitmap->bitmap);
    }

    /**
     * @param Bitmap $bitmap
     * @return int
     */
    public function andCardinality(Bitmap $bitmap): int
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
        }
        return Library::getInstance($this->bit)->and_cardinality($this->bitmap, $bitmap->bitmap);
    }

    /**
     * @param Bitmap $bitmap
     * @return Bitmap
     */
    public function andNot(Bitmap $bitmap): Bitmap
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
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
     * @param Bitmap $bitmap
     * @return void
     */
    public function andNotInPlace(Bitmap $bitmap): void
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
        }
        Library::getInstance($this->bit)->andnot_inplace($this->bitmap, $bitmap->bitmap);
    }

    /**
     * @param Bitmap $bitmap
     * @return int
     */
    public function andNotCardinality(Bitmap $bitmap): int
    {
        if ($this->bit !== $bitmap->bit) {
            throw new RuntimeException("bitmap bit not equal");
        }
        return Library::getInstance($this->bit)->andnot_cardinality($this->bitmap, $bitmap->bitmap);
    }

    public function iterate(int $size = 100): Generator
    {
        $buff = FFI::new(sprintf('uint%d_t[%d]', $this->bit, $size));
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
     * @return array
     */
    public function toArray(): array
    {
        $card = $this->getCardinality();
        if ($card === 0) {
            return [];
        }
        $buff = FFI::new(sprintf('uint%d_t[%d]', $this->bit, $card));
        $ptr = FFI::addr($buff[0]);
        Library::getInstance($this->bit)->to_uint_array($this->bitmap, $ptr);
        $ret = [];
        for ($i = 0; $i < $card; $i++) {
            $ret[] = $buff[$i];
        }
        return $ret;
    }
}