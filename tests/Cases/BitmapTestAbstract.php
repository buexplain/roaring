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

namespace RoaringTest\Cases;

use PHPUnit\Framework\TestCase;
use Roaring\Bitmap;

abstract class BitmapTestAbstract extends TestCase
{
    abstract function newBp(): Bitmap;

    abstract function intMax(): int;

    /**
     * composer test -- --filter=testSerialize
     * @return void
     */
    public function testSerialize(): void
    {
        $b = $this->newBp();
        $bStr = serialize($b);
        $b2 = unserialize($bStr);
        $b2str = serialize($b2);
        $this->assertEquals($bStr, $b2str, '反序列化失败');
    }

    /**
     * composer test -- --filter=testClone
     * @return void
     */
    public function testClone()
    {
        $b = $this->newBp();
        $b2 = clone $b;
        $this->assertEquals(serialize($b), serialize($b2));
    }

    /**
     * composer test -- --filter=testRunOptimize
     * @return void
     */
    public function testRunOptimize()
    {
        $b = $this->newBp();
        $this->assertFalse($b->runOptimize());
    }

    /**
     * composer test -- --filter=testClear
     * @return void
     */
    public function testClear()
    {
        $b = $this->newBp();
        $b->add(1);
        $b->clear();
        $this->assertEquals(0, $b->getCardinality());
    }

    /**
     * composer test -- --filter=testAdd
     * @return void
     */
    public function testAdd()
    {
        $b = $this->newBp();
        $b->add(1);
        $this->assertEquals(1, $b->getCardinality());
    }

    /**
     * composer test -- --filter=testAddMany
     * @return void
     */
    public function testAddMany()
    {
        $b = $this->newBp();
        $b->addMany([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        $this->assertEquals(10, $b->getCardinality());
    }

    /**
     * composer test -- --filter=testAddRange
     * @return void
     */
    public function addChecked(): void
    {
        $b = $this->newBp();
        $this->assertTrue($b->addChecked(1));
        $this->assertFalse($b->addChecked(1));
    }

    /**
     * composer test -- --filter=testAddRange
     * @return void
     */
    public function testAddRange()
    {
        $b = $this->newBp();
        $b->addRange(1, 5);
        $this->assertEquals(4, $b->getCardinality());
    }

    /**
     * composer test -- --filter=testRemove
     * @return void
     */
    public function testRemove()
    {
        $b = $this->newBp();
        $b->addRange(1, 5);
        $b->remove(1);
        $this->assertEquals(3, $b->getCardinality());
    }

    /**
     * composer test -- --filter=testRemoveMany
     * @return void
     */
    public function testRemoveMany()
    {
        $b = $this->newBp();
        $b->addRange(1, 5);
        $b->removeMany([1, 2, 3]);
        $this->assertEquals(1, $b->getCardinality());
    }

    /**
     * composer test -- --filter=testRemoveChecked
     * @return void
     */
    public function testRemoveChecked()
    {
        $b = $this->newBp();
        $b->add(1);
        $this->assertTrue($b->removeChecked(1));
        $this->assertFalse($b->removeChecked(1));
    }

    /**
     * composer test -- --filter=testRemoveRange
     * @return void
     */
    public function testRemoveRange()
    {
        $b = $this->newBp();
        $b->addRange(1, 6);
        $b->removeRange(1, 5);
        $this->assertEquals(1, $b->getCardinality());
    }

    /**
     * composer test -- --filter=testGetCardinality
     * @return void
     */
    public function testGetCardinality()
    {
        $b = $this->newBp();
        $this->assertEquals(0, $b->getCardinality());
        $b->add(1);
        $this->assertEquals(1, $b->getCardinality());
    }

    /**
     * composer test -- --filter=testRangeCardinality
     * @return void
     */
    public function testRangeCardinality()
    {
        $b = $this->newBp();
        $this->assertEquals(0, $b->rangeCardinality(1, 5));
        $b->addRange(1, 10);
        $this->assertEquals(4, $b->rangeCardinality(0, 5));
        $this->assertEquals(1, $b->rangeCardinality(1, 2));
    }

    /**
     * composer test -- --filter=testContains
     * @return void
     */
    public function testContains()
    {
        $b = $this->newBp();
        $this->assertFalse($b->contains(1));
        $b->add(1);
        $this->assertTrue($b->contains(1));
    }

    /**
     * composer test -- --filter=testContainsRange
     * @return void
     */
    public function testContainsRange()
    {
        $b = $this->newBp();
        $this->assertFalse($b->containsRange(1, 5));
        $b->addRange(1, 5);
        $this->assertTrue($b->containsRange(1, 5));
        $b->remove(2);
        $this->assertFalse($b->containsRange(1, 5));
    }

    /**
     * composer test -- --filter=testRank
     * @return void
     */
    public function testRank()
    {
        $b = $this->newBp();
        $this->assertEquals(0, $b->rank(0));
        $this->assertEquals(0, $b->rank(1));
        $b->addRange(1, 5);
        $this->assertEquals(3, $b->rank(3));
        $this->assertEquals(4, $b->rank(6));
    }

    /**
     * composer test -- --filter=testSelect
     * @return void
     */
    public function testSelect()
    {
        $b = $this->newBp();
        $this->assertNull($b->select(0));
        $b->addRange(1, 5);
        $this->assertEquals(1, $b->select(0));
        $this->assertEquals(3, $b->select(2));
        $this->assertEquals(4, $b->select(3));
        $this->assertNull($b->select(4));
    }

    /**
     * composer test -- --filter=testMinimum
     * @return void
     */
    public function testMinimum()
    {
        $b = $this->newBp();
        $this->assertEquals($this->intMax(), $b->minimum());
        $b->add($this->intMax());
        $this->assertEquals($this->intMax(), $b->minimum());
        $b->add(1);
        $this->assertEquals(1, $b->minimum());
    }

    /**
     * composer test -- --filter=testMaximum
     * @return void
     */
    public function testMaximum()
    {
        $b = $this->newBp();
        $this->assertEquals(0, $b->maximum());
        $b->add(1);
        $this->assertEquals(1, $b->maximum());
        $b->add($this->intMax());
        $this->assertEquals($this->intMax(), $b->maximum());
    }

    /**
     * composer test -- --filter=testEquals
     * @return void
     */
    public function testEquals()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $this->assertTrue($b->equals($a));
        $b->add(1);
        $this->assertFalse($a->equals($b));
    }

    /**
     * composer test -- --filter=testIntersect
     * @return void
     */
    public function testIntersect()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $this->assertFalse($b->intersect($a));
        $a->addRange(1, 5);
        $this->assertFalse($a->intersect($b));
        $b->addRange(2, 8);
        $this->assertTrue($b->intersect($a));
        $this->assertTrue($a->intersect($b));
    }

    /**
     * composer test -- --filter=testIsEmpty
     * @return void
     */
    public function testIsEmpty()
    {
        $a = $this->newBp();
        $this->assertTrue($a->isEmpty());
        $a->add(1);
        $this->assertFalse($a->isEmpty());
    }

    /**
     * composer test -- --filter=testOr
     * @return void
     */
    public function testOr()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $a->addMany([1, 2]);
        $b->addMany([2, 3]);
        $c = $a->or($b);
        $this->assertEquals([1, 2, 3], $c->toArray());
    }

    /**
     * composer test -- --filter=testOrInPlace
     * @return void
     */
    public function testOrInPlace()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $a->addMany([1, 2]);
        $b->addMany([2, 3]);
        $a->orInPlace($b);
        $this->assertEquals([1, 2, 3], $a->toArray());
    }

    /**
     * composer test -- --filter=testOrCardinality
     * @return void
     */
    public function testOrCardinality()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $a->addMany([1, 2]);
        $b->addMany([2, 3]);
        $this->assertEquals(count([1, 2, 3]), $a->orCardinality($b));
    }

    /**
     * composer test -- --filter=testXor
     * @return void
     */
    public function testXor()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $a->addMany([1, 2, 3]);
        $b->addMany([3, 4, 5]);
        $c = $a->xOr($b);
        $this->assertEquals([1, 2, 4, 5], $c->toArray());
    }

    /**
     * composer test -- --filter=testXorInPlace
     * @return void
     */
    public function testXorInPlace()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $a->addMany([1, 2, 3]);
        $b->addMany([3, 4, 5]);
        $a->xOrInPlace($b);
        $this->assertEquals([1, 2, 4, 5], $a->toArray());
    }

    /**
     * composer test -- --filter=testXorCardinality
     * @return void
     */
    public function testXorCardinality()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $a->addMany([1, 2, 3]);
        $b->addMany([3, 4, 5]);
        $this->assertEquals(count([1, 2, 4, 5]), $a->xOrCardinality($b));
    }

    /**
     * composer test -- --filter=testAnd
     * @return void
     */
    public function testAnd()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $a->addMany([1, 2, 3]);
        $b->addMany([2, 3, 4]);
        $c = $a->and($b);
        $this->assertEquals([2, 3], $c->toArray());
    }

    /**
     * composer test -- --filter=testAndInPlace
     * @return void
     */
    public function testAndInPlace()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $a->addMany([1, 2, 3]);
        $b->addMany([2, 3, 4]);
        $a->andInPlace($b);
        $this->assertEquals([2, 3], $a->toArray());
    }

    /**
     * composer test -- --filter=testAndCardinality
     * @return void
     */
    public function testAndCardinality()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $a->addMany([1, 2, 3]);
        $b->addMany([2, 3, 4]);
        $this->assertEquals(count([2, 3]), $a->andCardinality($b));
    }

    /**
     * composer test -- --filter=testAndNot
     * @return void
     */
    public function testAndNot()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $a->addMany([1, 2, 3]);
        $b->addMany([1, 3, 4]);
        $c = $a->andNot($b);
        $this->assertEquals([2], $c->toArray());
    }

    /**
     * composer test -- --filter=testAndNotInPlace
     * @return void
     */
    public function testAndNotInPlace()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $a->addMany([1, 2, 3]);
        $b->addMany([1, 3, 4]);
        $a->andNotInPlace($b);
        $this->assertEquals([2], $a->toArray());
    }

    /**
     * composer test -- --filter=testAndNotCardinality
     * @return void
     */
    public function testAndNotCardinality()
    {
        $a = $this->newBp();
        $b = $this->newBp();
        $a->addMany([1, 2, 3]);
        $b->addMany([1, 3, 4]);
        $this->assertEquals(count([2]), $a->andNotCardinality($b));
    }

    /**
     * composer test -- --filter=testIterate
     * @return void
     */
    public function testIterate()
    {
        $a = $this->newBp();
        $a->addRange(0, 100);
        $generator = $a->iterate(10);
        foreach ($generator as $v) {
            $this->assertCount(10, $v);
        }
    }
}