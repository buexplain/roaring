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
use Roaring\Bp32;

class Bp32Test extends TestCase
{
    /**
     * 测试实例化
     * composer test -- --filter=testNew
     * @return void
     */
    public function testNew()
    {
        $b = new Bp32();
        $this->assertInstanceOf(Bp32::class, $b, '实例化失败');
    }
}