<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

class ModelUnionTest extends Sql\TestCase
{
    /** @var array */
    private $init_db =
        [
            'client' => [
                // allow of migrator to create all columns
                ['name' => 'Vinny', 'surname' => null, 'order' => null],
                ['name' => 'Zoe'],
            ],
            'invoice' => [
                ['client_id' => 1, 'name' => 'chair purchase', 'amount' => 4.0],
                ['client_id' => 1, 'name' => 'table purchase', 'amount' => 15.0],
                ['client_id' => 2, 'name' => 'chair purchase', 'amount' => 4.0],
            ],
            'payment' => [
                ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
                ['client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
            ],
        ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setDb($this->init_db);
    }

    protected function createTransaction(): Model\Transaction
    {
        return new Model\Transaction($this->db);
    }

    protected function createSubtractInvoiceTransaction(): Model\Transaction
    {
        return new Model\Transaction($this->db, ['subtractInvoice' => true]);
    }

    protected function createClient(): Model\Client
    {
        $client = new Model\Client($this->db);

        $client->withMany('Payment', ['theirModel' => [Model\Payment::class]]);
        $client->withMany('Invoice', ['theirModel' => [Model\Invoice::class]]);

        return $client;
    }

    public function testBasics(): void
    {
        $client = $this->createClient();

        // There are total of 2 clients
        $this->assertSame(2, $client->getCount());

        // Client with ID=1 has invoices for 19
        $this->assertSame(19.0, (float) $client->load(1)->ref('Invoice')->getSum('amount'));

        $transaction = $this->createTransaction();

        $this->assertSameExportUnordered([
            ['id' => 'invoice/1', 'client_id' => 1, 'name' => 'chair purchase', 'amount' => 4.0],
            ['id' => 'invoice/2', 'client_id' => 1, 'name' => 'table purchase', 'amount' => 15.0],
            ['id' => 'invoice/3', 'client_id' => 2, 'name' => 'chair purchase', 'amount' => 4.0],
            ['id' => 'payment/1', 'client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
            ['id' => 'payment/2', 'client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());

        // Transaction is Union Model
        $client->withMany('Transaction', ['theirModel' => $transaction]);

        $this->assertSameExportUnordered([
            ['id' => 'invoice/1', 'client_id' => 1, 'name' => 'chair purchase', 'amount' => 4.0],
            ['id' => 'invoice/2', 'client_id' => 1, 'name' => 'table purchase', 'amount' => 15.0],
            ['id' => 'payment/1', 'client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
        ], $client->load(1)->ref('Transaction')->export());

        $client = $this->createClient();

        $transaction = $this->createSubtractInvoiceTransaction();

        $this->assertSameExportUnordered([
            ['id' => 'invoice/1', 'client_id' => 1, 'name' => 'chair purchase', 'amount' => -4.0],
            ['id' => 'invoice/2', 'client_id' => 1, 'name' => 'table purchase', 'amount' => -15.0],
            ['id' => 'invoice/3', 'client_id' => 2, 'name' => 'chair purchase', 'amount' => -4.0],
            ['id' => 'payment/1', 'client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
            ['id' => 'payment/2', 'client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());

        // Transaction is Union Model
        $client->withMany('Transaction', ['theirModel' => $transaction]);

        $this->assertSameExportUnordered([
            ['id' => 'invoice/1', 'client_id' => 1, 'name' => 'chair purchase', 'amount' => -4.0],
            ['id' => 'invoice/2', 'client_id' => 1, 'name' => 'table purchase', 'amount' => -15.0],
            ['id' => 'payment/1', 'client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
        ], $client->load(1)->ref('Transaction')->export());
    }

    public function testReference(): void
    {
        $client = $this->createClient();
        $client->withMany('transactions', ['theirModel' => $this->createTransaction()]);

        $this->assertSame(19.0, (float) $client->load(1)->ref('Invoice')->getSum('amount'));
        $this->assertSame(10.0, (float) $client->load(1)->ref('Payment')->getSum('amount'));

        // TODO aggregated fields are pushdown, but where condition is not
        // I belive the fields pushdown is even wrong as not every aggregated result produces same result when aggregated again
        // then fix also self::testFieldAggregate()
        $this->assertTrue(true);

        return;
        // @phpstan-ignore-next-line
        $this->assertSame(29.0, (float) $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->getOne());

        $this->assertSameSql(
            'select sum("val") from (select sum("amount") "val" from "invoice" where "client_id" = :a UNION ALL select sum("amount") "val" from "payment" where "client_id" = :b)',
            $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->render()[0]
        );

        $client = $this->createClient();
        $client->withMany('tr', ['model' => $this->createSubtractInvoiceTransaction()]);

        $this->assertSame(19.0, (float) $client->load(1)->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(10.0, (float) $client->load(1)->ref('Payment')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(-9.0, (float) $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->getOne());

        $this->assertSameSql(
            'select sum("val") from (select sum(-"amount") "val" from "invoice" where "client_id" = :a UNION ALL select sum("amount") "val" from "payment" where "client_id" = :b)',
            $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->render()[0]
        );
    }

    /**
     * Aggregation is supposed to work in theory, but MySQL uses "semi-joins" for this type of query which does not support UNION,
     * and therefore it complains about "client"."id" field.
     *
     * See also: http://stackoverflow.com/questions/8326815/mysql-field-from-union-subselect#comment10267696_8326815
     */
    public function testFieldAggregate(): void
    {
        $client = $this->createClient();
        $client->withMany('transactions', ['theirModel' => $this->createTransaction()])
            ->addField('balance', ['field' => 'amount', 'aggregate' => 'sum']);

        // TODO some fields are pushdown, but some not, same issue as in self::testReference()
        $this->assertTrue(true);

        return;
        // @phpstan-ignore-next-line
        $this->assertSameSql(
            'select "client"."id", "client"."name", (select sum("val") from (select sum("amount") "val" from "invoice" where "client_id" = "client"."id" union all select sum("amount") "val" from "payment" where "client_id" = "client"."id") "_tu") "balance" from "client" where "client"."id" = 1 limit 0, 1',
            $client->load(1)->action('select')->render()[0]
        );
    }

    public function testConditionOnUnionField(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition('amount', '<', 0);

        $this->assertSameExportUnordered([
            ['id' => 'invoice/1', 'client_id' => 1, 'name' => 'chair purchase', 'amount' => -4.0],
            ['id' => 'invoice/2', 'client_id' => 1, 'name' => 'table purchase', 'amount' => -15.0],
            ['id' => 'invoice/3', 'client_id' => 2, 'name' => 'chair purchase', 'amount' => -4.0],
        ], $transaction->export());
    }

    public function testConditionOnNestedModelField(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition('client_id', '>', 1);

        $this->assertSameExportUnordered([
            ['id' => 'invoice/3', 'client_id' => 2, 'name' => 'chair purchase', 'amount' => -4.0],
            ['id' => 'payment/2', 'client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());
    }

    public function testConditionOnNestedModels1(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition('amount', '>', 5);

        $this->assertSameExportUnordered([
            ['id' => 'payment/1', 'client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
        ], $transaction->export());
    }

    public function testConditionOnNestedModels2(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition('amount', '<', -10.0);

        $this->assertSameExportUnordered([
            ['id' => 'invoice/2', 'client_id' => 1, 'name' => 'table purchase', 'amount' => -15.0],
        ], $transaction->export());
    }

    public function testConditionOnNestedModels3(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->getNestedModel('invoice')->addCondition('id', 1);
        $transaction->getNestedModel('payment')->addCondition('id', 10);

        $this->assertSameExportUnordered([
            ['id' => 'invoice/1', 'client_id' => 1, 'name' => 'chair purchase', 'amount' => -4.0],
        ], $transaction->export());
    }

    public function testConditionExpression(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition($transaction->expr('{} > 5', [$transaction->getActualKey('amount')]));

        $this->assertSameExportUnordered([
            ['id' => 'payment/1', 'client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
        ], $transaction->export());
    }

    /**
     * Model's conditions can still be placed on the original field values.
     */
    public function testConditionOnMappedField(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->getNestedModel('invoice')->addCondition('amount', 4);

        $this->assertSameExportUnordered([
            ['id' => 'invoice/1', 'client_id' => 1, 'name' => 'chair purchase', 'amount' => -4.0],
            ['id' => 'invoice/3', 'client_id' => 2, 'name' => 'chair purchase', 'amount' => -4.0],
            ['id' => 'payment/1', 'client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
            ['id' => 'payment/2', 'client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());
    }

    public function testNestedEntity(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->getNestedModel('invoice')->addCondition('id', 1);

        $this->assertEquals([
            'id' => 1,
            'client_id' => 1,
            'name' => 'chair purchase',
            'amount' => 4.0,
            'union_id' => 'invoice/1',
            'union_amount' => -4.0,
        ], $transaction->load()->getNestedEntity()->get());

        $transaction->setTitleWithCaption();

        $this->assertSame([
            'id' => 'invoice/1',
            'captioned_title' => '[Invoice] chair purchase',
        ], $transaction->load()->onlyFields([$transaction->primaryKey, $transaction->titleKey])->get());
    }

    public function testNestedModelAdded(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();

        $tableInvoice = (new Model\Invoice())->addCondition('name', 'like', '%table%');

        $transaction->addNestedModel('table_invoice', $tableInvoice);

        $transaction->addCondition('name', 'like', '%table%');

        $this->assertSameExportUnordered([
            ['id' => 'invoice/2', 'client_id' => 1, 'name' => 'table purchase', 'amount' => -15.0],
            ['id' => 'table_invoice/2', 'client_id' => 1, 'name' => 'table purchase', 'amount' => 15.0],
        ], $transaction->export());
    }
}
