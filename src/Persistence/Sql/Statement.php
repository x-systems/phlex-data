<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

class Statement extends Expression
{
    /**
     * Query will use one of the predefined templates. The $mode will contain
     * name of template used. Basically it's part of Query property name -
     * Query::template_[$mode].
     *
     * @var string
     */
    public $mode = Query::MODE_SELECT;
    
    /**
     * If no fields are defined, this field is used.
     *
     * @var string|Expression
     */
    public $defaultField = '*';

    /** @var string */
    protected $templates = [
        Query::MODE_SELECT      => '[with]select[option] [field] [from] [table][join][where][group][having][order][limit]',
        Query::MODE_INSERT      => 'insert[option] into [table_noalias] ([set_fields]) values ([set_values])',
        Query::MODE_REPLACE     => 'replace[option] into [table_noalias] ([set_fields]) values ([set_values])',
        Query::MODE_DELETE      => '[with]delete [from] [table_noalias][where][having]',
        Query::MODE_UPDATE      => '[with]update [table_noalias] set [set] [where]',
        Query::MODE_TRUNCATE    => 'truncate table [table_noalias]',
    ];
}
