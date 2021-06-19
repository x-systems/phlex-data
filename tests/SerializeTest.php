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

        $f1 = $m->addField('data1', ['type' => ['array', 'serialize' => 'serialize']]);
        $m->addField('data2', ['serialize' => 'base64']);
        $m->addField('data3', ['type' => ['array', 'serialize' => [
            Persistence\Sql::class => 'json',
        ]]]);
        $m->addField('data4', ['type' => ['string', 'codecs' => [
            Persistence\Array_::class => [
                'serialize' => 'serialize',
            ],
        ]]]);

        $serializer = $f1->getCodec()->getSerializer();

        $this->assertSame(Model\Field\Serializer::class, get_class($serializer));
        $this->assertSame('serialize', $this->getProtected($serializer, 'encodeFx'));

        $this->assertSame(
            [
                'data1' => serialize(['foo' => 'bar']),
                'data2' => base64_encode('abc'),
                'data3' => json_encode(['foo' => 'bar']),
                'data4' => 'no_changes',
            ],
            $this->db->encodeRow(
                $m,
                [
                    'data1' => ['foo' => 'bar'],
                    'data2' => 'abc',
                    'data3' => ['foo' => 'bar'],
                    'data4' => 'no_changes',
                ],
            )
        );

        $this->assertSame(
            [
                'data1' => ['foo' => 'bar'],
                'data2' => 'abc',
                'data3' => ['foo' => 'bar'],
                'data4' => 'no_changes',
            ],
            $this->db->decodeRow(
                $m,
                [
                    'data1' => serialize(['foo' => 'bar']),
                    'data2' => base64_encode('abc'),
                    'data3' => json_encode(['foo' => 'bar']),
                    'data4' => 'no_changes',
                ],
            )
        );
    }

    public function testOneWaySerialize()
    {
        $m = new Model($this->db, ['table' => 'job']);

        $f1 = $m->addField('data1', ['type' => ['string', 'serialize' => [
            Persistence\Sql::class => ['encodeFx' => 'md5'],
        ]]]);

        $serializer = $f1->getCodec()->getSerializer();

        $this->assertSame(Model\Field\Serializer::class, get_class($serializer));
        $this->assertSame('md5', $this->getProtected($serializer, 'encodeFx'));

        $this->assertSame(
            [
                'data1' => md5('test'),
            ],
            $this->db->encodeRow(
                $m,
                [
                    'data1' => 'test',
                ],
            )
        );

        $this->assertSame(
            [
                'data1' => md5('test'),
            ],
            $this->db->decodeRow(
                $m,
                [
                    'data1' => md5('test'),
                ],
            )
        );
    }

    public function testSerializeErrorJson(): void
    {
        $m = new Model($this->db, ['table' => 'job']);

        $m->addField('data', ['type' => ['array', 'serialize' => [
            Persistence\Sql::class => 'json',
        ]]]);

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
