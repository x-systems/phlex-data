<?php

declare(strict_types=1);

namespace Phlex\Data\Tests;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

class StAccount extends Model
{
    public $table = 'account';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addField('name');

        $this->withMany('Transactions', ['theirModel' => [StGenericTransaction::class]])
            ->addField('balance', ['aggregate' => 'sum', 'field' => 'amount']);

        $this->withMany('Transactions:Deposit', ['theirModel' => [StTransaction_Deposit::class]]);
        $this->withMany('Transactions:Withdrawal', ['theirModel' => [StTransaction_Withdrawal::class]]);
        $this->withMany('Transactions:Ob', ['theirModel' => [StTransaction_Ob::class]])
            ->addField('opening_balance', ['aggregate' => 'sum', 'field' => 'amount']);

        $this->withMany('Transactions:TransferOut', ['theirModel' => [StTransaction_TransferOut::class]]);
        $this->withMany('Transactions:TransferIn', ['theirModel' => [StTransaction_TransferIn::class]]);
    }

    /**
     * @return static
     */
    public static function open(Persistence $persistence, string $name, float $amount = 0.0)
    {
        $m = new static($persistence);
        $m = $m->createEntity();
        $m->save(['name' => $name]);

        if ($amount) {
            $m->ref('Transactions:Ob')->save(['amount' => $amount]);
        }

        return $m;
    }

    public function deposit(float $amount): Model
    {
        return $this->ref('Transactions:Deposit')->save(['amount' => $amount]);
    }

    public function withdraw(float $amount): Model
    {
        return $this->ref('Transactions:Withdrawal')->save(['amount' => $amount]);
    }

    /**
     * @return array<int, Model>
     */
    public function transferTo(self $account, float $amount): array
    {
        $out = $this->ref('Transactions:TransferOut')->save(['amount' => $amount]);
        $in = $account->ref('Transactions:TransferIn')->save(['amount' => $amount, 'link_id' => $out->getId()]);
        $out->set('link_id', $in->getId());
        $out->save();

        return [$in, $out];
    }
}

class StGenericTransaction extends Model
{
    public $table = 'transaction';
    /** @var string */
    public $type;

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->hasOne('account', ['theirModel' => [StAccount::class]]);
        $this->addField('type', ['type' => ['enum', 'values' => ['Ob', 'Deposit', 'Withdrawal', 'TransferOut', 'TransferIn']]]);

        if ($this->type) {
            $this->addCondition('type', $this->type);
        }
        $this->addField('amount', ['type' => 'money']);

        $this->onHookShort(Model::HOOK_AFTER_LOAD, function () {
            if (static::class !== $this->getClassName()) {
                $cl = $this->getClassName();
                $cl = new $cl($this->persistence);
                $cl = $cl->load($this->getId());

                $this->breakHook($cl);
            }
        });
    }

    public function getClassName(): string
    {
        return __NAMESPACE__ . '\StTransaction_' . $this->get('type');
    }
}

class StTransaction_Ob extends StGenericTransaction
{
    public $type = 'Ob';
}

class StTransaction_Deposit extends StGenericTransaction
{
    public $type = 'Deposit';
}

class StTransaction_Withdrawal extends StGenericTransaction
{
    public $type = 'Withdrawal';
}

class StTransaction_TransferOut extends StGenericTransaction
{
    public $type = 'TransferOut';

    protected function doInitialize(): void
    {
        parent::doInitialize();
        $this->hasOne('link', ['theirModel' => [StTransaction_TransferIn::class]]);

        // $this->join('transaction','linked_transaction');
    }
}

class StTransaction_TransferIn extends StGenericTransaction
{
    public $type = 'TransferIn';

    protected function doInitialize(): void
    {
        parent::doInitialize();
        $this->hasOne('link', ['theirModel' => [StTransaction_TransferOut::class]]);
    }
}

/**
 * Implements various tests for deep copying objects.
 */
class SubTypesTest extends Sql\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // populate database for our three models
        $this->createMigrator(new StAccount($this->db))->dropIfExists()->create();
        $this->createMigrator(new StTransaction_TransferOut($this->db))->dropIfExists()->create();
    }

    public function testBasic(): void
    {
        $inheritance = StAccount::open($this->db, 'inheritance', 1000);
        $current = StAccount::open($this->db, 'current');

        $inheritance->transferTo($current, 500);
        $current->withdraw(350);

        $this->assertInstanceOf(StTransaction_Ob::class, $inheritance->ref('Transactions')->load(1));
        $this->assertInstanceOf(StTransaction_TransferOut::class, $inheritance->ref('Transactions')->load(2));
        $this->assertInstanceOf(StTransaction_TransferIn::class, $current->ref('Transactions')->load(3));
        $this->assertInstanceOf(StTransaction_Withdrawal::class, $current->ref('Transactions')->load(4));

        $cl = [];
        foreach ($current->ref('Transactions') as $tr) {
            $cl[] = get_class($tr);
        }

        $this->assertSame([StTransaction_TransferIn::class, StTransaction_Withdrawal::class], $cl);
    }
}
