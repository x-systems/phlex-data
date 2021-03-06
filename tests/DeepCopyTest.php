<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Phlex\Data\Model;
use Phlex\Data\Util\DeepCopy;
use Phlex\Data\Util\DeepCopyException;

class DcClient extends Model
{
    public $table = 'client';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addField('name');

        $this->withMany('Invoices', ['theirModel' => [DcInvoice::class]]);
        $this->withMany('Quotes', ['theirModel' => [DcQuote::class]]);
        $this->withMany('Payments', ['theirModel' => [DcPayment::class]]);
    }
}

class DcInvoice extends Model
{
    public $table = 'invoice';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->hasOne('client', ['theirModel' => [DcClient::class]]);

        $this->withMany('Lines', ['theirModel' => [DcInvoiceLine::class], 'theirKey' => 'parent_id'])
            ->addField('total', ['aggregate' => 'sum', 'field' => 'total']);

        $this->withMany('Payments', ['theirModel' => [DcPayment::class]])
            ->addField('paid', ['aggregate' => 'sum', 'field' => 'amount']);

        $this->addExpression('due', '[total]-[paid]');

        $this->addField('ref');

        $this->addField('is_paid', ['type' => 'boolean', 'default' => false]);

        $this->onHookShort(DeepCopy::HOOK_AFTER_COPY, function ($s) {
            if (get_class($s) === static::class) {
                $this->set('ref', $this->get('ref') . '_copy');
            }
        });
    }
}

class DcQuote extends Model
{
    public $table = 'quote';

    protected function doInitialize(): void
    {
        parent::doInitialize();
        $this->hasOne('client', ['theirModel' => [DcClient::class]]);

        $this->withMany('Lines', ['theirModel' => [DcQuoteLine::class], 'theirKey' => 'parent_id'])
            ->addField('total', ['aggregate' => 'sum', 'field' => 'total']);

        $this->addField('ref');

        $this->addField('is_converted', ['type' => 'boolean', 'default' => false]);
    }
}

class DcInvoiceLine extends Model
{
    public $table = 'line';

    protected function doInitialize(): void
    {
        parent::doInitialize();
        $this->hasOne('parent', ['theirModel' => [DcInvoice::class]]);

        $this->addField('name');

        $this->addField('type', ['type' => ['enum', 'values' => ['invoice', 'quote']]]);
        $this->addCondition('type', '=', 'invoice');

        $this->addField('qty', ['type' => 'integer', 'mandatory' => true]);
        $this->addField('price', ['type' => 'money']);
        $this->addField('vat', ['type' => 'float', 'default' => 0.21]);

        // total is calculated with VAT
        $this->addExpression('total', '[qty]*[price]*(1+[vat])');
    }
}

class DcQuoteLine extends Model
{
    public $table = 'line';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->hasOne('parent', ['theirModel' => [DcQuote::class]]);

        $this->addField('name');

        $this->addField('type', ['type' => ['enum', 'values' => ['invoice', 'quote']]]);
        $this->addCondition('type', '=', 'quote');

        $this->addField('qty', ['type' => 'integer']);
        $this->addField('price', ['type' => 'money']);

        // total is calculated WITHOUT VAT
        $this->addExpression('total', '[qty]*[price]');
    }
}

class DcPayment extends Model
{
    public $table = 'payment';

    protected function doInitialize(): void
    {
        parent::doInitialize();
        $this->hasOne('client', ['theirModel' => [DcClient::class]]);

        $this->hasOne('invoice', ['theirModel' => [DcInvoice::class]]);

        $this->addField('amount', ['type' => 'money']);
    }
}

/**
 * Implements various tests for deep copying objects.
 */
class DeepCopyTest extends Sql\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // populate database for our three models
        $this->createMigrator(new DcClient($this->db))->dropIfExists()->create();
        $this->createMigrator(new DcInvoice($this->db))->dropIfExists()->create();
        $this->createMigrator(new DcQuote($this->db))->dropIfExists()->create();
        $this->createMigrator(new DcInvoiceLine($this->db))->dropIfExists()->create();
        $this->createMigrator(new DcPayment($this->db))->dropIfExists()->create();
    }

    public function testBasic(): void
    {
        $client = new DcClient($this->db);
        $client_id = $client->insert(['name' => 'John']);

        $quote = new DcQuote($this->db);

        $quote->insert([
            'ref' => 'q1', 'client_id' => $client_id, 'Lines' => [
                ['name' => 'tools', 'qty' => 5, 'price' => 10],
                ['name' => 'work', 'qty' => 1, 'price' => 40],
            ],
        ]);
        $quote = $quote->loadAny();

        // total price should match
        $this->assertEquals(90.00, $quote->get('total'));

        $dc = new DeepCopy();
        $invoice = $dc
            ->from($quote)
            ->to(new DcInvoice())
            ->with(['Lines'])
            ->copy();

        // price now will be with VAT
        $this->assertSame('q1', $invoice->get('ref'));
        $this->assertEquals(108.90, $invoice->get('total'));
        $this->assertEquals(1, $invoice->getId());

        // Note that we did not specify that 'client_id' should be copied, so same value here
        $this->assertSame($quote->get('client_id'), $invoice->get('client_id'));
        $this->assertSame('John', $invoice->ref('client')->get('name'));

        // now to add payment for the invoice. Payment originates from the same client as noted on the invoice
        $invoice->ref('Payments')->insert(['amount' => $invoice->get('total') - 5, 'client_id' => $invoice->get('client_id')]);

        $invoice->reload();

        // now that invoice is mostly paid, due amount will reflect that
        $this->assertEquals(5, $invoice->get('due'));

        // Next we copy invoice into simply a new record. Duplicate. However this time we will also duplicate payments,
        // and client. Because Payment references client too, we need to duplicate that one also, this way new record
        // structure will not be related to any existing records.
        $dc = new DeepCopy();
        $invoice_copy = $dc
            ->from($invoice)
            ->to(new DcInvoice())
            ->with(['Lines', 'client', 'Payments' => ['client']])
            ->copy();

        // Invoice copy receives a new ID
        $this->assertNotSame($invoice->getId(), $invoice_copy->getId());
        $this->assertSame('q1_copy', $invoice_copy->get('ref'));

        // ..however the due amount is the same - 5
        $this->assertEquals(5, $invoice_copy->get('due'));

        // ..client record was created in the process
        $this->assertNotSame($invoice_copy->get('client_id'), $invoice->get('client_id'));

        // ..but he is still called John
        $this->assertSame('John', $invoice_copy->ref('client')->get('name'));

        // finally, the client_id used for newly created payment and new invoice correspond
        $this->assertSame($invoice_copy->get('client_id'), $invoice_copy->ref('Payments')->loadAny()->get('client_id'));

        // the final test is to copy client entirely!

        $dc = new DeepCopy();
        $client3 = $dc
            ->from((new DcClient($this->db))->load(1))
            ->to(new DcClient())
            ->with([
                // Invoices are copied, but unless we also copy lines, totals won't be there!
                'Invoices' => [
                    'Lines',
                ],
                'Quotes' => [
                    'Lines',
                ],
                'Payments' => [
                    // this is important to have here, because we want copied payments to be linked with NEW invoices!
                    'invoice',
                ],
            ])
            ->copy();

        // New client receives new ID, but also will have all the relevant records copied
        $this->assertEquals(3, $client3->getId());

        // We should have one of each records for this new client
        $this->assertEquals(1, $client3->ref('Invoices')->getCount());
        $this->assertEquals(1, $client3->ref('Quotes')->getCount());
        $this->assertEquals(1, $client3->ref('Payments')->getCount());

        if ($this->getDatabasePlatform() instanceof SQLServer2012Platform) {
            $this->markTestIncomplete('TODO - MSSQL: Cannot perform an aggregate function on an expression containing an aggregate or a subquery.');
        }

        // We created invoice for 90 for client1, so after copying it should still be 90
        $this->assertEquals(90, $client3->ref('Quotes')->toQuery()->aggregate('sum', 'total')->getOne());

        // The total of the invoice we copied, should remain, it's calculated based on lines
        $this->assertEquals(108.9, $client3->ref('Invoices')->toQuery()->aggregate('sum', 'total')->getOne());

        // Payments by this clients should also be copied correctly
        $this->assertEquals(103.9, $client3->ref('Payments')->toQuery()->aggregate('sum', 'amount')->getOne());

        // If copied payments are properly allocated against copied invoices, then due amount will be 5
        $this->assertEquals(5, $client3->ref('Invoices')->toQuery()->aggregate('sum', 'due')->getOne());
    }

    public function testError(): void
    {
        $client = new DcClient($this->db);
        $client_id = $client->insert(['name' => 'John']);

        $quote = new DcQuote($this->db);
        $quote->withMany('Lines2', ['theirModel' => [DcQuoteLine::class], 'theirKey' => 'parent_id']);

        $quote->insert(['ref' => 'q1', 'client_id' => $client_id, 'Lines' => [
            ['name' => 'tools', 'qty' => 5, 'price' => 10],
            ['name' => 'work', 'qty' => 1, 'price' => 40],
        ]]);
        $quote = $quote->loadAny();

        $invoice = new DcInvoice();
        $invoice->onHook(DeepCopy::HOOK_AFTER_COPY, static function ($m) {
            if (!$m->get('ref')) {
                throw new \Phlex\Core\Exception('no ref');
            }
        });

        // total price should match
        $this->assertEquals(90.00, $quote->get('total'));

        $dc = new DeepCopy();

        $this->expectException(DeepCopyException::class);

        try {
            $invoice = $dc
                ->from($quote)
                ->excluding(['ref'])
                ->to($invoice)
                ->with(['Lines', 'Lines2'])
                ->copy();
        } catch (DeepCopyException $e) {
            $this->assertSame('no ref', $e->getPrevious()->getMessage());

            throw $e;
        }
    }

    public function testDeepError(): void
    {
        $client = new DcClient($this->db);
        $client_id = $client->insert(['name' => 'John']);

        $quote = new DcQuote($this->db);

        $quote->insert(['ref' => 'q1', 'client_id' => $client_id, 'Lines' => [
            ['name' => 'tools', 'qty' => 5, 'price' => 10],
            ['name' => 'work', 'qty' => 1, 'price' => 40],
        ]]);
        $quote = $quote->loadAny();

        $invoice = new DcInvoice();
        $invoice->onHook(DeepCopy::HOOK_AFTER_COPY, static function ($m) {
            if (!$m->get('ref')) {
                throw new \Phlex\Core\Exception('no ref');
            }
        });

        // total price should match
        $this->assertEquals(90.00, $quote->get('total'));

        $dc = new DeepCopy();

        $this->expectException(DeepCopyException::class);

        try {
            $invoice = $dc
                ->from($quote)
                ->excluding(['Lines' => ['qty']])
                ->to($invoice)
                ->with(['Lines'])
                ->copy();
        } catch (\Phlex\Data\Util\DeepCopyException $e) {
            $this->assertSame('Mandatory field value cannot be null', $e->getPrevious()->getMessage());

            throw $e;
        }
    }
}
