<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

class SerializeTest extends Sql\TestCase
{
    public function testSerializerResolution()
    {
        $serializer = Model\Field\Serializer::resolve('json');

        $this->assertSame(Model\Field\Serializer::class, get_class($serializer));
        $this->assertSame([Model\Field\Codec::class, 'jsonEncode'], $this->getProtected($serializer, 'encodeFx'));
    }

    public function testBasicSerialize()
    {
        $m = new Model($this->db, ['table' => 'job']);

        $f = $m->addField('data', ['serialize' => 'serialize']);

        $serializer = $f->getSerializer();

        $this->assertSame(Model\Field\Serializer::class, get_class($serializer));
        $this->assertSame('serialize', $this->getProtected($serializer, 'encodeFx'));

        $this->assertSame(
            ['data' => 'a:1:{s:3:"foo";s:3:"bar";}'],
            $this->db->encodeRow(
                $m,
                ['data' => ['foo' => 'bar']]
            )
        );
        $this->assertSame(
            ['data' => ['foo' => 'bar']],
            $this->db->decodeRow(
                $m,
                ['data' => 'a:1:{s:3:"foo";s:3:"bar";}']
            )
        );

        $f->serialize = 'json';
        $f->type = 'array';
        $this->assertSame(
            ['data' => '{"foo":"bar"}'],
            $this->db->encodeRow(
                $m,
                ['data' => ['foo' => 'bar']]
            )
        );
        $this->assertSame(
            ['data' => ['foo' => 'bar']],
            $this->db->decodeRow(
                $m,
                ['data' => '{"foo":"bar"}']
            )
        );
    }

    public function testSerializeErrorJson(): void
    {
        $m = new Model($this->db, ['table' => 'job']);

        $m->addField('data', ['type' => 'array', 'serialize' => [
            Persistence\Sql::class => 'json',
        ]]);

        $this->expectException(\JsonException::class);
        $this->db->decodeRow($m, ['data' => '{"foo":"bar" OPS']);
    }

    public function testSerializeErrorJson2(): void
    {
        $m = new Model($this->db, ['table' => 'job']);

        $m->addField('data', ['type' => 'array', 'serialize' => 'json']);

        // recursive array - json can't encode that
        $dbData = [];
        $dbData[] = &$dbData;

        $this->expectException(\JsonException::class);
        $this->db->encodeRow($m, ['data' => ['foo' => 'bar', 'recursive' => $dbData]]);
    }

    /*
     * THIS IS NOT POSSIBLE BECAUSE unserialize() produces error
     * and not exception
     */

    /*
    public function testSerializeErrorSerialize(): void
    {
        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($this->db, 'job');

        $f = $m->addField('data', ['serialize' => 'serialize']);
        $this->expectException(Exception::class);
        $this->assertEquals(
            ['data' => ['foo' => 'bar']]
            , $db->decodeRow($m,
            ['data' => 'a:1:{s:3:"foo";s:3:"bar"; OPS']
        ));
    }
     */
}
