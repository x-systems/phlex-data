<?php

declare(strict_types=1);

namespace Phlex\Data\Persistence\Sql;

use Phlex\Core\DiContainerTrait;
use Phlex\Data\Exception;

class Statement extends Expression
{
    use DiContainerTrait;

    /**
     * Query will use one of the predefined templates. The $mode will contain
     * name of template used. Basically it's part of Query property name -
     * Query::template_[$mode].
     *
     * @var string
     */
    public $mode = 'select';

    /**
     * If no fields are defined, this field is used.
     *
     * @var string|Expression
     */
    public $defaultField = '*';

    protected $consumedInParentheses = true;

    /** @var string */
    protected $template_select = '[with]select[option] [field] [from] [table][join][where][group][having][order][limit]';

    /** @var string */
    protected $template_insert = 'insert[option] into [table_noalias] ([set_fields]) values ([set_values])';

    /** @var string */
    protected $template_replace = 'replace[option] into [table_noalias] ([set_fields]) values ([set_values])';

    /** @var string */
    protected $template_delete = '[with]delete [from] [table_noalias][where][having]';

    /** @var string */
    protected $template_update = '[with]update [table_noalias] set [set] [where]';

    /** @var string */
    protected $template_truncate = 'truncate table [table_noalias]';

    protected $template = '@select';

    /**
     * Name or alias of base table to use when using default join().
     *
     * This is set by table(). If you are using multiple tables,
     * then $masterTable is set to false as it is irrelevant.
     *
     * @var false|string|null
     */
    protected $masterTable;

    protected $selectsMultipleRows = true;

    // {{{ Field specification and rendering

    /**
     * Adds new column to resulting select by querying $field.
     *
     * Examples:
     *  $q->field('name');
     *
     * You can use a dot to prepend table name to the field:
     *  $q->field('user.name');
     *  $q->field('user.name')->field('address.line1');
     *
     * Array as a first argument will specify multiple fields, same as calling field() multiple times
     *  $q->field(['name', 'surname', 'address.line1']);
     *
     * You can pass first argument as Expression or Query
     *  $q->field( $q->expr('2+2'), 'alias');   // must always use alias
     *
     * You can create new Statement for subqueries. Subqueries will be wrapped in
     * brackets.
     *  $q->field( (new Statement())->table('x')... , 'alias');
     *
     * Associative array will assume that "key" holds the field alias.
     * Value may be field name, Expression or Statement.
     *  $q->field(['alias' => 'name', 'alias2' => 'mother.surname']);
     *  $q->field(['alias' => $q->expr(..), 'alias2' => (new Statement())->.. ]);
     *
     * If you need to use funky name for the field (e.g, one containing
     * a dot or a space), you should wrap it into expression:
     *  $q->field($q->expr('{}', ['fun...ky.field']), 'f');
     *
     * @param mixed  $field Specifies field to select
     * @param string $alias Specify alias for this field
     *
     * @return $this
     */
    public function field($field, $alias = null)
    {
        // field is passed as string, may contain commas
        if (is_string($field) && strpos($field, ',') !== false) {
            $field = explode(',', $field);
        }

        // recursively add array fields
        if (is_array($field)) {
            if ($alias !== null) {
                throw (new Exception('Alias must not be specified when $field is an array'))
                    ->addMoreInfo('alias', $alias);
            }

            foreach ($field as $alias => $f) {
                $this->field($f, is_numeric($alias) ? null : $alias);
            }

            return $this;
        }

        // save field in args
        $this->_set_args('field', $alias, $field);

        return $this;
    }

    /**
     * Returns Expression for [field].
     *
     * @param bool $withAlias Should we add aliases, see _render_field_noalias()
     *
     * @return Expression
     */
    protected function _render_field($withAlias = true)
    {
        // will be joined for output
        $ret = [];

        // If no fields were defined, use defaultField
        if (empty($this->args['field'])) {
            return $this->defaultField;
        }

        // process each defined field
        foreach ($this->args['field'] as $alias => $field) {
            // Do not add alias, if:
            //  - we don't want aliases OR
            //  - alias is the same as field OR
            //  - alias is numeric
            if (
                $withAlias === false
                || (is_string($field) && $alias === $field)
                || is_numeric($alias)
                ) {
                $alias = null;
            }

            // Will parameterize the value and escape if necessary
            $ret[] = Expression::asIdentifier($field, $alias);
        }

        return self::asParameterList($ret);
    }

    protected function _render_field_noalias()
    {
        return $this->_render_field(false);
    }

    // }}}

    // {{{ Table specification and rendering

    /**
     * Specify a table to be used in a query.
     *
     * @param mixed  $table Specifies table
     * @param string $alias Specify alias for this table
     *
     * @return $this
     */
    public function table($table, $alias = null)
    {
        // comma-separated table names
        if (is_string($table) && strpos($table, ',') !== false) {
            $table = explode(',', $table);
        }

        // array of tables - recursively process each
        if (is_array($table)) {
            if ($alias !== null) {
                throw (new Exception('You cannot use single alias with multiple tables'))
                    ->addMoreInfo('alias', $alias);
            }

            foreach ($table as $alias => $t) {
                if (is_numeric($alias)) {
                    $alias = null;
                }
                $this->table($t, $alias);
            }

            return $this;
        }

        // if table is set as sub-Statement, then alias is mandatory
        if ($table instanceof self && $alias === null) {
            throw new Exception('If table is set as Statement, then table alias is mandatory');
        }

        if (is_string($table) && $alias === null) {
            $alias = $table;
        }

        // masterTable will be set only if table() is called once.
        // it's used as "default table" when joining with other tables, see join().
        // on multiple calls, masterTable will be false and we won't be able to join easily anymore.
        $this->masterTable = ($this->masterTable === null && $alias !== null ? $alias : false);

        // save table in args
        $this->_set_args('table', $alias, $table);

        return $this;
    }

    /**
     * @param bool $withAlias Should we add aliases, see _render_table_noalias()
     */
    protected function _render_table($withAlias = true)
    {
        // will be joined for output
        $ret = [];

        if (empty($this->args['table'])) {
            return '';
        }

        // process tables one by one
        foreach ($this->args['table'] as $alias => $table) {
            // throw exception if we don't want to add alias and table is defined as Expression
            if ($withAlias === false && $table instanceof self) {
                throw new Exception('Table cannot be Statement in UPDATE, INSERT etc. query modes');
            }

            // Do not add alias, if:
            //  - we don't want aliases OR
            //  - alias is the same as table name OR
            //  - alias is numeric
            if (
                $withAlias === false
                || (is_string($table) && $alias === $table)
                || is_numeric($alias)
                ) {
                $alias = null;
            }

            $ret[] = Expression::asIdentifier($table, $alias);
        }

        return self::asParameterList($ret);
    }

    protected function _render_table_noalias()
    {
        return $this->_render_table(false);
    }

    protected function _render_from()
    {
        return empty($this->args['table']) ? '' : 'from';
    }

    /// }}}

    // {{{ with()

    /**
     * Specify WITH query to be used.
     *
     * @param self   $cursor    Specifies cursor query or array [alias=>query] for adding multiple
     * @param string $alias     Specify alias for this cursor
     * @param array  $fields    Optional array of field names used in cursor
     * @param bool   $recursive Is it recursive?
     *
     * @return $this
     */
    public function with(self $cursor, string $alias, array $fields = null, bool $recursive = false)
    {
        // save cursor in args
        $this->_set_args('with', $alias, [
            'cursor' => $cursor,
            'fields' => $fields,
            'recursive' => $recursive,
        ]);

        return $this;
    }

    /**
     * Recursive WITH query.
     *
     * @param self   $cursor Specifies cursor query or array [alias=>query] for adding multiple
     * @param string $alias  Specify alias for this cursor
     * @param array  $fields Optional array of field names used in cursor
     *
     * @return $this
     */
    public function withRecursive(self $cursor, string $alias, array $fields = null)
    {
        return $this->with($cursor, $alias, $fields, true);
    }

    protected function _render_with()
    {
        // will be joined for output
        $list = [];

        if (empty($this->args['with'])) {
            return '';
        }

        // process each defined cursor
        $isRecursive = false;
        foreach ($this->args['with'] as $alias => ['cursor' => $cursor, 'fields' => $fields, 'recursive' => $recursive]) {
            // is at least one recursive ?
            $isRecursive = $isRecursive || $recursive;

            $list[] = new Expression($fields ? '{alias} [fields] as {{cursor}}' : '{alias} as {{cursor}}', [
                'alias' => $alias,
                'fields' => Expression::asIdentifierList($fields ?: [])->consumedInParentheses(),
                'cursor' => $cursor,
            ]);
        }

        return new Expression('with ' . ($isRecursive ? 'recursive ' : '') . '[subqueries] ', [
            'subqueries' => self::asParameterList($list),
        ]);
    }

    /// }}}

    // {{{ join()

    /**
     * Joins your query with another table. Join will use $main_table
     * to reference the main table, unless you specify it explicitly.
     *
     * Examples:
     *  $q->join('address');         // on user.address_id=address.id
     *  $q->join('address.user_id'); // on address.user_id=user.id
     *  $q->join('address a');       // With alias
     *  $q->join(array('a'=>'address')); // Also alias
     *
     * Second argument may specify the field of the master table
     *  $q->join('address', 'billing_id');
     *  $q->join('address.code', 'code');
     *  $q->join('address.code', 'user.code');
     *
     * Third argument may specify which kind of join to use.
     *  $q->join('address', null, 'left');
     *  $q->join('address.code', 'user.code', 'inner');
     *
     * Using array syntax you can join multiple tables too
     *  $q->join(array('a'=>'address', 'p'=>'portfolio'));
     *
     * You can use expression for more complex joins
     *  $q->join('address',
     *      $q->orExpr()
     *          ->where('user.billing_id=address.id')
     *          ->where('user.technical_id=address.id')
     *  )
     *
     * @param string|array $foreignTables     Table to join with
     * @param mixed        $masterField       Field in master table
     * @param string       $joinKind          'left' or 'inner', etc
     * @param string       $foreignTableAlias Internal, don't use
     *
     * @return $this
     */
    public function join(
        $foreignTables,
        $masterField = null,
        $joinKind = null,
        $foreignTableAlias = null
        ) {
        // If array - add recursively
        if (is_array($foreignTables)) {
            foreach ($foreignTables as $alias => $foreignTable) {
                if (is_numeric($alias)) {
                    $alias = null;
                }

                $this->join($foreignTable, $masterField, $joinKind, $alias);
            }

            return $this;
        }
        $j = [];

        // Split and deduce fields
        // NOTE that this will not allow table names with dots in there !!!
        [$foreignTable, $foreignField] = array_pad(explode('.', $foreignTables, 2), 2, null);

        if (is_object($masterField)) {
            $j['expr'] = $masterField;
        } else {
            // Split and deduce primary table
            [$masterTable, $masterField] = isset($masterField) ? array_pad(explode('.', $masterField, 2), 2, null) : [null, null];

            if ($masterField === null) {
                $masterField = $masterTable;
                $masterTable = null;
            }

            // Identify fields we use for joins
            if ($foreignField === null && $masterField === null) {
                $masterField = $foreignTable . '_id';
            }

            $j['masterTable'] = $masterTable ?? $this->masterTable;
            $j['masterField'] = $masterField ?? 'id';
        }

        $j['foreignTable'] = $foreignTable;
        $j['foreignField'] = $foreignField ?? 'id';

        $j['type'] = $joinKind ?: 'left';
        $j['foreignTableAlias'] = $foreignTableAlias;

        $this->args['join'][] = $j;

        return $this;
    }

    public function _render_join()
    {
        if (!isset($this->args['join'])) {
            return '';
        }

        $pad = true;
        $joins = [];
        foreach ($this->args['join'] as $join) {
            $template = $pad ? ' ' : '';
            $args = [];

            $pad = false;

            $template .= $join['type'] . ' join {{}}';
            $args[] = $join['foreignTable'];

            if ($join['foreignTableAlias'] !== null) {
                $template .= ' {}';
                $args[] = $join['foreignTableAlias'];
            }

            $template .= ' on';

            if (isset($join['expr'])) {
                $template .= ' []';
                $args[] = $join['expr'];
            } else {
                $template .= ' {{}} = {{}}';
                $args[] = ($join['foreignTableAlias'] ?: $join['foreignTable']) . '.' . $join['foreignField'];
                $args[] = ($join['masterTable'] === null ? '' : $join['masterTable'] . '.') . $join['masterField'];
            }

            $joins[] = new Expression($template, $args);
        }

        return Expression::asIdentifierList($joins, ' ');
    }

    // }}}

    // {{{ where() and having() specification and rendering

    /**
     * Adds condition to your statement.
     *
     * Examples:
     *  $q->where('id',1);
     *
     * By default condition implies equality. You can specify a different comparison
     * operator by either including it along with the field or using 3-argument
     * format:
     *  $q->where('id>','1');
     *  $q->where('id','>',1);
     *
     * You may use Expression as any part of the query.
     *  $q->where($q->expr('a=b'));
     *  $q->where('date>',$q->expr('now()'));
     *  $q->where($q->expr('length(password)'),'>',5);
     *
     * If you specify Query as an argument, it will be automatically
     * surrounded by brackets:
     *  $q->where('user_id',($q->subquery()->table('users')->field('id'));
     *
     * You can specify OR conditions by passing single argument - array:
     *  $q->where([
     *      ['a','is',null],
     *      ['b','is',null]
     *  ]);
     *
     * If entry of the OR condition is not an array, then it's assumed to
     * be an expression;
     *
     *  $q->where([
     *      ['age',20],
     *      'age is null'
     *  ]);
     *
     * The above use of OR conditions rely on Expression::or() functionality. See
     * that method for more information.
     *
     * To specify OR conditions
     *  $q->where($q->or()->where('a',1)->where('b',1));
     *
     * @param mixed $field    Field, array for OR or Expression
     * @param mixed $operator Condition such as '=', '>' or 'is not'
     * @param mixed $value    Value. Will be quoted unless you pass expression
     *
     * @return $this
     */
    public function where($field, $operator = null, $value = null)
    {
        $this->getConditionExpression('where')->where(...func_get_args());

        return $this;
    }

    public function having($field, $operator = null, $value = null)
    {
        $this->getConditionExpression('having')->having(...func_get_args());

        return $this;
    }

    protected function getConditionExpression(string $kind)
    {
        if (!isset($this->args[$kind])) {
            if (isset($this->args['where']) || isset($this->args['having'])) {
                throw new Exception('Mixing of WHERE and HAVING conditions not allowed in query expression');
            }

            $this->args[$kind] = $this->and();
        }

        return $this->args[$kind];
    }

    protected function _render_where()
    {
        if (!isset($this->args['where'])) {
            return;
        }

        return new Expression(' where [conditions]', [
            'conditions' => $this->args['where'],
        ]);
    }

    protected function _render_having()
    {
        if (!isset($this->args['having'])) {
            return;
        }

        return new Expression(' having [conditions]', [
            'conditions' => $this->args['having'],
        ]);
    }

    // }}}

    // {{{ group()

    /**
     * Implements GROUP BY functionality. Simply pass either field name
     * as string or expression.
     *
     * @param mixed $group Group by this
     *
     * @return $this
     */
    public function group($group)
    {
        // Case with comma-separated fields
        if (is_string($group) && !$this->isUnescapablePattern($group) && strpos($group, ',') !== false) {
            $group = explode(',', $group);
        }

        if (is_array($group)) {
            foreach ($group as $g) {
                $this->args['group'][] = $g;
            }

            return $this;
        }

        $this->args['group'][] = $group;

        return $this;
    }

    protected function _render_group()
    {
        if (!isset($this->args['group'])) {
            return '';
        }

        return new Expression(' group by [fields]', [
            'fields' => Expression::asIdentifierSoftList($this->args['group'], ', '),
        ]);
    }

    // }}}

    // {{{ Set field implementation

    /**
     * Sets field value for INSERT or UPDATE statements.
     *
     * @param string|array $field Name of the field
     * @param mixed        $value Value of the field
     *
     * @return $this
     */
    public function set($field, $value = null)
    {
//         if ($value === false) {
//             throw (new Exception('Value "false" is not supported by SQL'))
//                 ->addMoreInfo('field', $field)
//                 ->addMoreInfo('value', $value);
//         }

        if (is_array($value)) {
            throw (new Exception('Array values are not supported by SQL'))
                ->addMoreInfo('field', $field)
                ->addMoreInfo('value', $value);
        }

        if (is_array($field)) {
            foreach ($field as $key => $value) {
                $this->set($key, $value);
            }

            return $this;
        }

        if (is_string($field) || $field instanceof Expressionable) {
            $this->args['set'][] = compact('field', 'value');
        } else {
            throw (new Exception('Field name should be string or Expressionable'))
                ->addMoreInfo('field', $field);
        }

        return $this;
    }

    protected function _render_set()
    {
        $list = [];
        foreach ($this->args['set'] ?? [] as $pair) {
            $list[] = new Expression('{field}=[value]', $pair);
        }

        return $list ? self::asParameterList($list, ', ') : '';
    }

    protected function _render_set_fields()
    {
        if ($this->args['set']) {
            return Expression::asIdentifierList(array_column($this->args['set'], 'field'));
        }
    }

    protected function _render_set_values()
    {
        if ($this->args['set']) {
            return self::asParameterList(array_column($this->args['set'], 'value'));
        }
    }

    // }}}

    // {{{ Option

    /**
     * Set options for particular mode.
     *
     * @param mixed  $option
     * @param string $mode   select|insert|replace
     *
     * @return $this
     */
    public function option($option, $mode = 'select')
    {
        // Case with comma-separated options
        if (is_string($option) && strpos($option, ',') !== false) {
            $option = explode(',', $option);
        }

        if (is_array($option)) {
            foreach ($option as $opt) {
                $this->args['option'][$mode][] = $opt;
            }

            return $this;
        }

        $this->args['option'][$mode][] = $option;

        return $this;
    }

    protected function _render_option()
    {
        if (!isset($this->args['option'][$this->mode])) {
            return '';
        }

        return ' ' . implode(' ', $this->args['option'][$this->mode]);
    }

    /**
     * Creates 'select exists' query based on the query object.
     *
     * @return self
     */
    public function exists()
    {
        return (new static())->mode('select')->option('exists')->field($this);
    }

    // }}}

    // {{{ Query Modes

    /**
     * Execute select statement.
     */
    public function select()
    {
        return $this->mode('select');
    }

    /**
     * Execute insert statement.
     */
    public function insert()
    {
        return $this->mode('insert');
    }

    /**
     * Execute update statement.
     */
    public function update()
    {
        return $this->mode('update');
    }

    /**
     * Execute replace statement.
     */
    public function replace()
    {
        return $this->mode('replace');
    }

    /**
     * Execute delete statement.
     */
    public function delete()
    {
        return $this->mode('delete');
    }

    /**
     * Execute truncate statement.
     */
    public function truncate()
    {
        return $this->mode('truncate');
    }

    // }}}

    // {{{ Limit

    /**
     * Limit how many rows will be returned.
     *
     * @param int $cnt   Number of rows to return
     * @param int $shift Offset, how many rows to skip
     *
     * @return $this
     */
    public function limit($cnt, $shift = null)
    {
        $this->args['limit'] = [
            'cnt' => $cnt,
            'shift' => $shift,
        ];

        return $this;
    }

    public function _render_limit()
    {
        if (isset($this->args['limit'])) {
            return ' limit ' .
                (int) $this->args['limit']['shift'] .
                ', ' .
                (int) $this->args['limit']['cnt'];
        }
    }

    // }}}

    // {{{ Order

    /**
     * Orders results by field or Expression. See documentation for full
     * list of possible arguments.
     *
     * $q->order('name');
     * $q->order('name desc');
     * $q->order('name desc, id asc')
     * $q->order('name',true);
     *
     * @param string|array|Expressionable $order Order by
     * @param string|bool                 $desc  true to sort descending
     *
     * @return $this
     */
    public function order($order, $desc = null)
    {
        // Case with comma-separated fields or first argument being an array
        if (is_string($order) && strpos($order, ',') !== false) {
            $order = explode(',', $order);
        }

        if (is_array($order)) {
            if ($desc !== null) {
                throw new Exception(
                    'If first argument is array, second argument must not be used'
                    );
            }
            foreach (array_reverse($order) as $o) {
                $this->order($o);
            }

            return $this;
        }

        // First argument may contain space, to divide field and ordering keyword.
        // Explode string only if ordering keyword is 'desc' or 'asc'.
        if ($desc === null && is_string($order) && strpos($order, ' ') !== false) {
            $_chunks = explode(' ', $order);
            $_desc = strtolower(array_pop($_chunks));
            if (in_array($_desc, ['desc', 'asc'], true)) {
                $order = implode(' ', $_chunks);
                $desc = $_desc;
            }
        }

        if (is_bool($desc)) {
            $desc = $desc ? 'desc' : '';
        } elseif (strtolower($desc ?? '') === 'asc') {
            $desc = '';
        }
        // no else - allow custom order like "order by name desc nulls last" for Oracle

        $this->args['order'][] = [$order, $desc];

        return $this;
    }

    public function _render_order()
    {
        if (!isset($this->args['order'])) {
            return '';
        }

        $x = [];
        foreach ($this->args['order'] as $tmp) {
            [$arg, $desc] = $tmp;
            $x[] = $this->consume($arg, self::ESCAPE_IDENTIFIER_SOFT) . ($desc ? (' ' . $desc) : '');
        }

        return ' order by ' . implode(', ', array_reverse($x));
    }

    // }}}

    public function __debugInfo()
    {
        $arr = [
            'R' => false,
            'mode' => $this->mode,
            //'template'   => $this->template,
            //'params'     => $this->params,
            //'connection' => $this->connection,
            //'main_table' => $this->main_table,
            //'args'       => $this->args,
        ];

        try {
            $arr['R'] = $this->getDebugQuery();
        } catch (\Exception $e) {
            $arr['R'] = $e->getMessage();
        }

        return $arr;
    }

    // {{{ Miscelanious

    /**
     * Switch template for this query. Determines what would be done
     * on execute.
     *
     * By default it is in SELECT mode
     *
     * @param string $mode
     *
     * @return $this
     */
    public function mode($mode)
    {
        if (isset($this->{'template_' . $mode})) {
            $this->mode = $mode;
            $this->template = '@' . $mode;
        } else {
            throw (new Exception('Query does not have this mode'))
                ->addMoreInfo('mode', $mode);
        }

        $this->selectsMultipleRows = ($mode === 'select');

        return $this;
    }

    /**
     * Returns Expression object for NOW() or CURRENT_TIMESTAMP() method.
     */
    public function exprNow(int $precision = null): Expression
    {
        return $this->expr(
            'current_timestamp(' . ($precision !== null ? '[]' : '') . ')',
            $precision !== null ? [$precision] : []
        );
    }

    public static function subquery(): self
    {
        return new self();
    }

    public function sequence($sequence)
    {
        return $this;
    }

    protected function _render_concat()
    {
        return Expression::asParameterList($this->args['custom'], ' || ');
    }

    protected function _render_group_concat()
    {
        return new Expression('group_concat({field}, [delimiter])', $this->args['custom']);
    }

    /**
     * Sets value in args array. Doesn't allow duplicate aliases.
     *
     * @param string $what  Where to set it - table|field
     * @param string $alias Alias name
     * @param mixed  $value Value to set in args array
     */
    protected function _set_args($what, $alias, $value)
    {
        // save value in args
        if ($alias === null) {
            $this->args[$what][] = $value;
        } else {
            // don't allow multiple values with same alias
            if (isset($this->args[$what][$alias])) {
                throw (new Exception('Alias should be unique'))
                    ->addMoreInfo('what', $what)
                    ->addMoreInfo('alias', $alias);
            }

            $this->args[$what][$alias] = $value;
        }
    }

    /// }}}
}
