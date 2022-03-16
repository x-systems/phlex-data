<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Model;
use Phlex\Data\Persistence;
use Phlex\Data\Tests\Model\Person;

class PersistenceCsvTest extends \Phlex\Core\PHPUnit\TestCase
{
    /** @var \SplFileObject */
    protected $file;

    /** @var \SplFileObject */
    protected $file2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = $this->makeCsvFileObject();
        $this->file2 = $this->makeCsvFileObject();
    }

    protected function makeCsvFileObject()
    {
        $fileObject = new \SplFileObject('php://memory', 'w+');

        $fileObject->setFlags(
            \SplFileObject::READ_CSV |
            \SplFileObject::SKIP_EMPTY |
            \SplFileObject::DROP_NEW_LINE
        );

        // see https://bugs.php.net/bug.php?id=65601
        if (\PHP_MAJOR_VERSION < 8) {
            $fileObject->setFlags($fileObject->getFlags() | \SplFileObject::READ_AHEAD);
        }

        return $fileObject;
    }

    protected function setDb(array $data): void
    {
        $this->file->ftruncate(0);
        $this->file->fputcsv(array_keys(reset($data)));
        foreach ($data as $row) {
            $this->file->fputcsv($row);
        }

        $this->file2->ftruncate(0);
    }

    protected function getDb(): array
    {
        $this->file->fseek(0);
        $keys = $this->file->fgetcsv();
        $data = [];
        while ($row = $this->file->fgetcsv()) {
            $data[] = array_combine($keys, $row);
        }

        return $data;
    }

    public function testTestcase(): void
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);
        $data2 = $this->getDb();
        $this->assertSame($data, $data2);
    }

    public function testBaseData(): void
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);

        $p = new Persistence\Csv($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m = $m->loadAny();

        $this->assertSame('John', $m->get('name'));
        $this->assertSame('Smith', $m->get('surname'));
    }

    public function testLoadAny()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);

        $p = new Persistence\Csv($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $m = $m->loadAny();
        $this->assertSame('John', $m->get('name'));
        $this->assertSame('Smith', $m->get('surname'));
    }

    public function testLoadAnyException(): void
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);

        $p = new Persistence\Csv($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $mm = $m->load(2);

        $this->assertSame('Sarah', $mm->get('name'));
        $this->assertSame('Jones', $mm->get('surname'));

        $mm = $m->tryLoad(3);
        $this->assertFalse($mm->isLoaded());
    }

    public function testPersistenceCopy()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
            ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
        ];

        $this->setDb($data);

        $p = new Persistence\Csv($this->file);
        $p2 = new Persistence\Csv($this->file2);

        $m = new Person($p);

        $m2 = $m->withPersistence($p2);

        foreach ($m as $row) {
            $m2->insert($row->get());
        }

        $this->file->fseek(0);
        $this->file2->fseek(0);
        $this->assertSame(
            $this->file->fread(5000),
            $this->file2->fread(5000)
        );
    }

    public function testExport()
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];
        $this->setDb($data);

        $p = new Persistence\Csv($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $this->assertSame([
            1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            2 => ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        $this->assertSame([
            ['surname' => 'Smith'],
            ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }
}
