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

use Roaring\Bitmap;
use Roaring\Library;

class Bitmap64Test extends BitmapTestAbstract
{
    function newBp(): Bitmap
    {
        return new Bitmap(Library::BIT_64);
    }

    function intMax(): int
    {
        //整型数 int 的字长和平台有关， PHP 不支持无符号的 int
        return PHP_INT_MAX;
    }
}