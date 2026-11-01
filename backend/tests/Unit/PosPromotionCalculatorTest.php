<?php

namespace Tests\Unit;

use Company\Pos\Tests\PromotionContract;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3).'/shared/tests/PromotionContract.php';

class PosPromotionCalculatorTest extends TestCase
{
    use PromotionContract;
}
