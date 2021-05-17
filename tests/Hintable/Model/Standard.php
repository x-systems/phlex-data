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

        $this->addField($this->fieldName()->x, ['type' => 'string', 'required' => true]);
        $this->addField($this->fieldName()->y, ['type' => 'string', 'required' => true]);
        $this->addField($this->fieldName()->_name, ['type' => 'string', 'required' => true]);

        $this->addField($this->fieldName()->dtImmutable, ['type' => 'datetime', 'required' => true]);
        $this->addField($this->fieldName()->dtInterface, ['type' => 'datetime', 'required' => true]);
        $this->addField($this->fieldName()->dtMulti, ['type' => 'datetime', 'required' => true]);

        $this->addField($this->fieldName()->simpleOneId, ['type' => 'integer']);
        $this->hasOne($this->fieldName()->simpleOne, ['model' => [Simple::class], 'ourFieldName' => $this->fieldName()->simpleOneId]);

        $this->hasMany($this->fieldName()->simpleMany, ['model' => [Simple::class], 'theirFieldName' => Simple::hinting()->fieldName()->refId]);
    }
}
