<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

use Phlex\Core\InitializerTrait;
use Phlex\Core\InjectableTrait;
use Phlex\Core\TrackableTrait;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

/**
 * Provides generic functionality for joining data.
 *
 * @method Model getOwner()
 */
class Join
{
    use InitializerTrait;
    use InjectableTrait;
    use JoinLinkTrait;
    use TrackableTrait;

    /**
     * Name of the table (or collection) that can be used to retrieve data from.
     * For SQL, This can also be an expression or sub-select.
     *
     * @var string
     */
    protected $foreign_table;

    /**
     * If $persistence is set, then it's used for loading
     * and storing the values, instead $owner->persistence.
     *
     * @var Persistence|Persistence\Sql|null
     */
    protected $persistence;

    /**
     * ID used by a joined table.
     *
     * @var mixed
     */
    protected $id;

    /**
     * Field that is used as native "ID" in the foreign table.
     * When deleting record, this field will be conditioned.
     *
     * ->where($join->primaryKey, $join->id)->delete();
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * By default this will be either "inner" (for strong) or "left" for weak joins.
     * You can specify your own type of join by passing ['kind'=>'right']
     * as second argument to join().
     *
     * @var string|null
     */
    protected $kind;

    /**
     * Is our join weak? Weak join will stop you from touching foreign table.
     *
     * @var bool
     */
    protected $weak = false;

    /**
     * Normally the foreign table is saved first, then it's ID is used in the
     * primary table. When deleting, the primary table record is deleted first
     * which is followed by the foreign table record.
     *
     * If you are using the following syntax:
     *
     * $user->join('contact','default_contact_id');
     *
     * Then the ID connecting tables is stored in foreign table and the order
     * of saving and delete needs to be reversed. In this case $reverse
     * will be set to `true`. You can specify value of this property.
     *
     * @var bool|null
     */
    protected $reverse;

    /**
     * Field to be used for matching inside master table. By default
     * it's $foreign_table.'_id'.
     * Note that it should be actual field name in master table.
     *
     * @var string|null
     */
    protected $master_field;

    /**
     * Field to be used for matching in a foreign table. By default
     * it's 'id'.
     * Note that it should be actual field name in foreign table.
     *
     * @var string|null
     */
    protected $foreign_field;

    /**
     * A short symbolic name that will be used as an alias for the joined table.
     *
     * @var string|null
     */
    public $foreign_alias;

    /**
     * When $prefix is set, then all the fields generated through
     * our wrappers will be automatically prefixed inside the model.
     *
     * @var string
     */
    protected $prefix = '';

    /**
     * Data which is populated here as the save/insert progresses.
     *
     * @var array
     */
    protected $save_buffer = [];

    public function __construct($foreign_table = null)
    {
        if ($foreign_table !== null) {
            $this->foreign_table = $foreign_table;
        }
    }

    protected function onHookShortToOwner(string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $name = $this->elementId; // use static function to allow this object to be GCed

        return $this->getOwner()->onHookDynamicShort(
            $spot,
            static fn (Model $owner) => $owner->getElement($name),
            $fx,
            $args,
            $priority
        );
    }

    /**
     * Will use either foreign_alias or create #join_<table>.
     */
    public function getDesiredName(): string
    {
        return '#join_' . $this->foreign_table;
    }

    /**
     * Initialization.
     */
    protected function doInitialize(): void
    {
        // owner model should have primaryKey set
        $primaryKey = $this->getOwner()->primaryKey;
        if (!$primaryKey) {
            throw (new Exception('Joins owner model should have primaryKey set'))
                ->addMoreInfo('model', $this->getOwner());
        }

        // handle foreign table containing a dot - that will be reverse join
        if (is_string($this->foreign_table) && strpos($this->foreign_table, '.') !== false) {
            // split by LAST dot in foreign_table name
            [$this->foreign_table, $this->foreign_field] = preg_split('~\.+(?=[^.]+$)~', $this->foreign_table);

            if (!isset($this->reverse)) {
                $this->reverse = true;
            }
        }

        if ($this->reverse === true) {
            if (isset($this->master_field) && $this->master_field !== $primaryKey) { // TODO not implemented yet, see https://github.com/x-systems/phlex-data/issues/803
                throw (new Exception('Joining tables on non-id fields is not implemented yet'))
                    ->addMoreInfo('condition', $this->getOwner()->table . '.' . $this->master_field . ' = ' . $this->foreign_table . '.' . $this->foreign_field);
            }

            if (!$this->master_field) {
                $this->master_field = $primaryKey;
            }

            if (!$this->foreign_field) {
                $this->foreign_field = $this->getOwner()->table . '_' . $primaryKey;
            }
        } else {
            $this->reverse = false;

            if (!$this->master_field) {
                $this->master_field = $this->foreign_table . '_' . $primaryKey;
            }

            if (!$this->foreign_field) {
                $this->foreign_field = $primaryKey;
            }
        }

        $this->onHookShortToOwner(Model::HOOK_AFTER_UNLOAD, \Closure::fromCallable([$this, 'afterUnload']));
    }

    /**
     * Adding field into join will automatically associate that field
     * with this join. That means it won't be loaded from $table, but
     * form the join instead.
     */
    public function addField(string $name, array $seed = []): Field
    {
        $seed['joinName'] = $this->elementId;

        return $this->getOwner()->addField($this->prefix . $name, $seed);
    }

    /**
     * Adds multiple fields.
     *
     * @return $this
     */
    public function addFields(array $fields = [])
    {
        foreach ($fields as $field) {
            if (is_array($field)) {
                $name = $field[0];
                unset($field[0]);
                $this->addField($name, $field);
            } else {
                $this->addField($field);
            }
        }

        return $this;
    }

    /**
     * Another join will be attached to a current join.
     *
     * @return self
     */
    public function join(string $foreign_table, array $defaults = [])
    {
        $defaults['joinName'] = $this->elementId;

        return $this->getOwner()->join($foreign_table, $defaults);
    }

    /**
     * Another leftJoin will be attached to a current join.
     *
     * @return self
     */
    public function leftJoin(string $foreign_table, array $defaults = [])
    {
        $defaults['joinName'] = $this->elementId;

        return $this->getOwner()->leftJoin($foreign_table, $defaults);
    }

    /**
     * weakJoin will be attached to a current join.
     *
     * @todo NOT IMPLEMENTED! weakJoin method does not exist!
     */
    /*
    public function weakJoin($defaults = [])
    {
        $defaults['joinName'] = $this->elementId;

        return $this->getOwner()->weakJoin($defaults);
    }
    */

    /**
     * Creates reference based on a field from the join.
     *
     * @return Field\Reference\HasOne
     */
    public function hasOne(string $link, array $defaults = [])
    {
        $defaults['joinName'] = $this->elementId;

        return $this->getOwner()->hasOne($link, $defaults);
    }

    /**
     * Creates reference based on the field from the join.
     *
     * @return Field\Reference\WithMany
     */
    public function withMany(string $link, array $defaults = [])
    {
        $defaults = array_merge([
            'ourKey' => $this->primaryKey,
            'theirKey' => $this->getOwner()->table . '_' . $this->primaryKey,
        ], $defaults);

        return $this->getOwner()->withMany($link, $defaults);
    }

    /**
     * Wrapper for containsOne that will associate field
     * with join.
     *
     * @todo NOT IMPLEMENTED !
     *
     * @return ???
     */
    /*
    public function containsOne(Model $model, array $defaults = [])
    {
        if (is_string($defaults[0])) {
            $defaults[0] = $this->addField($defaults[0], ['system' => true]);
        }

        return parent::containsOne($model, $defaults);
    }
    */

    /**
     * Wrapper for containsMany that will associate field
     * with join.
     *
     * @todo NOT IMPLEMENTED !
     *
     * @return ???
     */
    /*
    public function containsMany(Model $model, array $defaults = [])
    {
        if (is_string($defaults[0])) {
            $defaults[0] = $this->addField($defaults[0], ['system' => true]);
        }

        return parent::containsMany($model, $defaults);
    }
    */

    /**
     * Will iterate through this model by pulling
     *  - fields
     *  - references
     *  - conditions.
     *
     * and then will apply them locally. If you think that any fields
     * could clash, then use ['prefix'=>'m2'] which will be pre-pended
     * to all the fields. Conditions will be automatically mapped.
     *
     * @todo NOT IMPLEMENTED !
     */
    /*
    public function importModel(Model $model, array $defaults = [])
    {
        // not implemented yet !!!
    }
    */

    /**
     * Joins with the primary table of the model and
     * then import all of the data into our model.
     *
     * @todo NOT IMPLEMENTED!
     */
    /*
    public function weakJoinModel(Model $model, array $fields = [])
    {
        if (!is_object($model)) {
            $model = $this->getOwner()->connection->add($model);
        }
        $j = $this->join($model->table);

        $j->importModel($model);

        return $j;
    }
    */

    /**
     * Set value.
     *
     * @param string $field
     * @param mixed  $value
     *
     * @return $this
     */
    public function set($field, $value)
    {
        $this->save_buffer[$field] = $value;

        return $this;
    }

    /**
     * Clears id and save buffer.
     */
    protected function afterUnload(): void
    {
        $this->id = null;
        $this->save_buffer = [];
    }
}
