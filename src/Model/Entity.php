<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

use Phlex\Core\CollectionTrait;
use Phlex\Core\ContainerTrait;
use Phlex\Core\DynamicMethodTrait;
use Phlex\Core\HookTrait;
use Phlex\Core\InitializerTrait;
use Phlex\Core\InjectableTrait;
use Phlex\Core\OptionsTrait;
use Phlex\Core\SeedRegistryTrait;
use Phlex\Core\Utils;
use Phlex\Data;
use Phlex\Data\Persistence;
use Phlex\Data\Model;
use Phlex\Data\Exception;

/**
 * Data model class.
 *
 * @property int                 $id       @Phlex\Field(visibility="protected_set") Contains ID of the current record.
 *                                         If the value is null then the record is considered to be new.
 * @property Field[]|Reference[] $elements
 *
 * @phpstan-implements \IteratorAggregate<static>
 */
class Entity implements \IteratorAggregate
{
    use CollectionTrait;
    use ContainerTrait {
        add as _add;
    }
    use DynamicMethodTrait;
    use Data\Hintable\HintableModelTrait;
    use HookTrait;
    use InitializerTrait;
    use InjectableTrait;
    use Model\AggregatesTrait;
    use Model\JoinsTrait;
    use Model\ReferencesTrait;
    use Model\UserActionsTrait;
    use OptionsTrait;
    use SeedRegistryTrait;

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

//     /** @const string Executed when execution of self::atomic() failed. */
//     public const HOOK_ROLLBACK = self::class . '@rollback';

//     /** @const string Executed for every field set using self::set() method. */
//     public const HOOK_NORMALIZE = self::class . '@normalize';
//     /** @const string Executed when self::validate() method is called. */
//     public const HOOK_VALIDATE = self::class . '@validate';
//     /** @const string Executed when self::onlyFields() method is called. */
//     public const HOOK_ONLY_FIELDS = self::class . '@onlyFields';

//     public const HOOK_SET_OPTION = self::class . '@afterSetOption';

    /** @const string */
    public const VALIDATE_INTENT_SAVE = 'save';

    /**
//      * @var Model
     */
    private Model $model;

    /**
     * @var mixed once set, loading a different ID will result in an error
     */
    private $id;

    /**
     * Currently loaded record data. This record is associative array
     * that contain field=>data pairs. It may contain data for un-defined
     * fields only if $onlyFields mode is false.
     *
     * Avoid accessing $data directly, use set() / get() instead.
     *
     * @var array
     */
    private $data = [];

    /**
     * After loading an active record from DataSet it will be stored in
     * $data property and you can access it using get(). If you use
     * set() to change any of the data, the original value will be copied
     * here.
     *
     * If the value you set equal to the original value, then the key
     * in this array will be removed.
     *
     * The $dirty data will be reset after you save() the data but it is
     * still available to all before/after save handlers.
     *
     * @var array
     */
    private $dirty = [];

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

    public function __construct(Model $model)
    {
        $this->model = $model;
    }
    
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Clones model object.
     */
//     public function __clone()
//     {
//         if (!$this->isEntity()) {
//             $this->scope = (clone $this->scope)->setModel($this);
//         }
//         $this->_cloneCollection('elements');
//         $this->_cloneCollection('fields');
//         $this->_cloneCollection('userActions');

//         // check for clone errors immediately, otherwise not strictly needed
//         $this->_rebindHooksIfCloned();
//     }

    /**
     * Extend this method to define fields of your choice.
     */
//     protected function doInitialize(): void
//     {
//         if ($this->primaryKey) {
//             if (!$this->hasPrimaryKeyField()) {
//                 $this->addField($this->primaryKey, ['type' => 'integer'])->asPrimaryKey();
//             }
//         } else {
//             return; // don't declare actions for model without primaryKey
//         }

//         $this->initEntityIdHooks();

//         if ($this->read_only) {
//             return; // don't declare action for read-only model
//         }

//         // Declare our basic Crud actions for the model.
//         $this->addUserAction('add', [
//             'fields' => true,
//             'modifier' => Model\UserAction::MODIFIER_CREATE,
//             'appliesTo' => Model\UserAction::APPLIES_TO_NO_RECORDS,
//             'callback' => 'save',
//             'description' => 'Add ' . $this->getCaption(),
//         ]);

//         $this->addUserAction('edit', [
//             'fields' => true,
//             'modifier' => Model\UserAction::MODIFIER_UPDATE,
//             'appliesTo' => Model\UserAction::APPLIES_TO_SINGLE_RECORD,
//             'callback' => 'save',
//         ]);

//         $this->addUserAction('delete', [
//             'appliesTo' => Model\UserAction::APPLIES_TO_SINGLE_RECORD,
//             'modifier' => Model\UserAction::MODIFIER_DELETE,
//             'callback' => fn ($model) => $model->delete(),
//         ]);

//         $this->addUserAction('validate', [
//             //'appliesTo'=> any!
//             'description' => 'Provided with modified values will validate them but will not save',
//             'modifier' => Model\UserAction::MODIFIER_READ,
//             'fields' => true,
//             'system' => true, // don't show by default
//             'args' => ['intent' => 'string'],
//         ]);
//     }

//     private function initEntityIdAndAssertUnchanged(): void
//     {
//         $id = $this->getId();
//         if ($id === null) { // allow unload
//             return;
//         }

//         if ($this->id === null) {
//             // set entity ID to the first seen ID
//             $this->id = $id;
//         } elseif (!$this->model->compare($this->primaryKey, $this->id)) {
//             $this->unload(); // data for different ID were loaded, make sure to discard them

//             throw (new Exception('Model instance is an entity, ID can not be changed to a different one ' . $this->id . ' ' . $id))
//                 ->addMoreInfo('entityId', $this->id)
//                 ->addMoreInfo('newId', $id);
//         }
//     }

//     private function initEntityIdHooks(): void
//     {
//         $fx = function () {
//             $this->initEntityIdAndAssertUnchanged();
//         };

//         $this->onHookShort(self::HOOK_BEFORE_LOAD, $fx, [], 10);
//         $this->onHookShort(self::HOOK_AFTER_LOAD, $fx, [], -10);
//         $this->onHookShort(self::HOOK_BEFORE_INSERT, $fx, [], 10);
//         $this->onHookShort(self::HOOK_AFTER_INSERT, $fx, [], -10);
//         $this->onHookShort(self::HOOK_BEFORE_UPDATE, $fx, [], 10);
//         $this->onHookShort(self::HOOK_AFTER_UPDATE, $fx, [], -10);
//         $this->onHookShort(self::HOOK_BEFORE_DELETE, $fx, [], 10);
//         $this->onHookShort(self::HOOK_AFTER_DELETE, $fx, [], -10);
//         $this->onHookShort(self::HOOK_BEFORE_SAVE, $fx, [], 10);
//         $this->onHookShort(self::HOOK_AFTER_SAVE, $fx, [], -10);
//     }

    /**
     * @internal should be not used outside phlex-data
     */
    public function &getDataRef(): array
    {
        return $this->data;
    }

    /**
     * @internal should be not used outside phlex-data
     */
    public function &getDirtyRef(): array
    {
        return $this->dirty;
    }

    /**
     * Perform validation on a currently loaded values, must return Array in format:
     *  ['field'=>'must be 4 digits exactly'] or empty array if no errors were present.
     *
     * You may also use format:
     *  ['field'=>['must not have character [ch]', 'ch'=>$bad_character']] for better localization of error message.
     *
     * Always use
     *   return array_merge(parent::validate($intent), $errors);
     *
     * @param string $intent by default only Model::VALIDATE_INTENT_SAVE is used (from beforeSave) but you can use other intents yourself
     *
     * @return array [field => err_spec]
     */
    public function validate(string $intent = null): array
    {
        $errors = [];
        foreach ($this->model->hook(Model::HOOK_VALIDATE, [$intent]) as $handler_error) {
            if ($handler_error) {
                $errors = array_merge($errors, $handler_error);
            }
        }

        return $errors;
    }

    /**
     * Will return true if specified field is dirty.
     */
    public function isDirty(string $key): bool
    {
        $this->model->checkOnlyFieldsField($key);

        return array_key_exists($key, $this->getDirtyRef());
    }

    /**
     * Set field value.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function set(string $key, $value)
    {
        $this->model->checkOnlyFieldsField($key);

        $field = $this->getField($key);

        try {
            $value = $field->normalize($value);
        } catch (Exception $e) {
            throw $e
                ->addMoreInfo('key', $key)
                ->addMoreInfo('value', $value)
                ->addMoreInfo('field', $field);
        }

        // do nothing when value has not changed
        $dataRef = &$this->getDataRef();
        $dirtyRef = &$this->getDirtyRef();
        $currentValue = array_key_exists($key, $dataRef)
            ? $dataRef[$key]
            : (array_key_exists($key, $dirtyRef) ? $dirtyRef[$key] : $field->default);
        if ($field->compare($value, $currentValue)) {
            return $this;
        }

        $field->assertSetAccess();

        if (array_key_exists($key, $dirtyRef) && $field->compare($dirtyRef[$key], $value)) {
            unset($dirtyRef[$key]);
        } elseif (!array_key_exists($key, $dirtyRef)) {
            $dirtyRef[$key] = array_key_exists($key, $dataRef) ? $dataRef[$key] : $field->default;
        }
        $dataRef[$key] = $value;

        return $this;
    }

    /**
     * Unset field value even if null value is not allowed.
     *
     * @return $this
     */
    public function setNull(string $key)
    {
        // set temporary hook to disable any normalization (null validation)
        $hookIndex = $this->model->onHookShort(Model::HOOK_NORMALIZE, static function () {
            throw new \Phlex\Core\HookBreaker(false);
        }, [], \PHP_INT_MIN);
        try {
            return $this->set($key, null);
        } finally {
            $this->model->removeHook(Model::HOOK_NORMALIZE, $hookIndex, true);
        }
    }

    /**
     * Helper method to call self::set() for each input array element.
     *
     * This method does not revert the data when an exception is thrown.
     *
     * @return $this
     */
    public function setMulti(array $keyValues)
    {
        foreach ($keyValues as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * Returns field value.
     * If no field is passed, then returns array of all field values.
     *
     * @return mixed
     */
    public function get(string $key = null)
    {
        if ($key === null) {
            // Collect list of eligible fields
            $data = [];
            foreach ($this->model->only_fields ?: array_keys($this->model->getFields()) as $key) {
                $data[$key] = $this->get($key);
            }

            return $data;
        }

        $this->model->checkOnlyFieldsField($key);

        $dataRef = &$this->getDataRef();
        if (array_key_exists($key, $dataRef)) {
            return $dataRef[$key];
        }

        return $this->getField($key)->default;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        $this->assertHasPrimaryKey();

        return $this->get($this->model->primaryKey);
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setId($value)
    {
        $this->assertHasPrimaryKey();

        if ($value === null) {
            $this->setNull($this->model->primaryKey);
        } else {
            $this->set($this->model->primaryKey, $value);
        }

//         $this->initEntityIdAndAssertUnchanged();

        return $this;
    }
    
    private function assertHasPrimaryKey(): void
    {
        if (!$this->model->hasPrimaryKeyField()) {
            throw new Exception('Primary key is not defined');
        }
    }
    
    public function assertModelIs(Model $model): void
    {
        if (!$this->model === $model) {
            throw new Exception('Model mismatch');
        }
    }

    /**
     * Return value of $model->get($model->titleKey). If not set, returns id value.
     *
     * @return mixed
     */
    public function getTitle()
    {
        if ($this->model->titleKey && $this->model->hasField($this->model->titleKey)) {
            return $this->get($this->model->titleKey);
        }

        return $this->getId();
    }

    /**
     * Does key exist in Model data?
     */
    public function _isset(string $key): bool
    {
        $this->model->checkOnlyFieldsField($key);

        return array_key_exists($key, $this->getDirtyRef());
    }

    /**
     * Remove current field value and use default.
     *
     * @return $this
     */
    public function _unset(string $key)
    {
        $this->model->checkOnlyFieldsField($key);

        $dataRef = &$this->getDataRef();
        $dirtyRef = &$this->getDirtyRef();
        if (array_key_exists($key, $dirtyRef)) {
            $dataRef[$key] = $dirtyRef[$key];
            unset($dirtyRef[$key]);
        }

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function compare(string $key, $value): bool
    {
        return $this->getField($key)->compare($value, $this->get($key));
    }

    public function isLoaded(): bool
    {
        return $this->model->primaryKey && $this->getId() !== null && $this->id !== null;
    }
    
    public function getField(string $key): Model\Field
    {
        return $this->model->getField($key);
    }
    
    public function getFields($filters = null, bool $onlyFields = null): array
    {
        return $this->model->getFields(...func_get_args());
    }

    /**
     * Unload model.
     *
     * @return $this
     */
    public function unload()
    {
        $this->model->hook(Model::HOOK_BEFORE_UNLOAD);
        $dataRef = &$this->getDataRef();
        $dirtyRef = &$this->getDirtyRef();
        $dataRef = [];
        if ($this->model->primaryKey) {
            $this->setId(null);
        }
        $dirtyRef = [];
        $this->model->hook(Model::HOOK_AFTER_UNLOAD);

        return $this;
    }

    public function loadData($id)
    {
        if ($this->isLoaded()) {
            throw new Exception('Entity must be unloaded');
        }

        $this->model->assertHasPersistence();

        if ($this->model->hook(Model::HOOK_BEFORE_LOAD, [$id]) === false) {
            return $this;
        }

        $dataRef = &$this->getDataRef();
        $dataRef = $this->model->persistence->getRow($this->getModel(), $id);

        if ($dataRef === null) {
            $dataRef = [];
            $this->unload();
        } else {
            if ($this->model->primaryKey) {
                $this->setId($this->getId());
            }

            /** @var static|false $ret */
            $ret = $this->model->hook(Model::HOOK_AFTER_LOAD, [$this]);
            if ($ret === false) {
                $this->unload();
            } elseif (is_object($ret)) {
                return $ret; // @phpstan-ignore-line
            }
        }

        return $this;
    }

//     /**
//      * Load record by condition.
//      *
//      * @param mixed $value
//      *
//      * @return $this
//      */
//     public function loadBy(string $key, $value)
//     {
//         $field = $this->getField($key);

//         $scopeBak = $this->scope;
//         $systemBak = $field->system;
//         $defaultBak = $field->default;
//         try {
//             // add condition to cloned scope and try to load record
//             $this->scope = clone $this->scope;
//             $this->addCondition($field, $value);

//             return $this->loadAny();
//         } finally {
//             $this->scope = $scopeBak;
//             $field->system = $systemBak;
//             $field->default = $defaultBak;
//         }
//     }

//     /**
//      * Try to load record by condition.
//      * Will not throw exception if record doesn't exist.
//      *
//      * @param mixed $value
//      *
//      * @return $this
//      */
//     public function tryLoadBy(string $key, $value)
//     {
//         $field = $this->getField($key);

//         $scopeBak = $this->scope;
//         $systemBak = $field->system;
//         $defaultBak = $field->default;
//         try {
//             // add condition to cloned scope and try to load record
//             $this->scope = clone $this->scope;
//             $this->addCondition($field, $value);

//             return $this->tryLoadAny();
//         } finally {
//             $this->scope = $scopeBak;
//             $field->system = $systemBak;
//             $field->default = $defaultBak;
//         }
//     }

    /**
     * Reload model by taking its current ID.
     *
     * @return $this
     */
    public function reload()
    {
        $id = $this->getId();

        return $this->unload()->loadData($id);
    }

    /**
     * Keeps the model data, but wipes out the ID so
     * when you save it next time, it ends up as a new
     * record in the database.
     *
     * @return static
     */
    public function duplicate()
    {
        // TODO remove in v2.6
        if (func_num_args() > 0) {
            throw new Exception('Duplicating using existing ID is no longer supported');
        }

        $duplicate = clone $this;
        $duplicate->id = null;
        $dataRef = &$this->getDataRef();
        $duplicateDirtyRef = &$duplicate->getDirtyRef();
        $duplicateDirtyRef = $dataRef;
        $duplicate->setId(null);

        return $duplicate;
    }

    /**
     * Store the data into database, but will never attempt to
     * reload the data. Additionally any data will be unloaded.
     * Use this instead of save() if you want to squeeze a
     * little more performance out.
     *
     * @return $this
     */
    public function saveAndUnload(array $data = [])
    {
        return $this->saveWithoutReloading($data)->unload();
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
            $this->save($data);
        } finally {
            $this->reloadAfterSave = $reloadAfterSaveBackup;
        }

        return $this;
    }

    /**
     * Save record.
     *
     * @return $this
     */
    public function save(array $data = [])
    {
        $this->model->assertHasPersistence();

        if ($this->model->read_only) {
            throw new Exception('Model is read-only and cannot be saved');
        }

        $this->setMulti($data);

        return $this->model->atomic(function () {
            $dirtyRef = &$this->getDirtyRef();
            $dirtyAfterReload = [];

            if (($errors = $this->validate(Model::VALIDATE_INTENT_SAVE)) !== []) {
                throw new Model\Field\ValidationException($errors, $this);
            }

            $isUpdate = $this->isLoaded();
            if ($this->hook(self::HOOK_BEFORE_SAVE, [$isUpdate]) === false) {
                return $this;
            }

            if ($isUpdate) {
                $data = [];
                $dirty_join = false;
                foreach ($dirtyRef as $key => $ignore) {
                    if (!$this->model->hasField($key)) {
                        continue;
                    }

                    $field = $this->getField($key);
                    if (!$field->checkSetAccess() || !$field->interactsWithPersistence() || !$field->savesToPersistence()) {
                        continue;
                    }

                    // get the value of the field
                    $value = $this->get($key);

                    if ($field->hasJoin()) {
                        $dirty_join = true;
                        // storing into a different table join
                        $field->getJoin()->set($key, $value);
                    } else {
                        $data[$key] = $value;
                    }
                }

                // No save needed, nothing was changed
                if (!$data && !$dirty_join) {
                    return $this;
                }

                if ($this->hook(self::HOOK_BEFORE_UPDATE, [&$data]) === false) {
                    return $this;
                }

                $result = $this->persistence->update($this, $this->getId(), $data);

                $this->hook(self::HOOK_AFTER_UPDATE, [&$data]);

                // if any rows were updated in database, and we had expressions, reload
                if ($this->reloadAfterSave === true && $result->rowCount()) {
                    $dirtyBeforeReload = $dirtyRef;
                    $this->reload();
                    $dirtyAfterReload = $dirtyRef;
                    $dirtyRef = $dirtyBeforeReload;
                }
            } else {
                $data = [];
                foreach ($this->get() as $key => $value) {
                    if (!$this->model->hasField($key)) {
                        continue;
                    }

                    $field = $this->getField($key);
                    if (!$field->checkSetAccess() || !$field->interactsWithPersistence() || !$field->savesToPersistence()) {
                        continue;
                    }

                    if ($field->hasJoin()) {
                        // storing into a different table join
                        $field->getJoin()->set($key, $value);
                    } else {
                        $data[$key] = $value;
                    }
                }

                if ($this->hook(self::HOOK_BEFORE_INSERT, [&$data]) === false) {
                    return $this;
                }

                // Collect all data of a new record
                $id = $this->model->persistence->insert($this->model, $data);

                if (!$this->model->primaryKey) {
                    $this->hook(self::HOOK_AFTER_INSERT, [null]);

                    $dirtyRef = [];
                } else {
                    $this->setId($id);
                    $this->hook(self::HOOK_AFTER_INSERT, [$this->getId()]);

                    if ($this->reloadAfterSave !== false) {
                        $dirtyBeforeReload = $dirtyRef;
                        $this->reload();
                        $dirtyAfterReload = $dirtyRef;
                        $dirtyRef = $dirtyBeforeReload;
                    }
                }
            }

            if ($this->isLoaded()) {
                $dirtyRef = $dirtyAfterReload;
            }

            $this->model->hook(Model::HOOK_AFTER_SAVE, [$isUpdate]);

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
    public function insert(array $row)
    {
        $entity = $this->getModel(true)->createEntity();

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
        $entity->saveWithoutReloading($row);

        // store id value
        if ($entity->primaryKey) {
            $entity->getDataRef()[$entity->primaryKey] = $entity->getId();
        }

        // if there was referenced data, then import it
        foreach ($refs as $key => $value) {
            $entity->ref($key)->import($value);
        }

        return $entity->primaryKey ? $entity->getId() : null;
    }

    /**
     * Even more faster method to add data, does not modify your
     * current record and will not return anything.
     *
     * Will be further optimized in the future.
     *
     * @return $this
     */
//     public function import(array $rows)
//     {
//         $this->model->atomic(function () use ($rows) {
//             foreach ($rows as $row) {
//                 $this->insert($row);
//             }
//         });

//         return $this;
//     }

    /**
     * Returns entity values as array encoded for the $mutator.
     *
     * @return \Traversable<static>
     */
    public function encode(Data\MutatorInterface $mutator): array
    {
        return $mutator->encodeRow($this->model, $this->get());
    }

    /**
     * Returns iterator (yield values).
     *
     * @return \Traversable<static>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->toQuery() as $data) {
            $thisCloned = $this->createEntity();

            $dataRef = &$thisCloned->getDataRef();
            $dataRef = $this->persistence->decodeRow($this, $data);

            if ($this->primaryKey) {
                $thisCloned->setId($dataRef[$this->primaryKey] ?? null);
            }

            // you can return false in afterLoad hook to prevent to yield this data row
            // use it like this:
            // $model->onHook(self::HOOK_AFTER_LOAD, static function ($m) {
            //     if ($m->get('date') < $m->date_from) $m->breakHook(false);
            // })

            // you can also use breakHook() with specific object which will then be returned
            // as a next iterator value

            $ret = $thisCloned->hook(Model::HOOK_AFTER_LOAD);

            if ($ret === false) {
                continue;
            }

            if (!is_object($ret)) {
                $ret = $thisCloned;
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
    public function delete()
    {
        if ($this->model->read_only) {
            throw new Exception('Model is read-only and cannot be deleted');
        } elseif (!$this->isLoaded()) {
            throw new Exception('No active record is set, unable to delete.');
        }

        $this->model->atomic(function () {
            if ($this->hook(self::HOOK_BEFORE_DELETE, [$this->getId()]) === false) {
                return;
            }
            $this->persistence->delete($this->model, $this->getId());
            $this->hook(self::HOOK_AFTER_DELETE, [$this->getId()]);
        });

        return $this->unload();
    }

    /**
     * Atomic executes operations within one begin/end transaction, so if
     * the code inside callback will fail, then all of the transaction
     * will be also rolled back.
     *
     * @return mixed
     */
//     public function atomic(\Closure $fx)
//     {
//         try {
//             return $this->persistence->atomic($fx);
//         } catch (\Exception $e) {
//             if ($this->model->hook(Model::HOOK_ROLLBACK, [$e]) === false) {
//                 return false;
//             }

//             throw $e;
//         }
//     }
    
    public function ref(string $link): Model
    {
        return $this->model->getReference($link)->getTheirEntities($this);
    }

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
        if ($this->isEntity()) {
            return [
                'id' => $this->model->primaryKey && $this->model->hasField($this->model->primaryKey)
                    ? (($this->id !== null ? $this->id . ($this->getId() !== null ? '' : ' (unloaded)') : 'null'))
                    : 'no id field',
                'model' => $this->getModel()->__debugInfo(),
            ];
        }

        return [
            'table' => $this->table,
            'scope' => $this->scope()->toWords(),
        ];
    }
}
