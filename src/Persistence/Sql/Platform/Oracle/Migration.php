<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Platform\Oracle;

use Doctrine\DBAL\Schema\Column;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

class Migration extends Persistence\Sql\Migration
{
    public function create(): Persistence\Sql\Migration
    {
        parent::create();

        $this->persistence->execute(new Persistence\Sql\Expression(
            <<<'EOT'
                begin
                    execute immediate [];
                end;
                EOT,
            [
                new Persistence\Sql\Expression(
                    <<<'EOT'
                        create or replace trigger {table_ai_trigger_before}
                            before insert on {table}
                            for each row
                            when (new."id" is null)
                        declare
                            last_id {table}."id"%type;
                        begin
                            select nvl(max("id"), 0) into last_id from {table};
                            :new."id" := last_id + 1;
                        end;
                        EOT,
                    [
                        'table' => $this->table->getName(),
                        'table_ai_trigger_before' => $this->table->getName() . '__aitb',
                    ]
                ),
            ]
        ));

        return $this;
    }

    public function drop(): Persistence\Sql\Migration
    {
        // drop trigger if exists
        // see https://stackoverflow.com/questions/1799128/oracle-if-table-exists
        $this->persistence->execute(new Persistence\Sql\Expression(
            <<<'EOT'
                begin
                    execute immediate [];
                exception
                    when others then
                        if sqlcode != -4080 then
                            raise;
                        end if;
                end;
                EOT,
            [
                new Persistence\Sql\Expression(
                    'drop trigger {table_ai_trigger_before}',
                    [
                        'table_ai_trigger_before' => $this->table->getName() . '__aitb',
                    ]
                ),
            ]
        ));

        return parent::drop();
    }

    public function addColumn(Model\Field $field): Column
    {
        $column = parent::addColumn($field);

        if ($field->isPrimaryKey()) {
            $column->setAutoincrement(false);
        }

        return $column;
    }
}
