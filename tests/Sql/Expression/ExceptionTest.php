<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Sql\Expression;

use Phlex\Core\Exception;
use Phlex\Core\PHPUnit;
use Phlex\Data\Persistence\Sql;

class ExceptionTest extends PHPUnit\TestCase
{
    public function testException1()
    {
        $this->expectException(Exception::class);
        $e = new Sql\Expression('hello, [world]');
        $e->render();
    }

    public function testException2()
    {
        try {
            $e = new Sql\Expression('hello, [world]');
            $e->render();
        } catch (Exception $e) {
            $this->assertSame(
                'Expression could not render tag',
                $e->getMessage()
            );

            $this->assertSame(
                'world',
                $e->getParams()['tag']
            );
        }
    }
}
