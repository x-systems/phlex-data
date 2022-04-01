<?php

declare(strict_types=1);

namespace Phlex\Data\Model;

use Phlex\Data\Model;
use Phlex\Data\Persistence;
use Phlex\Data\Persistence\Sql\Expression;

/**
 * Union model combines multiple nested models through a UNION in order to retrieve
 * it's value set. The beauty of this class is that it will add fields transparently
 * and will map them appropriately from the nested model if you request
 * those fields from the union model.
 *
 * For example if you are asking sum(amount), there is no need to fetch any extra
 * fields from sub-models.
 *
 * @property Persistence\Sql $persistence
 *
 * @method Expression expr($expr, array $args = []) forwards to Persistence\Sql::expr using $this as model
 */
class Union extends Model
{
    /** @const string */
    public const HOOK_INIT_SELECT_QUERY = self::class . '@initSelectQuery';

    public const OPTION_FIELD_ACTUAL = self::class . '@fieldActual';

    /**
     * Union model should always be read-only.
     *
     * @var bool
     */
    public $read_only = true;

    public $table_alias = '_tu';

    /**
     * @var array<string, Model>|\Closure
     */
    public $table = [];

    protected $tokenDelimiter = '/';

    protected $actualKeyPrefix = 'union_';

    protected $titleWithCaption = false;

    protected function doInitialize(): void
    {
        $primaryKey = $this->primaryKey;

        $this->primaryKey = null;

        parent::doInitialize();

        $this->primaryKey = $primaryKey;

        $this->addField($primaryKey, ['type' => 'string', 'actual' => $this->getActualPrimaryKey()])->asPrimaryKey();
    }

    protected function getActualPrimaryKey(): string
    {
        return $this->actualKeyPrefix . $this->primaryKey;
    }

    public function getActualKey($key): string
    {
        return $this->actualKeyPrefix . $key;
    }

    public function addNestedModels(array $models)
    {
        foreach ($models as $alias => $model) {
            $this->addNestedModel($alias, $model);
        }

        return $this;
    }

    /**
     * Adds nested model in union.
     */
    public function addNestedModel(string $alias, Model $model)
    {
        $model = $this->table[$alias] = $this->persistence->add($model);

        $model->setOption(Persistence\Query::OPTION_MODEL_STRICT_ONLY_FIELDS);

        $model->addExpression($this->getActualPrimaryKey(), new Expression\Concat($alias . $this->tokenDelimiter, Expression::asIdentifier($model->primaryKey)));

        $model->only_fields[] = $this->getActualPrimaryKey();

        foreach ($this->getFields() as $field) {
            if ($field->isPrimaryKey()) {
                continue;
            }

            $this->addNestedModelField($alias, $field);
        }

        return $this;
    }

    public function addField(string $key, $seed = []): Model\Field
    {
        $field = parent::addField($key, $seed);

        $actual = $field->getOption(self::OPTION_FIELD_ACTUAL, $field->actual ?? []);

        $field->setOption(self::OPTION_FIELD_ACTUAL, $actual);

        foreach (array_keys($this->table) as $alias) {
            $this->addNestedModelField($alias, $field);
        }

        $field->actual = $this->getActualKey($key);

        return $field;
    }

    protected function addNestedModelField(string $nestedModelAlias, Model\Field $unionField)
    {
        $actual = $unionField->getOption(self::OPTION_FIELD_ACTUAL);

        $nestedModel = $this->getNestedModel($nestedModelAlias);

        $key = $unionField->getKey();

        if ($fieldSeed = $actual[$nestedModelAlias] ?? null) {
            $nestedField = $nestedModel->addField($this->getActualKey($key), $fieldSeed);
        } else {
            if ($nestedModel->hasField($key)) {
                $nestedField = $nestedModel->getField($key);
            } else {
                $nestedField = $nestedModel->addExpression($key, new Expression('NULL'));
            }
        }

        $nestedField->setOption(Persistence\Query::OPTION_FIELD_ALIAS, $this->getActualKey($key));

        $nestedModel->only_fields[] = $nestedField->getKey();
    }

    public function getNestedModel(string $alias): Model
    {
        return $this->table[$alias];
    }

    public function getNestedEntity(string $token = null): Model
    {
        [$alias, $id] = explode($this->tokenDelimiter, $token ?? $this->get($this->primaryKey));

        $nestedModel = clone $this->getNestedModel($alias);

        $nestedModel->allFields();

        $nestedModel->unsetOption(Persistence\Query::OPTION_MODEL_STRICT_ONLY_FIELDS);

        foreach ($nestedModel->getFields() as $field) {
            $field->unsetOption(Persistence\Query::OPTION_FIELD_ALIAS);
        }

        return $nestedModel->load($id);
    }

    public function setTitleWithCaption($titleKey = 'captioned_title')
    {
        $this->addCalculatedField($titleKey, fn ($model) => '[' . $model->getNestedEntity()->getCaption() . '] ' . $model->getField($this->titleWithCaption)->get());

        $this->titleWithCaption = $this->titleKey;

        $this->titleKey = $titleKey;

        return $this;
    }
}
