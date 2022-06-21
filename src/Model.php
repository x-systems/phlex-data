<?php

declare(strict_types=1);

namespace Phlex\Data;

use Phlex\Core\CollectionTrait;
use Phlex\Core\ContainerTrait;
use Phlex\Core\DynamicMethodTrait;
use Phlex\Core\Factory;
use Phlex\Core\HookTrait;
use Phlex\Core\InitializerTrait;
use Phlex\Core\InjectableTrait;
use Phlex\Core\OptionsTrait;
use Phlex\Core\Utils;
use Phlex\Data\Persistence\Sql;

/**
 * Data model class.
 *
 * @property int                 $id       @Phlex\Field(visibility="protected_set") Contains ID of the current record.
 *                                         If the value is null then the record is considered to be new.
 * @property Field[]|Reference[] $elements
 *
 * @phpstan-implements \IteratorAggregate<static>
 */
class Model implements \IteratorAggregate
{
    use CollectionTrait;
    use ContainerTrait {
        add as _add;
    }
    use DynamicMethodTrait;
    use Hintable\HintableModelTrait;
    use HookTrait;
    use InitializerTrait;
    use InjectableTrait;
    use Model\AggregatesTrait;
    use Model\JoinsTrait;
    use Model\ReferencesTrait;
    use Model\UserActionsTrait;
    use OptionsTrait;

    /** @const string */
    public const HOOK_BEFORE_LOAD = self::class . '@beforeLoad';
    /** @const string */
    public const HOOK_AFTER_LOAD = self::class . '@afterLoad';
    /** @const string */
    public const HOOK_BEFORE_UNLOAD = self::class . '@beforeUnload';
    /** @const string */
    public const HOOK_AFTER_UNLOAD = self::class . '@afterUnload';

    /** @const string */
    public const HOOK_BEFORE_INSERT = self::class . '@beforeInsert';
    /** @const string */
    public const HOOK_AFTER_INSERT = self::class . '@afterInsert';
    /** @const string */
    public const HOOK_BEFORE_UPDATE = self::class . '@beforeUpdate';
    /** @const string */
    public const HOOK_AFTER_UPDATE = self::class . '@afterUpdate';
    /** @const string */
    public const HOOK_BEFORE_DELETE = self::class . '@beforeDelete';
    /** @const string */
    public const HOOK_AFTER_DELETE = self::class . '@afterDelete';

    /** @const string */
    public const HOOK_BEFORE_SAVE = self::class . '@beforeSave';
    /** @const string */
    public const HOOK_AFTER_SAVE = self::class . '@afterSave';

    /** @const string Executed when execution of self::atomic() failed. */
    public const HOOK_ROLLBACK = self::class . '@rollback';

    /** @const string Executed for every field set using self::set() method. */
    public const HOOK_NORMALIZE = self::class . '@normalize';
    /** @const string Executed when self::validate() method is called. */
    public const HOOK_VALIDATE = self::class . '@validate';
    /** @const string Executed when self::onlyFields() method is called. */
    public const HOOK_ONLY_FIELDS = self::class . '@onlyFields';

    public const HOOK_SET_OPTION = self::class . '@afterSetOption';

    /** @const string */
    public const FIELD_FILTER_SYSTEM = 'system';
    /** @const string */
    public const FIELD_FILTER_NOT_SYSTEM = 'not system';
    /** @const string */
    public const FIELD_FILTER_REFERENCE = 'reference';
    /** @const string */
    public const FIELD_FILTER_NOT_REFERENCE = 'not reference';
    /** @const string */
    public const FIELD_FILTER_VISIBLE = 'visible';
    /** @const string */
    public const FIELD_FILTER_EDITABLE = 'editable';
    /** @const string */
    public const FIELD_FILTER_PERSIST = 'persist';
    /** @const string */
    public const FIELD_FILTER_ONLY_FIELDS = 'only fields';

    /** @const string */
    public const VALIDATE_INTENT_SAVE = 'save';

    /** @var Model\Scope\RootScope|null */
    private $scope;

    /** @var Model\Entry|null */
    private $entry;

    /**
     * The class used by addField() method.
     *
     * @todo use Field::class here and refactor addField() method to not use namespace prefixes.
     *       but because that's backward incompatible change, then we can do that only in next
     *       major version.
     *
     * @var string|array
     */
    public $_default_seed_addField = [Model\Field::class];

    /**
     * The class used by addExpression() method.
     *
     * @var string|array
     */
    public $_default_seed_addExpression = [Model\Field\Callback::class];

    /**
     * @var array Collection containing Field Objects - using key as the field system name
     */
    protected $fields = [];

    /**
     * Contains name of table, session key, collection or file where this
     * model normally lives. The interpretation of the table will be decoded
     * by persistence driver.
     *
     * You can define this field as associative array where "key" is used
     * as the name of persistence driver. Here is example for mysql and default:
     *
     * $table = ['user', 'mysql'=>'tbl_user'];
     *
     * @var string|array<0|string, string>|array<string, Model>|\Closure|false|null
     */
    public $table;

    /**
     * Use alias for $table.
     *
     * @var string|null
     */
    public $table_alias;

    /**
     * Sequence name. Some DB engines use sequence for generating auto_increment IDs.
     *
     * @var string|null
     */
    public $sequence;

    /**
     * Persistence driver inherited from Phlex\Data\Persistence.
     *
     * @var Persistence|Persistence\Sql|null
     */
    public $persistence;

    /**
     * Array of limit set.
     *
     * @var array
     */
    public $limit = [];

    /**
     * Array of set order by.
     *
     * @var array
     */
    public $order = [];

    /**
     * Array of WITH cursors set.
     *
     * @var array
     */
    public $with = [];

    /**
     * Setting model as read_only will protect you from accidentally
     * updating the model. This property is intended for UI and other code
     * detecting read-only models and acting accordingly.
     *
     * SECURITY WARNING: If you are looking for a RELIABLE way to restrict access
     * to model data, please check Secure Enclave extension.
     *
     * @var bool
     */
    public $read_only = false;

    /**
     * While in most cases your id field will be called 'id', sometimes
     * you would want to use a different one or maybe don't create field
     * at all.
     *
     * @var string|null
     */
    public $primaryKey = 'id';

    /**
     * Title field is used typically by UI components for a simple human
     * readable row title/description.
     *
     * @var string
     */
    public $titleKey = 'name';

    /**
     * Caption of the model. Can be used in UI components, for example.
     * Should be in plain English and ready for proper localization.
     *
     * @var string|null
     */
    public $caption;

    /**
     * When using onlyFields() this property will contain list of desired
     * fields.
     *
     * If you set onlyFields() before loading the data for this model, then
     * only that set of fields will be available. Attempt to access any other
     * field will result in exception. This is to ensure that you do not
     * accidentally access field that you have explicitly excluded.
     *
     * The default behavior is to return NULL and allow you to set new
     * fields even if addField() was not used to set the field.
     *
     * onlyFields() always allows to access fields with system = true.
     *
     * @var false|array
     */
    public $only_fields = false;

    /**
     * When set to true, all the field types will be enforced and
     * normalized when setting.
     *
     * @var bool
     */
    public $strict_types = true;

    /**
     * Models that contain expressions will automatically reload after save.
     * This is to ensure that any SQL-based calculation are executed and
     * updated correctly after you have performed any modifications to
     * the fields.
     *
     * You can set this property to "true" or "false" if you want to explicitly
     * enable or disable reloading.
     *
     * @var bool|null
     */
    public $reloadAfterSave;

    /**
     * Creation of the new model can be done in two ways:.
     *
     * $m = $db->add(new Model());
     *
     * or
     *
     * $m = new Model($db);
     *
     * The second use actually calls add() but is preferred usage because:
     *  - it's shorter
     *  - type hinting will work;
     *  - you can specify string for a table
     *
     * @param array<string, mixed> $defaults
     */
    public function __construct(Persistence $persistence = null, array $defaults = [])
    {
        $this->scope = \Closure::bind(fn () => new Model\Scope\RootScope(), null, Model\Scope\RootScope::class)()
            ->setModel($this);

        $this->setDefaults($defaults);

        if ($persistence !== null) {
            $persistence->add($this);
        }
    }

    /**
     * Check if model has persistence with specified method.
     */
    public function assertHasPersistence(string $method = null): void
    {
        if (!$this->persistence) {
            throw new Exception('Model is not associated with any persistence');
        }

        if ($method && !$this->persistence->hasMethod($method)) {
            throw new Exception("Persistence does not support {$method} method");
        }
    }

    /**
     * @return static
     */
    public function createEntity($data = [])
    {
        return (clone $this)->toEntity($data);
    }

    /**
     * @return static
     */
    public function toEntity($data = [])
    {
        $this->entry = new Model\Entry($data);

        return $this;
    }

    /**
     * Clones model object.
     */
    public function __clone()
    {
        $this->scope = (clone $this->scope)->setModel($this);
        if ($this->isEntity()) {
            $this->entry = clone $this->entry;
        }
        $this->_cloneCollection('elements');
        $this->_cloneCollection('fields');
        $this->_cloneCollection('userActions');

        // check for clone errors immediately, otherwise not strictly needed
        $this->_rebindHooksIfCloned();
    }

    /**
     * Extend this method to define fields of your choice.
     */
    protected function doInitialize(): void
    {
        if ($this->primaryKey) {
            if (!$this->hasPrimaryKeyField()) {
                $this->addField($this->primaryKey, ['type' => 'integer'])->asPrimaryKey();
            }
        } else {
            return; // don't declare actions for model without primaryKey
        }

        if ($this->read_only) {
            return; // don't declare action for read-only model
        }

        // Declare our basic Crud actions for the model.
        $this->addUserAction('add', [
            'fields' => true,
            'modifier' => Model\UserAction::MODIFIER_CREATE,
            'appliesTo' => Model\UserAction::APPLIES_TO_NO_RECORDS,
            'callback' => 'save',
            'description' => 'Add ' . $this->getCaption(),
        ]);

        $this->addUserAction('edit', [
            'fields' => true,
            'modifier' => Model\UserAction::MODIFIER_UPDATE,
            'appliesTo' => Model\UserAction::APPLIES_TO_SINGLE_RECORD,
            'callback' => 'save',
        ]);

        $this->addUserAction('delete', [
            'appliesTo' => Model\UserAction::APPLIES_TO_SINGLE_RECORD,
            'modifier' => Model\UserAction::MODIFIER_DELETE,
            'callback' => fn ($model) => $model->delete(),
        ]);

        $this->addUserAction('validate', [
            // 'appliesTo'=> any!
            'description' => 'Provided with modified values will validate them but will not save',
            'modifier' => Model\UserAction::MODIFIER_READ,
            'fields' => true,
            'system' => true, // don't show by default
            'args' => ['intent' => 'string'],
        ]);
    }

    /**
     * Perform validation on a currently loaded values, must return Array in format:
     *  ['field'=>'must be 4 digits exactly'] or empty array if no errors were present.
     *
     * You may also use format:
     *  ['field'=>['must not have character [ch]', 'ch'=>$bad_character']] for better localization of error message.
     *
     * Always use
     *   return array_merge(parent::validate($data, $intent), $errors);
     *
     * @param string $intent by default only Model::VALIDATE_INTENT_SAVE is used (from beforeSave) but you can use other intents yourself
     *
     * @return array [field => err_spec]
     */
    public function validate(string $intent = null): array
    {
        $errors = [];
        foreach ($this->hook(self::HOOK_VALIDATE, [$intent]) as $handler_error) {
            if ($handler_error) {
                $errors = array_merge($errors, $handler_error);
            }
        }

        return $errors;
    }

    /**
     * TEMPORARY to spot any use of $model->add(new Field(), ['bleh']); form.
     */
    public function add(object $obj, array $defaults = []): object
    {
        if ($obj instanceof Model\Field) {
            throw new Exception('You should always use addField() for adding fields, not add()');
        }

        return $this->_add($obj, $defaults);
    }

    /**
     * Adds new field into model.
     *
     * @param array|object $seed
     */
    public function addField(string $key, $seed = []): Model\Field
    {
        if (is_object($seed)) {
            $field = $seed;
        } else {
            $field = $this->fieldFactory($seed);
        }

        return $this->_addIntoCollection($key, $field, 'fields');
    }

    /**
     * Given a field seed, return a field object.
     */
    public function fieldFactory(array $seed = null): Model\Field
    {
        $seed = Factory::mergeSeeds(
            $seed,
            $this->_default_seed_addField,
            $this->persistence ? ($this->persistence->_default_seed_addField ?? null) : null
        );

        return Model\Field::fromSeed($seed);
    }

    /**
     * Adds multiple fields into model.
     *
     * @return $this
     */
    public function addFields(array $fields, array $defaults = [])
    {
        foreach ($fields as $key => $field) {
            if (!is_int($key)) {
                // field name can be passed as array key
                $name = $key;
            } elseif (is_string($field)) {
                // or it can be simple string = field name
                $name = $field;
                $field = [];
            } elseif (is_array($field) && is_string($field[0] ?? null)) {
                // or field name can be passed as first element of seed array (old behaviour)
                $name = array_shift($field);
            } else {
                // some unsupported format, maybe throw exception here?
                continue;
            }

            $seed = is_object($field) ? $field : array_merge($defaults, (array) $field);

            $this->addField($name, $seed);
        }

        return $this;
    }

    /**
     * Remove field that was added previously.
     *
     * @return $this
     */
    public function removeField(string $key)
    {
        $this->getField($key); // better exception if field does not exist

        $this->_removeFromCollection($key, 'fields');

        return $this;
    }

    public function hasField(string $key): bool
    {
        return $this->_hasInCollection($key, 'fields');
    }

    public function getField(string $key): Model\Field
    {
        try {
            return $this->_getFromCollection($key, 'fields');
        } catch (\Phlex\Core\Exception $e) {
            throw (new Exception('Field is not defined in model', 0, $e))
                ->addMoreInfo('model', $this)
                ->addMoreInfo('field', $key);
        }
    }

    public function hasPrimaryKeyField(): bool
    {
        return is_string($this->primaryKey) && $this->hasField($this->primaryKey);
    }

    public function getPrimaryKeyField(): ?Model\Field
    {
        return $this->hasPrimaryKeyField() ? $this->getField($this->primaryKey) : null;
    }

    public function getId()
    {
        $this->assertHasPrimaryKey();

        return $this->entry->get($this->primaryKey);
    }

    public function assertHasPrimaryKey(): void
    {
        if (!$this->hasPrimaryKeyField()) {
            throw new Exception('ID field is not defined');
        }
    }

    public function assertIsEntity(): void
    {
        if (!$this->isEntity()) {
            throw new Exception('Model is not entity');
        }
    }

    public function assertNotEntity(): void
    {
        if ($this->isEntity()) {
            throw new Exception('Model is entity');
        }
    }

    /**
     * Sets which fields we will select.
     *
     * @return $this
     */
    public function onlyFields(array $fields = [])
    {
        $this->hook(self::HOOK_ONLY_FIELDS, [&$fields]);
        $this->only_fields = $fields;

        return $this;
    }

    /**
     * Sets that we should select all available fields.
     *
     * @return $this
     */
    public function allFields()
    {
        $this->only_fields = false;

        return $this;
    }

    protected function assertOnlyField(string $key)
    {
        $this->getField($key); // test if field exists

        if ($this->only_fields) {
            if (!in_array($key, $this->only_fields, true) && !$this->getField($key)->system) {
                throw (new Exception('Attempt to use field outside of those set by onlyFields'))
                    ->addMoreInfo('field', $key)
                    ->addMoreInfo('only_fields', $this->only_fields);
            }
        }
    }

    public function isOnlyField(string $key): bool
    {
        return !$this->only_fields || in_array($key, $this->only_fields, true);
    }

    /**
     * @param string|array|null $filters
     *
     * @return Model\Field[]
     */
    public function getFields($filters = null, bool $onlyFields = null): array
    {
        if ($filters === null) {
            return $onlyFields ? $this->getFields(self::FIELD_FILTER_ONLY_FIELDS) : $this->fields;
        } elseif (!is_array($filters)) {
            $filters = [$filters];
        }

        $onlyFields ??= true;

        return array_filter($this->fields, function (Model\Field $field, $name) use ($filters, $onlyFields) {
            if ($onlyFields && !$this->isOnlyField($field->elementId)) {
                return false;
            }

            foreach ($filters as $filter) {
                if ($this->fieldMatchesFilter($field, $filter)) {
                    return true;
                }
            }

            return false;
        }, \ARRAY_FILTER_USE_BOTH);
    }

    protected function fieldMatchesFilter(Model\Field $field, string $filter): bool
    {
        switch ($filter) {
            case self::FIELD_FILTER_SYSTEM:
                return $field->system;
            case self::FIELD_FILTER_NOT_SYSTEM:
                return !$field->system;
            case self::FIELD_FILTER_REFERENCE:
                return $field instanceof Model\Field\Reference;
            case self::FIELD_FILTER_NOT_REFERENCE:
                return !$field instanceof Model\Field\Reference;
            case self::FIELD_FILTER_EDITABLE:
                return $field->isEditable();
            case self::FIELD_FILTER_VISIBLE:
                return $field->isVisible();
            case self::FIELD_FILTER_ONLY_FIELDS:
                return $this->isOnlyField($field->elementId);
            case self::FIELD_FILTER_PERSIST:
                if (!$field->interactsWithPersistence()) {
                    return false;
                }

                return $field->system || $this->isOnlyField($field->elementId);
            default:
                throw (new Exception('Filter is not supported'))
                    ->addMoreInfo('filter', $filter);
        }
    }

    /**
     * Return (possibly localized) $model->caption.
     * If caption is not set, then generate it from model class name.
     */
    public function getCaption(): string
    {
        return $this->caption ?: Utils::getReadableCaption(
            (new \ReflectionClass(static::class))->isAnonymous() ? get_parent_class(static::class) : static::class
        );
    }

    /**
     * Returns array of model record titles [id => title].
     */
    public function getTitles(): array
    {
        $key = $this->titleKey && $this->hasField($this->titleKey) ? $this->titleKey : $this->primaryKey;

        return array_map(fn ($row) => $row[$key], $this->export([$key], $this->primaryKey));
    }

    /**
     * Narrow down data-set of the current model by applying
     * additional condition. There is no way to remove
     * condition once added, so if you need - clone model.
     *
     * This is the most basic for defining condition:
     *  ->addCondition('my_field', $value);
     *
     * This condition will work across all persistence drivers universally.
     *
     * In some cases a more complex logic can be used:
     *  ->addCondition('my_field', '>', $value);
     *  ->addCondition('my_field', '!=', $value);
     *  ->addCondition('my_field', 'in', [$value1, $value2]);
     *
     * Second argument could be '=', '>', '<', '>=', '<=', '!=', 'in', 'like' or 'regexp'.
     * Those conditions are still supported by most of persistence drivers.
     *
     * There are also vendor-specific expression support:
     *  ->addCondition('my_field', $expr);
     *  ->addCondition($expr);
     *
     * Conditions on referenced models are also supported:
     *  $contact->addCondition('company/country', 'US');
     * where 'company' is the name of the reference
     * This will limit scope of $contact model to contacts whose company country is set to 'US'
     *
     * Using # in conditions on referenced model will apply the condition on the number of records:
     * $contact->addCondition('tickets/#', '>', 5);
     * This will limit scope of $contact model to contacts that have more than 5 tickets
     *
     * To use those, you should consult with documentation of your
     * persistence driver.
     *
     * @param mixed $field
     * @param mixed $operator
     * @param mixed $value
     *
     * @return $this
     */
    public function addCondition($field, $operator = null, $value = null)
    {
        $this->scope()->addCondition(...func_get_args());

        return $this;
    }

    /**
     * Get the scope object of the Model.
     */
    public function scope(): Model\Scope\RootScope
    {
        if ($this->scope->getModel() === null) {
            $this->scope->setModel($this);
        }

        return $this->scope;
    }

    /**
     * Shortcut for using addCondition(primaryKey, $id).
     *
     * @param mixed $id
     *
     * @return $this
     */
    public function withId($id)
    {
        return $this->addCondition($this->primaryKey, $id);
    }

    /**
     * Adds WITH cursor.
     *
     * @param Model $model
     *
     * @return $this
     */
    public function addWith(self $model, string $alias, array $mapping = [], bool $recursive = false)
    {
        if (isset($this->with[$alias])) {
            throw (new Exception('With cursor already set with this alias'))
                ->addMoreInfo('alias', $alias);
        }

        $this->with[$alias] = [
            'model' => $model,
            'mapping' => $mapping,
            'recursive' => $recursive,
        ];

        return $this;
    }

    /**
     * Set order for model records. Multiple calls.
     *
     * @param string|array $field
     * @param string       $direction "asc" or "desc"
     *
     * @return $this
     */
    public function setOrder($field, string $direction = 'asc')
    {
        // fields passed as array
        if (is_array($field)) {
            if (func_num_args() > 1) {
                throw (new Exception('If first argument is array, second argument must not be used'))
                    ->addMoreInfo('arg1', $field)
                    ->addMoreInfo('arg2', $direction);
            }

            foreach (array_reverse($field) as $key => $direction) {
                if (is_int($key)) {
                    if (is_array($direction)) {
                        // format [field, direction]
                        $this->setOrder(...$direction);
                    } else {
                        // format "field"
                        $this->setOrder($direction);
                    }
                } else {
                    // format "field" => direction
                    $this->setOrder($key, $direction);
                }
            }

            return $this;
        }

        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw (new Exception('Invalid order direction, direction can be only "asc" or "desc"'))
                ->addMoreInfo('field', $field)
                ->addMoreInfo('direction', $direction);
        }

        // finally set order
        $this->order[] = [$field, $direction];

        return $this;
    }

    /**
     * Set limit of DataSet.
     *
     * @return $this
     */
    public function setLimit(int $count = null, int $offset = 0)
    {
        $this->limit = [$count, $offset];

        return $this;
    }

    public function get($key = null)
    {
        $this->assertIsEntity();

        if ($key === null) {
            // Collect list of eligible fields
            $data = [];
            foreach ($this->only_fields ?: array_keys($this->getFields(self::FIELD_FILTER_NOT_REFERENCE)) as $key) {
                $data[$key] = $this->get($key);
            }

            return $data;
        }

        $this->assertOnlyField($key);

        return $this->getField($key)->get();
    }

    /**
     * Helper method to call self::set() for each input array element.
     *
     * This method does not revert the data when an exception is thrown.
     *
     * @return $this
     */
    public function setMulti(array $fields)
    {
        foreach ($fields as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    public function setId($id)
    {
        $this->assertHasPrimaryKey();

        if ($this->isLoaded()) {
            throw new Exception('Modifying of primary key of loaded record');
        }

        if (!$this->isEntity()) {
            $this->toEntity();
        }

        $this->entry->set($this->primaryKey, $this->getPrimaryKeyField()->normalize($id));

        return $this;
    }

    public function set($key, $value)
    {
        $this->assertIsEntity();

        $this->assertOnlyField($key);

        $this->getField($key)->set($value);

        return $this;
    }

    public function reset($key)
    {
        $this->assertIsEntity();

        $this->assertOnlyField($key);

        $this->entry->reset($key);

        return $this;
    }

    public function setNull($key)
    {
        $this->assertIsEntity();

        $this->assertOnlyField($key);

        $this->entry->set($key, null);

        return $this;
    }

    public function isset(string $key): bool
    {
        $this->assertIsEntity();

        $this->assertOnlyField($key);

        return $this->entry->isset($key);
    }

    /**
     * Return value of $model->get($model->titleKey). If not set, returns id value.
     *
     * @return mixed
     */
    public function getTitle()
    {
        if ($this->titleKey && $this->hasField($this->titleKey)) {
            return $this->get($this->titleKey);
        }

        return $this->getId();
    }

    public function isLoaded(): bool
    {
        return $this->isEntity() && $this->primaryKey && $this->entry->isLoaded($this->primaryKey);
    }

    public function isEntity(): bool
    {
        return $this->entry !== null;
    }

    public function getEntry()
    {
        return $this->entry;
    }

    public function isDirty($key): bool
    {
        $this->assertIsEntity();

        $this->assertOnlyField($key);

        return $this->entry->isDirty($key);
    }

    public function compare(string $name, $value): bool
    {
        return $this->getField($name)->compare($this->get($name), $value);
    }

    /**
     * Unload model.
     *
     * @return $this
     */
    public function unload()
    {
        $this->hook(self::HOOK_BEFORE_UNLOAD);
        $this->entry = null;
        $this->hook(self::HOOK_AFTER_UNLOAD);

        return $this;
    }

    /**
     * Try to load record.
     * Will not throw exception if record doesn't exist.
     *
     * @param mixed $id
     *
     * @return $this
     */
    public function tryLoad($id)
    {
        try {
            return $this->load($id);
        } catch (Model\RecordNotFoundException $e) {
        }

        return $this->createEntity();
    }

    /**
     * Load any record.
     *
     * @return $this
     */
    public function loadAny()
    {
        return $this->load();
    }

    /**
     * Try to load any record.
     * Will not throw exception if record doesn't exist.
     *
     * @return $this
     */
    public function tryLoadAny()
    {
        try {
            return $this->load();
        } catch (Model\RecordNotFoundException $e) {
        }

        return $this->createEntity();
    }

    /**
     * Load model.
     *
     * @param mixed $id
     *
     * @return static
     */
    public function load($id = null)
    {
        return (clone $this)->loadSelf($id);
    }

    private function loadSelf($id = null)
    {
        $this->assertHasPersistence();

        $this->unload();

        if ($this->hook(self::HOOK_BEFORE_LOAD, [$id]) === false) {
            return $this;
        }

        $data = $this->persistence->getRow($this, $id);

        if ($data !== null) {
            $this->toEntity($data);

            /** @var static|false $ret */
            $ret = $this->hook(self::HOOK_AFTER_LOAD);
            if ($ret === false) {
                $this->unload();
            } elseif (is_object($ret)) {
                return $ret; // @phpstan-ignore-line
            }
        }

        return $this;
    }

    /**
     * Load record by condition.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function loadBy(string $key, $value)
    {
        $field = $this->getField($key);

        $scopeBak = $this->scope;
        $systemBak = $field->system;
        $defaultBak = $field->default;
        try {
            // add condition to cloned scope and try to load record
            $this->scope = clone $this->scope;
            $this->addCondition($field, $value);

            return $this->loadAny();
        } finally {
            $this->scope = $scopeBak;
            $field->system = $systemBak;
            $field->default = $defaultBak;
        }
    }

    /**
     * Try to load record by condition.
     * Will not throw exception if record doesn't exist.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function tryLoadBy(string $key, $value)
    {
        $field = $this->getField($key);

        $scopeBak = $this->scope;
        $systemBak = $field->system;
        $defaultBak = $field->default;
        try {
            // add condition to cloned scope and try to load record
            $this->scope = clone $this->scope;
            $this->addCondition($field, $value);

            return $this->tryLoadAny();
        } finally {
            $this->scope = $scopeBak;
            $field->system = $systemBak;
            $field->default = $defaultBak;
        }
    }

    /**
     * Reload model by taking its current ID.
     *
     * @return $this
     */
    public function reload()
    {
        $this->assertIsEntity();

        return $this->loadSelf($this->getId());
    }

    /**
     * Keeps the model data, but wipes out the ID so
     * when you save it next time, it ends up as a new
     * record in the database.
     *
     * @return static
     */
    public function duplicate($id = null)
    {
        $duplicate = clone $this;

        if ($id !== null) {
            $duplicate = $duplicate->load($id);
        }

        $duplicate->assertIsEntity();

        $duplicate->entry->unset($duplicate->primaryKey);

        return $duplicate;
    }

    /**
     * Save the $data but do not reload the entity from persistence.
     *
     * @return $this
     */
    public function saveWithoutReloading(array $data = [])
    {
        $reloadAfterSaveBackup = $this->reloadAfterSave;
        try {
            $this->reloadAfterSave = false;

            return $this->save($data);
        } finally {
            $this->reloadAfterSave = $reloadAfterSaveBackup;
        }

        return $this;
    }

    /**
     * This will cast Model into another class without
     * loosing state of your active record.
     */
    public function asModel(string $class, array $options = []): self
    {
        $model = $this->newInstance($class, $options);

        if ($this->entry) {
            $model->entry = clone $this->entry;
        }

        return $model;
    }

    /**
     * Create new model from the same base class
     * as $this.
     *
     * @return static
     */
    public function newInstance(string $class = null, array $options = [])
    {
        $model = (self::class)::fromSeed([$class ?? static::class], $options);

        if ($this->persistence) {
            return $this->persistence->add($model); // @phpstan-ignore-line
        }

        return $model;
    }

    /**
     * Create new model from the same base class
     * as $this. If you omit $id,then when saving
     * a new record will be created with default ID.
     * If you specify $id then it will be used
     * to save/update your record. If set $id
     * to `true` then model will assume that there
     * is already record like that in the destination
     * persistence.
     *
     * See https://github.com/x-systems/phlex-data/issues/111 for use-case examples.
     *
     * @param mixed                $id
     * @param class-string<static> $class
     *
     * @return static
     */
    public function withPersistence(Persistence $persistence, $id = null, string $class = null)
    {
        $class ??= static::class;

        /** @var self $model */
        $model = new $class($persistence, ['table' => $this->table]);

        if ($this->isEntity()) {
            $model->entry = clone $this->entry;
        }

        if ($this->primaryKey && $id !== null) {
            $model->setId($id === true ? $this->getId() : $id);
        }

        // include any fields defined inline
        foreach ($this->fields as $key => $field) {
            if (!$model->hasField($key)) {
                $model->addField($key, clone $field);
            }
        }

        $model->limit = $this->limit;
        $model->order = $this->order;
        $model->scope = (clone $this->scope)->setModel($model);

        return $model;
    }

    /**
     * Save record.
     *
     * @return $this
     */
    public function save($data = [])
    {
        if (!$this->isEntity()) {
            return $this->createEntity($data)->save();
        }

        if ($this->read_only) {
            throw new Exception('Model is read-only and cannot be saved');
        }

        $this->setMulti($data);

        // if no persistence just commit entry
        if (!$this->persistence) {
            $this->entry->commit();

            return $this;
        }

        return $this->atomic(function () {
            if (($errors = $this->validate(self::VALIDATE_INTENT_SAVE)) !== []) {
                throw new Model\Field\ValidationException($errors, $this);
            }

            $isUpdate = $this->isLoaded();
            if ($this->hook(self::HOOK_BEFORE_SAVE, [$isUpdate]) === false) {
                return $this;
            }

            $result = 0;
            $dirty_join = false;
            $dirty = $isUpdate ? $this->entry->getDirty() : $this->get();

            $data = [];
            foreach ($dirty as $key => $value) {
                if (!$this->hasField($key)) {
                    continue;
                }

                $field = $this->getField($key);
                if (!$field->interactsWithPersistence() || !$field->savesToPersistence()) {
                    continue;
                }

                if ($field->hasJoin()) {
                    $dirty_join = true;
                    // storing into a different table join
                    $field->getJoin()->set($key, $value);
                } else {
                    $data[$key] = $value;
                }
            }

            if ($isUpdate) {
                // No save needed, nothing was changed
                if (!$data && !$dirty_join) {
                    return $this;
                }

                if ($this->hook(self::HOOK_BEFORE_UPDATE, [&$data]) === false) {
                    return $this;
                }

                if ($data) {
                    $result = $this->persistence->update($this, $this->getId(), $data)->rowCount();
                }

                $this->hook(self::HOOK_AFTER_UPDATE, [&$data]);

                if (!$dirty_join && !$result) {
                    throw new Exception('No records updated');
                }
            } else {
                if ($this->hook(self::HOOK_BEFORE_INSERT, [&$data]) === false) {
                    return $this;
                }

                // Collect all data of a new record
                $id = $this->persistence->insert($this, $data);

                if (!$this->primaryKey) {
                    $this->hook(self::HOOK_AFTER_INSERT, [null]);
                } else {
                    $this->setId($id);
                    $this->hook(self::HOOK_AFTER_INSERT, [$this->getId()]);

                    $result = true;
                }
            }

            // if any rows were inserted/updated in database, and we had expressions, reload
            if ($result && $this->reloadAfterSave === true) {
                $this->reload();
            }

            $this->entry->commit();

            $this->hook(self::HOOK_AFTER_SAVE, [$isUpdate]);

            return $this;
        });
    }

    /**
     * Faster method to add data, that does not modify active record.
     *
     * Will be further optimized in the future.
     *
     * @return mixed
     */
    public function insert(iterable $row)
    {
        // Find any row values that do not correspond to fields, and they may correspond to
        // references instead
        $refs = [];
        foreach ($row as $key => $value) {
            // and we only support array values
            if (!is_array($value)) {
                continue;
            }

            // and reference must exist with same name
            if (!$this->hasReference($key)) {
                continue;
            }

            // Then we move value for later
            $refs[$key] = $value;
            unset($row[$key]);
        }

        // save data fields
        $model = $this->createEntity();

        if ($model->primaryKey) {
            if ($idRaw = $row[$model->primaryKey] ?? null) {
                $model->setId($idRaw);

                unset($row[$model->primaryKey]);
            }
        }

        $model->saveWithoutReloading($row);

        $ret = null;

        // store id value
        if ($model->isLoaded() && $model->hasPrimaryKeyField()) {
            $ret = $model->getId();
        }

        // if there was referenced data, then import it
        foreach ($refs as $key => $value) {
            $model->ref($key)->import($value);
        }

        return $ret;
    }

    /**
     * Even more faster method to add data, does not modify your
     * current record and will not return anything.
     *
     * Will be further optimized in the future.
     *
     * @return $this
     */
    public function import(array $rows)
    {
        $this->atomic(function () use ($rows) {
            foreach ($rows as $row) {
                $this->insert($row);
            }
        });

        return $this;
    }

    /**
     * Export DataSet as array of hashes.
     *
     * @param array|null $keys     Names of fields to export
     * @param string     $arrayKey Optional name of field which value we will use as array key
     * @param bool       $decode   Should we decode exported data
     */
    public function export(array $keys = null, string $arrayKey = null, bool $decode = true): array
    {
        $this->assertHasPersistence('export');

        // @todo: why only persisting fields?
        // prepare array with field names
        if ($keys === null) {
            $keys = array_keys($this->getFields(self::FIELD_FILTER_PERSIST));
        }

        // no key field - then just do export
        if ($arrayKey === null) {
            return $this->persistence->export($this, $keys, $decode);
        }

        // do we have added key field in fields list?
        // if so, then will have to remove it afterwards
        $key_field_added = false;

        // add key_field to array if it's not there
        if (!in_array($arrayKey, $keys, true)) {
            $keys[] = $arrayKey;
            $key_field_added = true;
        }

        // export
        $data = $this->persistence->export($this, $keys, $decode);

        // prepare resulting array
        $res = [];
        foreach ($data as $row) {
            $key = $row[$arrayKey];
            if ($key_field_added) {
                unset($row[$arrayKey]);
            }
            $res[$key] = $row;
        }

        return $res;
    }

    /**
     * Returns entry values as array encoded for the $mutator.
     *
     * @return \Traversable<static>
     */
    public function encode(MutatorInterface $mutator): array
    {
        return $mutator->encodeRow($this, $this->get());
    }

    /**
     * Returns iterator (yield values).
     *
     * @return \Traversable<static>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->toQuery() as $data) {
            $entity = $this->createEntity($this->persistence->decodeRow($this, $data));

            // you can return false in afterLoad hook to prevent to yield this data row
            // use it like this:
            // $model->onHook(self::HOOK_AFTER_LOAD, static function ($m) {
            //     if ($m->get('date') < $m->date_from) $m->breakHook(false);
            // })

            // you can also use breakHook() with specific object which will then be returned
            // as a next iterator value

            $ret = $entity->hook(self::HOOK_AFTER_LOAD);

            if ($ret === false) {
                continue;
            }

            if (!is_object($ret)) {
                $ret = $entity;
            }

            if ($ret->primaryKey) {
                yield $ret->getId() => $ret; // @phpstan-ignore-line
            } else {
                yield $ret; // @phpstan-ignore-line
            }
        }
    }

    /**
     * Executes specified callback for each record in DataSet.
     *
     * @return $this
     */
    public function each(\Closure $fx)
    {
        foreach ($this as $record) {
            $fx($record);
        }

        return $this;
    }

    /**
     * Delete record with a specified id. If no ID is specified
     * then current record is deleted.
     *
     * @param mixed $id
     *
     * @return static
     */
    public function delete($id = null)
    {
        if ($this->read_only) {
            throw new Exception('Model is read-only and cannot be deleted');
        }

        $id ??= $this->getId();

        $this->atomic(function () use ($id) {
            if ($this->hook(self::HOOK_BEFORE_DELETE, [$id]) === false) {
                return;
            }
            $this->persistence->delete($this, $id);
            $this->hook(self::HOOK_AFTER_DELETE, [$id]);
        });

        return $this;
    }

    /**
     * Atomic executes operations within one begin/end transaction, so if
     * the code inside callback will fail, then all of the transaction
     * will be also rolled back.
     *
     * @return mixed
     */
    public function atomic(\Closure $fx, ...$args)
    {
        try {
            return $this->persistence->atomic($fx, ...$args);
        } catch (\Exception $e) {
            if ($this->hook(self::HOOK_ROLLBACK, [$e]) === false) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Get query object to perform query on raw persistence data.
     */
    public function toQuery(): Persistence\Query
    {
        $this->assertHasPersistence('query');

        return $this->persistence->query($this);
    }

    /**
     * Add expression field.
     *
     * @param string|array|Sql\Expressionable|\Closure $expression
     *
     * @return Model\Field\Callback
     */
    public function addExpression(string $key, $expression)
    {
        if (!is_array($expression)) {
            $expression = ['expr' => $expression];
        } elseif (isset($expression[0])) {
            $expression['expr'] = $expression[0];
            unset($expression[0]);
        }

        /** @var Model\Field\Callback */
        $field = Model\Field::fromSeed($this->_default_seed_addExpression, $expression);

        $this->addField($key, $field);

        return $field;
    }

    /**
     * Add expression field which will calculate its value by using callback.
     *
     * @param string|array|\Closure $expression
     *
     * @return Model\Field\Callback
     */
    public function addCalculatedField(string $key, $expression)
    {
        if (!is_array($expression)) {
            $expression = ['expr' => $expression];
        } elseif (isset($expression[0])) {
            $expression['expr'] = $expression[0];
            unset($expression[0]);
        }

        $field = new Model\Field\Callback($expression);

        $this->addField($key, $field);

        return $field;
    }

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
//         if ($this->isEntity()) {
//             return [
//                 'entityId' => $this->primaryKey && $this->hasField($this->primaryKey)
//                     ? (($this->entityId !== null ? $this->entityId . ($this->getId() !== null ? '' : ' (unloaded)') : 'null'))
//                     : 'no id field',
//                 'model' => $this->__debugInfo(),
//             ];
//         }

        return [
            'table' => $this->table,
            //             'scope' => $this->scope()->toWords(),
        ];
    }
}
