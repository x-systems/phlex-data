<?php

declare(strict_types=1);

namespace Phlex\Data;

use Doctrine\DBAL\Platforms;
use Phlex\Core\Factory;

abstract class Persistence
{
    use \Phlex\Core\ContainerTrait {
        add as _add;
    }
    use \Phlex\Core\DiContainerTrait;
    use \Phlex\Core\DynamicMethodTrait;
    use \Phlex\Core\HookTrait;
    use \Phlex\Core\NameTrait;

    /** @const string */
    public const HOOK_INIT_SELECT_QUERY = self::class . '@initSelect';
    /** @const string */
    public const HOOK_BEFORE_INSERT_QUERY = self::class . '@beforeInsert';
    /** @const string */
    public const HOOK_AFTER_INSERT_QUERY = self::class . '@afterInsert';
    /** @const string */
    public const HOOK_BEFORE_UPDATE_QUERY = self::class . '@beforeUpdate';
    /** @const string */
    public const HOOK_AFTER_UPDATE_QUERY = self::class . '@afterUpdate';
    /** @const string */
    public const HOOK_BEFORE_DELETE_QUERY = self::class . '@beforeDelete';

    /** @const string */
    public const HOOK_AFTER_ADD = self::class . '@afterAdd';

    /** @const string */
    public const ID_LOAD_ONE = self::class . '@idLoadOne';
    /** @const string */
    public const ID_LOAD_ANY = self::class . '@idLoadAny';

    protected $codecs = [];

    protected static $defaultCodecs = [
        [Persistence\Codec::class],
    ];

    protected static $registry = [
        [Persistence\Sql::class],
        'oci' => [Persistence\Sql\Platform\Oracle::class],
        'oci12' => [Persistence\Sql\Platform\Oracle::class],
        'sqlite' => [Persistence\Sql\Platform\Sqlite::class],
    ];

    /**
     * Connects database.
     *
     * @param string|array $dsn Format as PDO DSN or use "mysql://user:pass@host/db;option=blah",
     *                          leaving user and password arguments = null
     */
    public static function connect($dsn, string $user = null, string $password = null, array $args = []): self
    {
        // parse DSN string
        $dsn = \Atk4\Dsql\Connection::normalizeDsn($dsn, $user, $password);

        switch ($dsn['driverSchema']) {
            case 'mysql':
            case 'oci':
            case 'oci12':
                // Omitting UTF8 is always a bad problem, so unless it's specified we will do that
                // to prevent nasty problems. This is un-tested on other databases, so moving it here.
                // It gives problem with sqlite
                if (strpos($dsn['dsn'], ';charset=') === false) {
                    $dsn['dsn'] .= ';charset=utf8mb4';
                }

                // no break
            case 'pgsql':
            case 'sqlsrv':
            case 'sqlite':
                return Factory::factory(self::resolve($dsn['driverSchema']), [$dsn['dsn'], $dsn['user'], $dsn['pass'], $args]);
            default:
                throw (new Exception('Unable to determine persistence driver type from DSN'))
                    ->addMoreInfo('dsn', $dsn['dsn']);
        }
    }

    public static function resolve($driverSchema)
    {
        return self::$registry[$driverSchema] ?? self::$registry[0];
    }

    /**
     * Disconnect from database explicitly.
     */
    public function disconnect(): void
    {
    }

    public static function getDefaultCodecs()
    {
        $parentClass = get_parent_class(static::class);

        return static::$defaultCodecs + ($parentClass ? $parentClass::getDefaultCodecs() : []);
    }

    public function getCodecs()
    {
        return (array) $this->codecs + $this->getDefaultCodecs();
    }

    public function setCodecs(array $codecs)
    {
        $this->codecs = $codecs;

        return $this;
    }

    /**
     * Associate model with the data driver.
     */
    public function add(Model $m, array $defaults = []): Model
    {
        $m = Factory::factory($m, $defaults);

        if ($m->persistence) {
            if ($m->persistence === $this) {
                return $m;
            }

            throw new Exception('Model is already related to another persistence');
        }

        $m->persistence = $this;
        $m->persistence_data = [];
        $this->initPersistence($m);
        $m = $this->_add($m);

        $this->hook(self::HOOK_AFTER_ADD, [$m]);

        return $m;
    }

    /**
     * Extend this method to enhance model to work with your persistence. Here
     * you can define additional methods or store additional data. This method
     * is executed before model's init().
     */
    protected function initPersistence(Model $m)
    {
    }

    public function query(Model $model): Persistence\Query
    {
    }

    /**
     * Atomic executes operations within one begin/end transaction. Not all
     * persistences will support atomic operations, so by default we just
     * don't do anything.
     *
     * @return mixed
     */
    public function atomic(\Closure $fx)
    {
        return $fx();
    }

    public function getRow(Model $model, $id = null)
    {
        $query = $this->query($model);

        if ($id !== null) {
            $query->whereId($id);
        }

        $rawData = $query->getRow();

        if ($rawData === null) {
            return null;
        }

        return $this->typecastLoadRow($model, $rawData);
    }

    /**
     * Inserts record in database and returns new record ID.
     *
     * @return mixed
     */
    public function insert(Model $model, array $data)
    {
        // don't set id field at all if it's NULL
        if ($model->primaryKey && !isset($data[$model->primaryKey])) {
            unset($data[$model->primaryKey]);

            $this->syncIdSequence($model);
        }

        $data = $this->typecastSaveRow($model, $data);

        $this->query($model)->insert($data)->execute();

        if ($model->primaryKey && isset($data[$model->primaryKey])) {
            $id = (string) $data[$model->primaryKey];

            $this->syncIdSequence($model);
        } else {
            $id = $this->lastInsertId($model);
        }

        return $id;
    }

    /**
     * Updates record in database.
     *
     * @param mixed $id
     */
    public function update(Model $model, $id, array $data)
    {
        $data = $this->typecastSaveRow($model, $data);

        $model->onHook(self::HOOK_AFTER_UPDATE_QUERY, function (Model $model, Persistence\Query $query, $result) use ($data) {
            if ($model->primaryKey && isset($data[$model->primaryKey]) && $model->dirty[$model->primaryKey]) {
                // ID was changed
                $model->id = $data[$model->primaryKey];
            }
        }, [], -1000);

        $result = $this->query($model)->whereId($id)->update($data)->execute();

        //@todo $result->rowCount() is specific to PDO, must be done specific to Query
        // if any rows were updated in database, and we had expressions, reload
        if ($model->reload_after_save === true /* && (!$result || iterator_count($result)) */) {
            $dirty = $model->dirty;
            $model->reload();
            $model->_dirty_after_reload = $model->dirty;
            $model->dirty = $dirty;
        }

        return $result;
    }

    /**
     * Deletes record from database.
     *
     * @param mixed $id
     */
    public function delete(Model $model, $id)
    {
        $this->query($model)->whereId($id)->delete()->execute();
    }

    /**
     * Export all DataSet.
     *
     * @param bool $typecast Should we typecast exported data
     */
    public function export(Model $model, array $fields = null, bool $typecast = true): array
    {
        $data = $this->query($model)->select($fields)->getRows();

        if ($typecast) {
            $data = array_map(function ($row) use ($model) {
                return $this->typecastLoadRow($model, $row);
            }, $data);
        }

        return $data;
    }

    public function lastInsertId(Model $model = null): string
    {
    }

    protected function syncIdSequence(Model $model): void
    {
    }

    public function getDatabasePlatform(): Platforms\AbstractPlatform
    {
        return new Persistence\GenericPlatform();
    }

//     /**
//      * Tries to load data record, but will not fail if record can't be loaded.
//      *
//      * @param mixed $id
//      */
//     public function tryLoad(Model $model, $id): ?array
//     {
//         throw new Exception('Load is not supported.');
//     }

//     /**
//      * Loads a record from model and returns a associative array.
//      *
//      * @param mixed $id
//      */
//     public function load(Model $model, $id): array
//     {
//         $data = $this->tryLoad(
//             $model,
//             $id,
//             ...array_slice(func_get_args(), 2, null, true)
//         );

//         if (!$data) {
//             $noId = $id === self::ID_LOAD_ONE || $id === self::ID_LOAD_ANY;

//             throw (new Exception($noId ? 'No record was found' : 'Record with specified ID was not found', 404))
//                 ->addMoreInfo('model', $model)
//                 ->addMoreInfo('id', $noId ? null : $id)
//                 ->addMoreInfo('scope', $model->scope()->toWords());
//         }

//         return $data;
//     }

    /**
     * Will convert one row of data from native PHP types into
     * persistence types. This will also take care of the "actual"
     * field keys. Example:.
     *
     * In:
     *  [
     *    'name'=>' John Smith',
     *    'age'=>30,
     *    'password'=>'abc',
     *    'is_married'=>true,
     *  ]
     *
     *  Out:
     *   [
     *     'first_name'=>'John Smith',
     *     'age'=>30,
     *     'is_married'=>1
     *   ]
     */
    public function typecastSaveRow(Model $model, array $row): array
    {
        $result = [];
        foreach ($row as $fieldName => $value) {
            // We have no knowledge of the field, it wasn't defined, so
            // we will leave it as-is.
            if (!$model->hasField($fieldName)) {
                $result[$fieldName] = $value;

                continue;
            }

            // Look up field object
            $field = $model->getField($fieldName);

            // check null values for mandatory fields
            if ($value === null && $field->mandatory) {
                throw new Model\Field\ValidationException([$fieldName => 'Mandatory field value cannot be null'], $model);
            }

            // Expression and null cannot be converted.
            if (
                $value instanceof \Atk4\Dsql\Expression
                || $value instanceof \Atk4\Dsql\Expressionable
                || $value === null
            ) {
                $result[$field->getPersistenceName()] = $value;

                continue;
            }

            // typecast if we explicitly want that or there is not serialization enabled
            if ($field->typecast || ($field->typecast === null && $field->serialize === null)) {
                $value = $this->typecastSaveField($field, $value);
            }

            // serialize if we explicitly want that
            if ($field->serialize) {
                $value = $this->serializeSaveField($field, $value);
            }

            // store converted value
            $result[$field->getPersistenceName()] = $value;
        }

        return $result;
    }

    /**
     * Will convert one row of data from Persistence-specific
     * types to PHP native types.
     *
     * NOTE: Please DO NOT perform "actual" field mapping here, because data
     * may be "aliased" from SQL persistences or mapped depending on persistence
     * driver.
     */
    public function typecastLoadRow(Model $model, array $row): array
    {
        $result = [];
        foreach ($row as $fieldName => $value) {
            // We have no knowledge of the field, it wasn't defined, so
            // we will leave it as-is.
            if (!$model->hasField($fieldName)) {
                $result[$fieldName] = $value;

                continue;
            }

            // Look up field object
            $field = $model->getField($fieldName);

            // ignore null values
            if ($value === null) {
                $result[$fieldName] = $value;

                continue;
            }

            // serialize if we explicitly want that
            if ($field->serialize) {
                $value = $this->serializeLoadField($field, $value);
            }

            // typecast if we explicitly want that or there is not serialization enabled
            if ($field->typecast || ($field->typecast === null && $field->serialize === null)) {
                $value = $this->typecastLoadField($field, $value);
            }

            // store converted value
            $result[$fieldName] = $value;
        }

        return $result;
    }

    /**
     * Prepare value of a specific field by converting it to
     * persistence-friendly format.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function typecastSaveField(Model\Field $field, $value)
    {
//         try {
        // use $f->typecast = [typecast_save_callback, typecast_load_callback]
//             if (is_array($field->typecast) && isset($field->typecast[0]) && ($t = $field->typecast[0]) instanceof \Closure) {
//                 return $t($value, $field, $this);
//             }

        $codec = $field->getPersistenceCodec();

        if (!$codec->isEncodable($value)) {
            return $value;
        }

        // we use clones of the object for encoding
        if (is_object($value)) {
            $value = clone $value;
        }

        return $codec->encode($value);
//         } catch (\Exception $e) {
//             throw (new Exception('Unable to typecast field value on save', 0, $e))
//                 ->addMoreInfo('field', $field->short_name);
//         }
    }

    /**
     * Cast specific field value from the way how it's stored inside
     * persistence to a PHP format.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function typecastLoadField(Model\Field $field, $value)
    {
//         try {
        // use $f->typecast = [typecast_save_callback, typecast_load_callback]
//             if (is_array($field->typecast) && isset($field->typecast[1]) && ($t = $field->typecast[1]) instanceof \Closure) {
//                 return $t($value, $field, $this);
//             }

        // only string type fields can use empty string as legit value, for all
        // other field types empty value is the same as no-value, nothing or null
        if ($field->type && $field->type !== 'string' && $value === '') {
            return;
        }

        // we respect null values
        if ($value === null) {
            return;
        }

        return $field->getPersistenceCodec()->decode($value);
//         } catch (\Exception $e) {
//             throw (new Exception('Unable to typecast field value on load', 0, $e))
//                 ->addMoreInfo('field', $field->short_name);
//         }
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastSaveField(Model\Field $f, $value)
    {
        return $value;
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastLoadField(Model\Field $f, $value)
    {
        return $value;
    }

    /**
     * Provided with a value, will perform field serialization.
     * Can be used for the purposes of encryption or storing unsupported formats.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function serializeSaveField(Model\Field $f, $value)
    {
        try {
            // use $f->serialize = [encode_callback, decode_callback]
            if (is_array($f->serialize) && isset($f->serialize[0]) && ($t = $f->serialize[0]) instanceof \Closure) {
                return $t($f, $value, $this);
            }

            // run persistence-specific serialization of field value
            return $this->_serializeSaveField($f, $value);
        } catch (\Exception $e) {
            throw (new Exception('Unable to serialize field value on save', 0, $e))
                ->addMoreInfo('field', $f->short_name);
        }
    }

    /**
     * Provided with a value, will perform field un-serialization.
     * Can be used for the purposes of encryption or storing unsupported formats.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function serializeLoadField(Model\Field $f, $value)
    {
        try {
            // use $f->serialize = [encode_callback, decode_callback]
            if (is_array($f->serialize) && isset($f->serialize[1]) && ($t = $f->serialize[1]) instanceof \Closure) {
                return $t($f, $value, $this);
            }

            // run persistence-specific un-serialization of field value
            return $this->_serializeLoadField($f, $value);
        } catch (\Exception $e) {
            throw (new Exception('Unable to serialize field value on load', 0, $e))
                ->addMoreInfo('field', $f->short_name);
        }
    }

    /**
     * Override this to fine-tune serialization for your persistence.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _serializeSaveField(Model\Field $f, $value)
    {
        switch ($f->serialize === true ? 'serialize' : $f->serialize) {
        case 'serialize':
            return serialize($value);
        case 'json':
            return $this->jsonEncode($f, $value);
        case 'base64':
            if (!is_string($value)) {
                throw (new Exception('Field value can not be base64 encoded because it is not of string type'))
                    ->addMoreInfo('field', $f)
                    ->addMoreInfo('value', $value);
            }

            return base64_encode($value);
        }
    }

    /**
     * Override this to fine-tune un-serialization for your persistence.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _serializeLoadField(Model\Field $f, $value)
    {
        switch ($f->serialize === true ? 'serialize' : $f->serialize) {
        case 'serialize':
            return unserialize($value);
        case 'json':
            return $this->jsonDecode($f, $value, $f->type === 'array');
        case 'base64':
            return base64_decode($value, true);
        }
    }

    /**
     * JSON decoding with proper error treatment.
     *
     * @return mixed
     */
    public function jsonDecode(Model\Field $f, string $json, bool $assoc = true)
    {
        return json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * JSON encoding with proper error treatment.
     *
     * @param mixed $value
     */
    public function jsonEncode(Model\Field $f, $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR, 512);
    }
}
