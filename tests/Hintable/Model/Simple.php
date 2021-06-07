<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Hintable\Model;

use Phlex\Data\Model;

/**
 * @property string   $x     @Phlex\Field()
 * @property int      $refId @Phlex\Field()
 * @property Standard $ref   @Phlex\RefOne()
 */
class Simple extends Model
{
    public $table = 'simple';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addField('x', ['type' => 'string', 'required' => true]);

        $this->addField($this->key()->refId, ['type' => 'integer']);
        $this->hasOne($this->key()->ref, ['model' => [Standard::class], 'ourKey' => $this->key()->refId]);
    }
}
