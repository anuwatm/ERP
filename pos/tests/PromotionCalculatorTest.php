<?php

namespace Pos\Tests;

use Company\Pos\Tests\PromotionContract;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/shared/tests/PromotionContract.php';

class PromotionCalculatorTest extends TestCase
{
    use PromotionContract;
}
