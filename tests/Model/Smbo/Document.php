<?php

declare(strict_types=1);

namespace Phlex\Data\Tests\Model\Smbo;

use Phlex\Data\Model;

class Document extends Model
{
    public $table = 'document';

    protected function init(): void
    {
        parent::init();

        // Documest is sent from one Contact to Another
        $this->hasOne('contact_from_id', ['model' => [Contact::class]]);
        $this->hasOne('contact_to_id', ['model' => [Contact::class]]);

        $this->addField('doc_type', ['enum' => ['invoice', 'payment']]);

        $this->addField('amount', ['type' => 'money']);
    }
}
