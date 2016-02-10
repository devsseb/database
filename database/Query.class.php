<?
namespace Db\Object;

class Query
{
	/*
	 * Default table
	 *
	 */
	private $table;

	/*
	 * Parts of a query
	 *
	 */
	private
		$insert = null,
		$values = array(),
		$update = null,
		$from = null,
		$select = array(),
		$join = array(),
		$where = array(),
		$in = null,
		$notIn = null,
		$having = array(),
		$orderBy = array(),
		$groupBy = array(),
		$limit = null,
		$delete = null,
		$foundRows = false
	;

	/*
	 * Query options
	 *
	 */
	private
		$one = false,
		$factorize = true
	;

	/*
	 * Query replaces
	 *
	 */
	private $replaces = array();

	/*
	 * Query result
	 *
	 */
	private $result = null;
	
	public function __construct($table = null)
	{
		$this->table = $table;
	}

	public function getDefaultTable()
	{
		return $this->table;
	}

	public function getReplaces()
	{
		return $this->replaces;
	}

	/*
	 * Set the attribute(s)
	 *
	 * Usage :
	 *	select() = SELECT * by default
	 * 	select('id', 'name')
	 * 	select(array('id', 'name'))
	 *
	 */
	public function select()
	{
		$this->select = array();
		return call_user_func_array(array($this, 'addSelect'), func_get_args());
	}
	
	/*
	 * Add attribute(s)
	 *
	 * Usage :
	 *	Same as select()
	 *
	 */
	public function addSelect()
	{
		$attributes = func_get_args();
		if (exists($attributes, 0) and is_array($attributes[0]))
			$attributes = $attributes[0];
		$this->select = array_merge($this->select, $attributes);
		return $this;
	}
	
	/*
	 * Return attribute(s)
	 *
	 */
	public function getSelect()
	{
		return $this->select;
	}
	
	/*
	 * Set the table(s)
	 *
	 * Usage :
	 *      from('table1', 'table2')
	 *      from(array(table1, table2))
	 *
	 */
	public function from()
	{
		$this->insert = null;
		$this->update = null;
		$this->delete = null;

		$tables = func_get_args();
		$this->from = array_shift($tables);
		foreach ($tables as $table)
			$this->innerJoin($table);
		return $this;
	}
	
	/*
	 * Return table(s)
	 *
	 */
	public function getFrom()
	{
		return $this->from;
	}

	/*
	 * Add a table to join
	 *
	 * Params :
	 *	$table = '<DatabaseTable>[ AS <alias>]
	 *	$attribute : attibute of join table, by default search first link in previous tables
	 *	$onTable = <DatabaseTable> or <alias> : By default search first link in previous tables
	 *	$onAttribute : By default search first link attribute in previous tables
	 *
	 */
	public function join($type, $table, $on)
	{
		$this->join[] = object(
			'type', $type,
			'table', $table,
			'on', $on
		);
		return $this;
	}

	/*
	 * Return join
	 *
	 */
	public function getJoin()
	{
		return $this->join;
	}
	
	/*
	 * Add a table with left join
	 *
	 * $on = array(
	 	object(
		 	'sourceTable' => '',
		 	'sourceAttribute' => '',
		 	'targetTable' => '',
		 	'targetAttribute' => ''
		 ) ,
		 table.attribute ON onTable.Attribute
	 */
	public function leftJoin($table, $on = array())
	{
		return $this->join('left', $table, $on);
	}
	
	public function attributeJoin($table, $attribute)
	{
		$join = false;
		foreach ($table::getLinks() as $link)
			if ($join = strtolower($attribute) == strtolower($link->attributeFactorized)) {
				$this->leftJoin(forward_static_call(array($link->onTableClass, 'getName')) . ' AS ' . $link->attributeFactorized);
				break;
			}

		if (!$join)
			$this->leftJoin($attribute);
	}
	
	/*
	 * Add a table with inner join
	 *
	 * $on like leftJoin()
	 *
	 */
	public function innerJoin($table, $on)
	{
		return $this->join('inner', $table, $on);
	}

	/*
	 * Set first condition
	 *
	 */
	public function where()
	{
		$this->where = array();
		return call_user_func_array(array($this, 'addWhere'), func_get_args());
	}
	
	private function addWhere($where, $replaces = null)
	{
		if (!is_array($replaces)) {
			$replaces = func_get_args();
			array_splice($replaces, 0, 1);
		}
		$this->where[] = $where;
		$this->replaces = array_merge($this->getReplaces(), $replaces);
		
		return $this;
	}
	
	/*
	 * Add a condition of "AND" type
	 *
	 */
	public function andWhere()
	{
		if ($this->where)
			$this->where[] = 'AND';
		return call_user_func_array(array($this, 'addWhere'), func_get_args());
	}
	
	/*
	 * Add a condition of "OR" type
	 *
	 */
	public function orWhere()
	{
		if ($this->where)
			$this->where[] = 'OR';
		return call_user_func_array(array($this, 'addWhere'), func_get_args());
	}
	
	/*
	 * Return condition
	 *
	 */
	public function getWhere()
	{
		return $this->where;
	}
	
	/*
	 * Set attribute order
	 *
	 * Usage :
	 *	orderBy('attribute'[, 'order'])
	 *
	 */
	public function orderBy()
	{
		$this->orderBy = array();
		return call_user_func_array(array($this, 'addOrderBy'), func_get_args());
	}
	
	/*
	 * Add attribute order
	 *
	 * Usage :
	 *	Same as orderBy()
	 *
	 */
	public function addOrderBy($attribute, $order = 'ASC')
	{
		$this->orderBy[] = array($attribute, $order);
		return $this;
	}
	
	/*
	 * Return attributes orders
	 *
	 */
	public function getOrderBy()
	{
		return $this->orderBy;
	}

	/*
	 * Set group by
	 *
	 */
	public function groupBy()
	{
		$this->groupBy = array();
		return call_user_func_array(array($this, 'addGroupBy'), func_get_args());
	}
	
	/*
	 * Add group by
	 *
	 */
	public function addGroupBy()
	{
		$attributes = func_get_args();
		if (exists($attributes, 0) and is_array($attributes[0]))
			$attributes = $attributes[0];
		$this->groupBy = array_merge($this->groupBy, $attributes);
		return $this;
	}
	
	/*
	 * Retrieve group by
	 *
	 */
	public function getGroupBy()
	{
		return $this->groupBy;
	}
	
	/*
	 * Set first having condition
	 *
	 */
	public function having()
	{
		$this->having = array();
		return call_user_func_array(array($this, 'addHaving'), func_get_args());
	}
	
	private function addHaving($having, $replaces = null)
	{
		if (!is_array($replaces)) {
			$replaces = func_get_args();
			array_splice($replaces, 0, 1);
		}
		$this->having[] = $having;
		$this->replaces = array_merge($this->getReplaces(), $replaces);
		
		return $this;
	}
	
	/*
	 * Add a having condition of "AND" type
	 *
	 */
	public function andHaving()
	{
		if ($this->having)
			$this->having[] = 'AND';
		return call_user_func_array(array($this, 'addHaving'), func_get_args());
	}
	
	/*
	 * Add a having condition of "OR" type
	 *
	 */
	public function orHaving()
	{
		if ($this->having)
			$this->having[] = 'OR';
		return call_user_func_array(array($this, 'addHaving'), func_get_args());
	}
	
	/*
	 * Return having condition
	 *
	 */
	public function getHaving()
	{
		return $this->having;
	}
	
	/*
	 * Set limit
	 *
	 */
	public function limit($offset, $total = null)
	{
		$this->limit = object(
			'offset', $total ? $offset : 0,
			'total', $total ?: $offset
		);
		return $this;
	}
	
	/*
	 * Return limit
	 *
	 */
	public function getLimit()
	{
		return $this->limit;
	}
	
	/*
	 * Insert mode
	 *
	 * Usage :
	 *      insert(DatabaseTable <table>)
	 *      insert('table')
	 *
	 */
	public function insert($table = null)
	{
		$this->from = null;
		$this->update = null;
		$this->delete = null;

		$this->insert = gete($table, $this->table);

		return $this;
	}
	
	/*
	 * Return insert table
	 *
	 */
	public function getInsert()
	{
		return $this->insert;
	}
	
	/*
	 * Update mode
	 *
	 * Usage :
	 *      update(DatabaseTable <table>)
	 *      update('table')
	 *
	 */
	public function update($table = null)
	{
		$this->from = null;
		$this->insert = null;
		$this->delete = null;

		$this->update = gete($table, $this->table);

		return $this;
	}
	
	/*
	 * Return update table
	 *
	 */
	public function getUpdate()
	{
		return $this->update;
	}
	
	/*
	 * Set the values for insert or update
	 *
	 * Usage :
	 * 	values(object('<attribute1>', '<values1>', ...))
	 * 	values(array('<attribute1>' => '<values1>', ...))
	 * 	values(array(array or objects))
	 *
	 */
	public function values($values)
	{

		if (is_object($values) or !exists($values, 0))
			$values = array($values);

		array_splice($this->values, count($this->values) - 1, 0, $values);

		return $this;
	}

	/*
	 * Return insert or update values
	 *
	 */
	public function getValues()
	{
		return $this->values;
	}
	
	/*
	 * Delete mode
	 *
	 * Usage :
	 *      delete(DatabaseTable <table>)
	 *      delete('table')
	 *
	 */
	public function delete($table = null)
	{
		$this->from = null;
		$this->insert = null;
		$this->update = null;

		$this->delete = gete($table, $this->table);

		return $this;
	}
	
	/*
	 * Return delete table
	 *
	 */
	public function getDelete()
	{
		return $this->delete;
	}
	
	/*
	 * Define query to retrieve on line
	 *
	 */	
	public function one($one = true)
	{
		$this->one = (bool)$one;
		return $this;
	}
	
	/*
	 * Retrive one line definition of query
	 *
	 */	
	public function isOne()
	{
		return $this->one;
	}
	
	/*
	 * Enable or disable the result factorization
	 *
	 */
	public function factorize($factorize = true)
	{
		$this->factorize = (bool)$factorize;
		return $this;
	}
	
	/*
	 * Return if factorization id enable or disable
	 *
	 */
	public function isFactorized()
	{
		return $this->factorize;
	}

	public function in($field, $query)
	{
		$this->in = array($field, $query);
		return $this;
	}
	
	public function getIn()
	{
		return $this->in;
	}
	
	public function notIn($field, $query)
	{
		$this->notIn = array($field, $query);
		return $this;
	}
	
	public function getNotIn()
	{
		return $this->notIn;
	}

	public function foundRows($foundRows = true)
	{
		$this->foundRows = (bool)$foundRows;
		return $this;
	}
	
	public function isFoundRows()
	{
		return $this->foundRows;
	}

	/*
	 * Execute the query and return result in Entity or EntityCollection
	 *
	 * Usage :
	 *	execute()
	 *	execute(<array replacemements>)
	 *	execute(<replace1>, <replace2>, ...)
	 */
	public function execute($replaces = array())
	{
		if (!is_array($replaces))
			$replaces = func_get_args();
		$replaces = array_merge($this->getReplaces(), $replaces);

		$this->result = new QueryResult($this, $replaces);

		if ($this->result->isFactorized()) {
			if ($this->result->isOne()) {
				if (!$this->result->get())
					return null;
				$entity = '\\Db\\' . $this->result->getEntityName();
				return new $entity($this->result->get());
			}
			return new \Db\Object\Collection($this->result);
		}
		
		return $this->result->get();
	}

	/*
	 * Return the last id generated after an insert
	 *
	 */
	public function getIdInserted()
	{
		return $this->result->getIdInserted();
	}

	public function getFoundRows()
	{
		return $this->result->getFoundRows();
	}

}
