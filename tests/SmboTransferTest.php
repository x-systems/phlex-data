<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Model;
use Phlex\Data\Tests\Model\Smbo\Account;
use Phlex\Data\Tests\Model\Smbo\Document;
use Phlex\Data\Tests\Model\Smbo\Payment;
use Phlex\Data\Tests\Model\Smbo\Transfer;

/**
 * Practical test contributed by Sortmybooks.com.
 */
class SmboTransferTest extends Sql\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->dropTableIfExists('account');
        $this->dropTableIfExists('document');
        $this->dropTableIfExists('payment');

        (new Account($this->db))->migrate();
        (new Document($this->db))->migrate();

        $payment = new Model($this->db, ['table' => 'payment']);

        $payment->addField('document_id', ['type' => 'integer']);
        $payment->addField('account_id', ['type' => 'integer']);
        $payment->addField('cheque_no');
        $payment->addField('misc_payment');
        $payment->addField('transfer_document_id');

        $payment->migrate();
    }

    /**
     * Testing transfer between two accounts.
     */
    public function testTransfer(): void
    {
        $aib = (new Account($this->db))->save(['name' => 'AIB']);
        $boi = (new Account($this->db))->save(['name' => 'BOI']);

        $t = $aib->transfer($boi, 100); // create transfer between accounts
        $t->save();

        $this->assertEquals(-100, $aib->reload()->get('balance'));
        $this->assertEquals(100, $boi->reload()->get('balance'));

        $t = new Transfer($this->db);
        $data = $t->export(['id', 'transfer_document_id']);
        usort($data, fn ($e1, $e2) => $e1['id'] < $e2['id'] ? -1 : 1);
        $this->assertSame([
            ['id' => 1, 'transfer_document_id' => 2],
            ['id' => 2, 'transfer_document_id' => 1],
        ], $data);
    }

    public function testRef(): void
    {
        // create accounts and payments
        $a = new Account($this->db);

        $aa = $a->createEntity();
        $aa->save(['name' => 'AIB']);
        $aa->ref('Payment')->save(['amount' => 10]);
        $aa->ref('Payment')->save(['amount' => 20]);
        $aa->unload();

        $aa = $a->createEntity();
        $aa->save(['name' => 'BOI']);
        $aa->ref('Payment')->save(['amount' => 30]);
        $aa->unload();

        // create payment without link to account
        $p = new Payment($this->db);
        $p->saveWithoutReloading(['amount' => 40])->unload();

        // Account is not loaded, will dump all Payments related to ANY Account
        $data = $a->ref('Payment')->export(['amount']);
        $this->assertEquals([
            ['amount' => 10],
            ['amount' => 20],
            ['amount' => 30],
            // ['amount' => 40], // will not select this because it is not related to any Account
        ], $data);

        // Account is loaded, will dump all Payments related to that particular Account
        $a = $a->load(1);
        $data = $a->ref('Payment')->export(['amount']);
        $this->assertEquals([
            ['amount' => 10],
            ['amount' => 20],
        ], $data);
    }

    /*
    public function testBasicEntities(): void
    {
        $db = Persistence\Sql::connect($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);

        // Create a new company
        $company = new Company($db);
        $company->set([
            'name'           => 'Test Company 1',
            'director_name'  => 'Tester Little',
            'type'           => 'Limited Company',
            'vat_registered' => true,
        ]);
        $company->save();

        return;

        // Create two new clients, one is sole trader, other is limited company
        $client = $company->ref('Client');
        list($john_id, $agile_id) = $m->insert([
            ['name' => 'John Smith Consulting', 'vat_registered' => false],
            'Agile Software Limited',
        ]);

        // Insert a first, default invoice for our sole-trader
        $john = $company->load($john_id);
        $john_invoices = $john->ref('Invoice');
        $john_invoices->insertInvoice([
            'ref_no'   => 'INV1',
            'due_date' => (new Date())->add(new DateInterval('2w')), // due in 2 weeks
            'lines'    => [
                ['descr' => 'Sold some sweets', 'total_gross' => 100.00],
                ['descr' => 'Delivery', 'total_gross' => 10.00],
            ],
        ]);

        // Use custom method to create a sub-nominal
        $company->ref('Nominal')->insertSubNominal('Sales', 'Discounted');

        // Insert our second invoice using set referencing
        $company->ref('Client')->load($agile_id)->refSet('Invoice')->insertInvoice([
            'lines' => [
                [
                    'item_id'   => $john->ref('Product')->insert('Cat Food'),
                    'nominal'   => 'Sales:Discounted',
                    'total_net' => 50.00,
                    'vat_rate'  => 23,
                    // calculates total_gross at 61.50.
                ],
                [
                    'item_id'   => $john->ref('Service')->insert('Delivery'),
                    'total_net' => 10.00,
                    'vat_rate'  => '23%',
                    // calculates total_gross at 12.30
                ],
            ],
        ]);

        // Next we create bank account
        $hsbc = $john->ref('Account')->set('name', 'HSBC')->save();

        // And each of our invoices will have one new payment
        foreach ($john_invoices as $invoice) {
            $invoice->ref('Payment')->insert(['amount' => 10.20, 'bank_account_id' => $hsbc]);
        }

        // Now let's execute report
        $debt = $john->add(new Model_Report_Debtors());

        // This should give us total amount owed by all clients:
        // (100.00+10.00) + (61.50 + 12.30) - 10.20*2
        $this->assertEquals(163.40, $debt->sum('amount'));
    }
     */
}
