<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\ActsAsOrgUser;

abstract class TestCase extends BaseTestCase
{
    use ActsAsOrgUser;
}
