<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Hintable\Model;

use Phlex\Data\Model;

/**
 * @property string                       $x           @Phlex\Field()
 * @property string                       $y           @Phlex\Field(field_name="yy")
 * @property string                       $_name       @Phlex\Field(field_name="name") Property Model::name is defined, so we need to use different property name
 * @property \DateTimeImmutable           $dtImmutable @Phlex\Field()
 * @property \DateTimeInterface           $dtInterface @Phlex\Field()
 * @property \DateTime|\DateTimeImmutable $dtMulti     @Phlex\Field()
 * @property int                          $simpleOneId @Phlex\Field()
 * @property Simple                       $simpleOne   @Phlex\RefOne()
 * @property Simple                       $simpleMany  @Phlex\RefMany()
 */
class Standard extends Model
{
    public $table = 'prefix_standard';

    protected function doInitialize(): void
    {
        parent::doInitialize();

        $this->addField($this->key()->x, ['type' => 'string', 'required' => true]);
        $this->addField($this->key()->y, ['type' => 'string', 'required' => true]);
        $this->addField($this->key()->_name, ['type' => 'string', 'required' => true]);

        $this->addField($this->key()->dtImmutable, ['type' => 'datetime', 'required' => true]);
        $this->addField($this->key()->dtInterface, ['type' => 'datetime', 'required' => true]);
        $this->addField($this->key()->dtMulti, ['type' => 'datetime', 'required' => true]);

        $this->addField($this->key()->simpleOneId, ['type' => 'integer']);
        $this->hasOne($this->key()->simpleOne, ['theirModel' => [Simple::class], 'ourKey' => $this->key()->simpleOneId]);

        $this->hasMany($this->key()->simpleMany, ['theirModel' => [Simple::class], 'theirKey' => Simple::hint()->key()->refId]);
    }
}
