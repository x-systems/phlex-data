<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Reference;

use Phlex\Data\Model;
use Phlex\Data\Persistence;

/**
 * ContainsOne reference.
 */
class ContainsOne extends Model\Reference
{
    /**
     * Field type.
     *
     * @var string
     */
    public $type = 'array';

    /**
     * Is it system field?
     *
     * @var bool
     */
    public $system = true;

    /**
     * Array with UI flags like editable, visible and hidden.
     *
     * By default hasOne relation ID field should be editable in forms,
     * but not visible in grids. UI should respect these flags.
     *
     * @var array
     */
    public $ui = [];

    /**
     * Required! We need table alias for internal use only.
     *
     * @var string
     */
    protected $table_alias = 'tbl';

    /**
     * Reference\ContainsOne will also add a field corresponding
     * to 'ourKey' unless it exists of course.
     */
    protected function doInitialize(): void
    {
        parent::doInitialize();

        if (!$this->ourKey) {
            $this->ourKey = $this->link;
        }

        $ourModel = $this->getOurModel();
        $ourKey = $this->getOurKey();

        if (!$ourModel->hasElement($ourKey)) {
            $ourModel->addField($ourKey, [
                'type' => $this->type,
                'referenceLink' => $this->link,
                'system' => $this->system,
                'caption' => $this->caption, // it's ref models caption, but we can use it here for field too
                'ui' => array_merge([
                    'visible' => false, // not visible in UI Table, Grid and Crud
                    'editable' => true, // but should be editable in UI Form
                ], $this->ui),
            ]);
        }
    }

    protected function getDefaultPersistence(Model $theirModel)
    {
        $persistence = new Persistence\Array_([
            $this->table_alias => $this->getOurModel()->isEntity() && $this->getOurFieldValue() !== null ? [1 => $this->getOurFieldValue()] : [],
        ]);

        return $persistence->setCodecs($this->getPersistence()->getCodecs());
    }

    /**
     * Returns referenced model with loaded data record.
     */
    public function ref(array $defaults = []): Model
    {
        $theirModel = $this->createTheirModel(array_merge($defaults, [
            'table' => $this->table_alias,
        ]));

        foreach ([Model::HOOK_AFTER_SAVE, Model::HOOK_AFTER_DELETE] as $spot) {
            $this->onHookToTheirModel($theirModel, $spot, function ($theirModel) {
                $this->getOurModel()->save([
                    $this->getOurKey() => $theirModel->toQuery()->getRow() ?: null,
                ]);
            });
        }

        // try to load any (actually only one possible) record
        return $theirModel->tryLoadAny();
    }

    public function createTheirModel(array $defaults = []): Model
    {
        $ourModel = $this->getOurModel();
        $theirModel = parent::createTheirModel($defaults);

        $theirModel->setOption(self::OPTION_ROOT_MODEL, $ourModel->getOption(self::OPTION_ROOT_MODEL, $ourModel));

        return $theirModel;
    }
}
