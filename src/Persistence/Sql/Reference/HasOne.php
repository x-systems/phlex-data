<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql\Reference;

use Phlex\Data\Exception;
use Phlex\Data\Model;
use Phlex\Data\Persistence;

class HasOne extends \Phlex\Data\Model\Reference\HasOne
{
    /**
     * Creates expression which sub-selects a field inside related model.
     *
     * Returns Expression in case you want to do something else with it.
     *
     * @param string|Model\Field|array $ourKey or [$field, ..defaults]
     */
    public function addField($ourKey, string $theirKey = null): Persistence\Sql\Field\Expression
    {
        if (is_array($ourKey)) {
            $defaults = $ourKey;
            if (!isset($defaults[0])) {
                throw (new Exception('Field name must be specified'))
                    ->addMoreInfo('field', $ourKey);
            }
            $ourKey = $defaults[0];
            unset($defaults[0]);
        } else {
            $defaults = [];
        }

        if ($theirKey === null) {
            $theirKey = $ourKey;
        }

        $ourModel = $this->getOurModel();

        // if caption is not defined in $defaults -> get it directly from the linked model field $theirKey
        $defaults['caption'] ??= $ourModel->refModel($this->link)->getField($theirKey)->getCaption();

        /** @var Persistence\Sql\Field\Expression $fieldExpression */
        $fieldExpression = $ourModel->addExpression($ourKey, array_merge(
            [
                function (Model $ourModel) use ($theirKey) {
                    // remove order if we just select one field from hasOne model
                    // that is mandatory for Oracle
                    return $ourModel->refLink($this->link)->toQuery()->field($theirKey)->reset('order');
                },
            ],
            $defaults,
            [
                // to be able to change field, but not save it
                // afterSave hook will take care of the rest
                'read_only' => false,
                'never_save' => true,
            ]
        ));

        // Will try to execute last
        $this->onHookToOurModel($ourModel, Model::HOOK_BEFORE_SAVE, function (Model $ourModel) use ($ourKey, $theirKey) {
            // if title field is changed, but reference ID field (ourKey)
            // is not changed, then update reference ID field value
            if ($ourModel->isDirty($ourKey) && !$ourModel->isDirty($this->ourKey)) {
                $theirModel = $this->createTheirModel();

                $theirModel->addCondition($theirKey, $ourModel->get($ourKey));
                $ourModel->set($this->getOurKey(), $theirModel->toQuery()->field($theirModel->primaryKey));
                $ourModel->_unset($ourKey);
            }
        }, [], 21);

        return $fieldExpression;
    }

    /**
     * Add multiple expressions by calling addField several times. Fields
     * may contain 3 types of elements:.
     *
     * [ 'name', 'surname' ] - will import those fields as-is
     * [ 'full_name' => 'name', 'day_of_birth' => ['dob', 'type'=>'date'] ] - use alias and options
     * [ ['dob', 'type' => 'date'] ]  - use options
     *
     * You may also use second param to specify parameters:
     *
     * addFields(['from', 'to'], ['type' => 'date']);
     *
     * @return $this
     */
    public function addFields(array $fields = [], array $defaults = [])
    {
        foreach ($fields as $ourKey => $ourFieldDefaults) {
            $ourFieldDefaults = array_merge($defaults, (array) $ourFieldDefaults);

            if (!isset($ourFieldDefaults[0])) {
                throw (new Exception('Incorrect definition for addFields. Field name must be specified'))
                    ->addMoreInfo('ourKey', $ourKey)
                    ->addMoreInfo('ourFieldDefaults', $ourFieldDefaults);
            }

            $theirKey = $ourFieldDefaults[0];

            if (is_int($ourKey)) {
                $ourKey = $theirKey;
            }

            $ourFieldDefaults[0] = $ourKey;

            $this->addField($ourFieldDefaults, $theirKey);
        }

        return $this;
    }

    /**
     * Creates model that can be used for generating sub-query actions.
     */
    public function refLink(array $defaults = []): Model
    {
        $theirModel = $this->createTheirModel($defaults);

        return $theirModel->addCondition(
            $this->getTheirKey($theirModel),
            $this->referenceOurValue()
        );
    }

    /**
     * Navigate to referenced model.
     */
    public function ref(array $defaults = []): Model
    {
        $theirModel = parent::ref($defaults);
        $ourModel = $this->getOurModel();

        if (!isset($ourModel->persistence) || !($ourModel->persistence instanceof Persistence\Sql)) {
            return $theirModel;
        }

        $theirKey = $this->getTheirKey($theirModel);
        $ourField = $this->getOurField();

        // At this point the reference
        // if ourKey is the primaryKey and is being used in the reference
        // we should persist the relation in condtition
        // example - $model->load(1)->ref('refLink')->import($rows);
        if ($ourModel->isLoaded() && !$theirModel->isLoaded()) {
            if ($ourField->isPrimaryKey()) {
                return $theirModel->getEntitySet()
                    ->addCondition($theirKey, $this->getOurFieldValue());
            }
        }

        // handles the deep traversal using an expression
        $ourFieldExpression = $ourModel->toQuery()->field($ourField);

        $theirModel->getEntitySet(true)
            ->addCondition($theirKey, $ourFieldExpression);

        return $theirModel;
    }

    /**
     * Add a title of related entity as expression to our field.
     *
     * $order->hasOne('user_id', 'User')->addTitle();
     *
     * This will add expression 'user' equal to ref('user_id')['name'];
     *
     * This method returns newly created expression field.
     */
    public function addTitle(array $defaults = []): Persistence\Sql\Field\Expression
    {
        $ourModel = $this->getOurModel();

        $key = $defaults['field'] ?? preg_replace('~_(' . preg_quote($ourModel->primaryKey, '~') . '|id)$~', '', $this->link);

        if ($ourModel->hasField($key)) {
            throw (new Exception('Field with this name already exists. Please set title field name manually addTitle([\'field\'=>\'field_name\'])'))
                ->addMoreInfo('field', $key);
        }

        /** @var Persistence\Sql\Field\Expression $fieldExpression */
        $fieldExpression = $ourModel->addExpression($key, array_replace_recursive(
            [
                function (Model $ourModel) {
                    $theirModel = $ourModel->refLink($this->link);

                    return $theirModel->toQuery()->field($theirModel->titleKey)->reset('order');
                },
                'type' => null,
                'ui' => ['editable' => false, 'visible' => true],
            ],
            $defaults,
            [
                // to be able to change title field, but not save it
                // afterSave hook will take care of the rest
                'read_only' => false,
                'never_save' => true,
            ]
        ));

        // Will try to execute last
        $this->onHookToOurModel($ourModel, Model::HOOK_BEFORE_SAVE, function (Model $ourModel) use ($key) {
            // if title field is changed, but reference ID field (our_field)
            // is not changed, then update reference ID field value
            if ($ourModel->isDirty($key) && !$ourModel->isDirty($this->ourKey)) {
                $theirModel = $this->createTheirModel();

                $theirModel->addCondition($theirModel->titleKey, $ourModel->get($key));
                $ourModel->set($this->getOurKey(), $theirModel->toQuery()->field($theirModel->primaryKey));
            }
        }, [], 20);

        // Set ID field as not visible in grid by default
        if (!array_key_exists('visible', $this->getOurField()->ui)) {
            $this->getOurField()->ui['visible'] = false;
        }

        return $fieldExpression;
    }

    /**
     * Add a title of related entity as expression to our field.
     *
     * $order->hasOne('user_id', 'User')->addTitle();
     *
     * This will add expression 'user' equal to ref('user_id')['name'];
     *
     * @return $this
     */
    public function withTitle(array $defaults = [])
    {
        $this->addTitle($defaults);

        return $this;
    }
}
