<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field;

use Phlex\Core\Factory;
use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

/**
 * Reference implements a link between one model and another. The basic components for
 * a reference is ability to generate the destination model, which is returned through
 * createTheirModel() and that's pretty much it.
 *
 * It's possible to extend the basic reference with more meaningful references.
 *
 * @method Model getOwner() our model
 */
class Reference extends Model\Field
{
    use \Phlex\Core\InitializerTrait;

    /**
     * Option to use for linking a model to this reference when it is theirModel.
     */
    public const OPTION_MODEL_OWNER = self::class . '@model_owner';

    /**
     * Option to use with Model to contain reference to the root model for "contained" references.
     *
     * Useful for containsOne/Many implementation in case of
     * SQL_Model->containsOne()->hasOne() structure to get back to SQL persistence
     * from Array persistence used in containsOne model
     */
    public const OPTION_ROOT_MODEL = self::class . '@root_model';

    public $access = self::ACCESS_GET;

    public $persist = self::PERSIST_NONE;

    /**
     * Use this alias for related entity by default. This can help you
     * if you create sub-queries or joins to separate this from main
     * table. The table_alias will be uniquely generated.
     *
     * @var string
     */
    protected $table_alias;

    /**
     * Definition of the destination their model, that can be either an object, a
     * callback or a string. This can be defined during initialization and
     * then used inside createTheirModel() to fully populate and associate with
     * persistence.
     *
     * @var Model|\Closure|array
     */
    public $theirModel;

    /**
     * This is an optional property which can be used by your implementation
     * to store field-level relationship based on a common field matching.
     *
     * @var string|null
     */
    protected $ourKey;

    /**
     * This is an optional property which can be used by your implementation
     * to store field-level relationship based on a common field matching.
     *
     * @var string|null
     */
    protected $theirKey;

    /**
     * Caption of the reeferenced model. Can be used in UI components, for example.
     * Should be in plain English and ready for proper localization.
     *
     * @var string|null
     */
    public $caption;

    protected function onHookToOurModel(Model $model, string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $name = $this->elementId; // use static function to allow this object to be GCed

        return $model->onHookDynamic(
            $spot,
            static fn (Model $model) => $model->getField($name),
            $fx,
            $args,
            $priority
        );
    }

    protected function onHookToTheirModel(Model $model, string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $modelOwnerReference = $model->getOption(self::OPTION_MODEL_OWNER);
        if ($modelOwnerReference !== null && $modelOwnerReference !== $this) {
            throw new Exception('Model owner reference unexpectedly already set');
        }
        $model->setOption(self::OPTION_MODEL_OWNER, $this);

        return $model->onHookDynamic(
            $spot,
            static fn (Model $model) => $model->getOption(self::OPTION_MODEL_OWNER),
            $fx,
            $args,
            $priority
        );
    }

    protected function doInitialize(): void
    {
        $this->initTableAlias();
    }

    public function getOurModel(): Model
    {
        return $this->getOwner();
    }

    /**
     * Create destination model that is linked through this reference. Will apply
     * necessary conditions.
     *
     * IMPORTANT: the returned model must be a fresh clone or freshly built from a seed
     */
    public function createTheirModel(array $defaults = []): Model
    {
        // set table_alias
        $defaults['table_alias'] ??= $this->table_alias;

        if (is_object($this->theirModel)) {
            if ($this->theirModel instanceof \Closure) {
                // if model is Closure, then call the closure which should return a model
                $theirModel = ($this->theirModel)($this->getOurModel(), $this, $defaults);
            } else {
                // if model is set, then use clone of this model
                $theirModel = clone $this->theirModel;
            }
        } else {
            // add model from seed
            $modelDefaults = $this->theirModel;
            $theirModelSeed = [$modelDefaults[0]];
            unset($modelDefaults[0]);
            $defaults = array_merge($modelDefaults, $defaults);

            $theirModel = Factory::factory($theirModelSeed, $defaults);
        }

        $this->addToPersistence($theirModel, $defaults);

        return $theirModel;
    }

    public function getOurField(): Model\Field
    {
        return $this->getOurModel()->getField($this->getOurKey());
    }

    public function getOurKey(): string
    {
        return $this->ourKey ?: $this->getOurModel()->primaryKey;
    }

    public function getTheirKey(Model $theirModel = null): string
    {
        if ($this->theirKey !== null) {
            return $this->theirKey;
        }

        $theirModel ??= $this->createTheirModel();

        return $theirModel->primaryKey;
    }

    /**
     * @return mixed
     */
    protected function getOurFieldValue()
    {
        return $this->getOurField()->get();
    }

    public function getTheirFieldValue(Model $theirModel = null)
    {
        $theirModel ??= $this->createTheirModel();

        return $theirModel->get($this->getTheirKey($theirModel));
    }

    protected function initTableAlias(): void
    {
        if (!$this->table_alias) {
            $ourModel = $this->getOurModel();

            $aliasFull = $this->getKey();
            $alias = preg_replace('~_(' . preg_quote($ourModel->primaryKey, '~') . '|id)$~', '', $aliasFull);
            $alias = preg_replace('~([0-9a-z]?)[0-9a-z]*[^0-9a-z]*~i', '$1', $alias);
            if (isset($ourModel->table_alias)) {
                $aliasFull = $ourModel->table_alias . '_' . $aliasFull;
                $alias = preg_replace('~^_(.+)_[0-9a-f]{12}$~', '$1', $ourModel->table_alias) . '_' . $alias;
            }
            $this->table_alias = '_' . $alias . '_' . substr(md5($aliasFull), 0, 12);
        }
    }

    protected function addToPersistence(Model $theirModel, array $defaults = []): void
    {
        if (!$theirModel->persistence && $persistence = $this->getDefaultPersistence($theirModel)) {
            $persistence->add($theirModel, $defaults);
        }

        // set model caption
        if ($this->caption !== null) {
            $theirModel->caption = $this->caption;
        }
    }

    /**
     * Returns default persistence for theirModel.
     *
     * @return Persistence|false
     */
    protected function getDefaultPersistence(Model $theirModel)
    {
        $ourModel = $this->getOurModel();

        return $ourModel->getOption(self::OPTION_ROOT_MODEL, $ourModel)->persistence ?: false;
    }

    /**
     * Returns referenced entity, in this case without any extra conditions.
     * However descendant relationship types may override this to imply conditions.
     */
    public function getTheirEntity(array $defaults = []): Model
    {
        return $this->createTheirModel($defaults);
    }

    public function getQueryArguments($operator, $value): array
    {
        return $this->getOurField()->getCodec()->getQueryArguments($operator, $value);
    }

    public function get()
    {
        return $this->getTheirEntity();
    }

    public function getConditionValueTitle($value): ?string
    {
        $model = $this->getOwner();

        // make sure we set the value in the Model and fake it as loaded
        $model->toEntity([$model->primaryKey => 0, $this->getOurKey() => $value]);

        // then take the title
        $title = $model->get($this->getKey())->getTitle();
        if ($title === $value) {
            $title = null;
        }

        return $title;
    }

    // {{{ Debug Methods

    /**
     * List of properties to show in var_dump.
     *
     * @var array<int|string, string>
     */
    protected $__debug_fields = ['link', 'model', 'ourKey', 'theirKey'];

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
        $arr = [];
        foreach ($this->__debug_fields as $k => $v) {
            $k = is_int($k) ? $v : $k;
            if (isset($this->{$v})) {
                $arr[$k] = $this->{$v};
            }
        }

        return $arr;
    }

    // }}}
}
