<?
namespace Db\Object;

abstract class Connector
{
	/*
	 * Return an array of names of attributes by database contants T_*
	 *
	 */
	abstract public function getTypes();
	
	/*
	 * Return list of tables
	 *
	 */
	abstract public function getTables();

	/*
	 * Return list of attributes of $table
	 *
	 */
	abstract public function getAttributes($table);
	
	/*
	 * Return query for table creation
	 *
	 */
	abstract public function getCreateTable($table, $columns);
	
	/*
	 * Return query for alter table
	 *
	 */
	abstract public function getAlterTable($table, $columns);
	
	private $pdo, $sql, $options, $dsn, $query_total = 0;

	/*
	 * Connector contructor
	 *
	 */
	public function __construct($pdo, $options)
	{
		if (method_exists($this, '_construct'))
			$this->_construct();
			
		$this->connect($pdo, $options);
	}
	
	/*
	 * Connect the connector to database
	 *
	 */
	public function connect($pdo, $options)
	{
		$this->options = $options;
		
		$this->sql = 'Connecting ...';
		try {
			$this->pdo = new \PDO($this->dsn = $pdo->dsn, $pdo->user, $pdo->password, $pdo->driver_options);
		}
		catch (Exception $exception) {
			$this->error($exception->getMessage());
		}
		
	}
	
	/*
	 * Throw an error and send mail if options contains adress
	 *
	 */
	public function error($message)
	{
		$message = $_SERVER['REQUEST_URI'] . chr(10) . $message;

		foreach (get($this->options, k('mails'), array()) as $mail) {
			mail($mail, 'SQL Error on ' . $this->dsn, $message . chr(10) . chr(10) . $this->sql);
		}
		throw new \Exception($message);
	}
	
	/*
	 * Return the total of query executed on database
	 *
	 */
	public function getTotal()
	{
		return $this->query_total;
	}

	/*
	 * Returns the constant from  T_* that corresponds to the $type
	 *
	 */
	protected function getType($type)
	{
		$typeFound = null;
		$typeSearch = strtoupper($type);
		$types = $this->getTypes();
		foreach ($types as $key => $values) {
			foreach ($values as $value) {
				if ($value == $typeSearch) {
					$typeFound = $key;
					break;
				}
			}
			if ($typeFound)
				break;
		}
		
		if (!$typeFound) {
			preg_match('/[A-Z]+/', $typeSearch, $match);
			$typeSearch = get($match, k(0));
			foreach ($types as $key => $values) {
				foreach ($values as $value) {
					if ($value == $typeSearch) {
						$typeFound = $key;
						break;
					}
				}
				if ($typeFound)
					break;
			}			
		
		}
		
		preg_match('/\(([0-9]+)\)/', $type, $match);
		$size = (int)get($match, k(1), 0);
		return object('name', $typeFound, 'size', $size);
	}
	
	
	/*
	 * Execute a query
	 *
	 */
	public function executeQuery(\Db\Object\QueryResult $query)
	{
		$sql = $this->queryToSql($query);

		return $this->execute($sql);
	}
	
	/*
	 * Provide a method to convert que query to sql
	 *
	 */
	protected function queryToSql(\Db\Object\QueryResult $query)
	{
		$sql = '';

		if ($query->getTables())
			$sql = $this->queryToSqlSelect($query);
		elseif ($query->getInsert())
			$sql = $this->queryToSqlInsert($query);
		elseif ($query->getUpdate())
			$sql = $this->queryToSqlUpdate($query);
		elseif ($query->getDelete())
			$sql = $this->queryToSqlDelete($query);
		else
			$this->error('Invlid query, unable to determine type.');

		foreach ($query->getReplaces() as $index => $replace) {
			$replace = $this->protect($replace);
			if (is_numeric($index))
				$sql = preg_replace('/\\:\\?([^0-9a-zA-Z])/', $replace . '$1', $sql, 1);
			else
				$sql = preg_replace('/\\:' . $index . '([^0-9a-zA-Z])/', $replace . '$1', $sql);
		}

		return $sql;
	}
	
	/*
	 * Return the sql for a select query
	 *
	 */
	private function queryToSqlSelect(\Db\Object\QueryResult $query)
	{
		$sql = 'SELECT ';
		if ($query->isFoundRows())
			$sql.= 'SQL_CALC_FOUND_ROWS ';

		// Select
		$array = $query->getSelect();
		foreach ($array as &$select)
			if ($select != '*' and strpos($select, '(') === false and strpos($select, '.') === false and strpos(strtolower($select), ' as ') === false)
				$select = $this->protect($select, \Db::FIELD);
		unset($select);
		if (!$array)
			$array[] = '*';
		$sql.= implode(',',$array);

		// From
		foreach ($query->getTables() as $alias => $table) {
			$tableName = $table->tableName;

			if (!$table->join)
				$sql.= ' FROM ' . $this->protect($tableName, \Db::FIELD) . ($alias == $tableName ? '' : ' AS ' . $alias);
			else {

				$join = $table->join->type == 'left' ? 'LEFT' : 'INNER';
				$onTable = $table->join->onTableClass;

				if (exists($table->join, 'nn')) {
					$nnTable = $table->join->nn->table;
					$sql.= chr(10) . $join . ' JOIN ' . $this->protect($nnTable, \Db::FIELD) . ' ON ' .
						$this->protect($nnTable, \Db::FIELD) . '.' . $this->protect($table->join->nn->onAttribute, \Db::FIELD) . '=' .
						$this->protect($table->join->onAlias, \Db::FIELD) . '.' . $this->protect($table->join->attribute, \Db::FIELD);
						
					$sql.= chr(10) . $join . ' JOIN ' . $this->protect($tableName, \Db::FIELD) . ' ON ' .
						$this->protect($alias, \Db::FIELD) . '.' . $this->protect($table->join->onAttribute, \Db::FIELD) . '=' .
						$this->protect($nnTable, \Db::FIELD) . '.' . $this->protect($table->join->nn->attribute, \Db::FIELD);
						
				} else {
					
					$sql.= ' ' . $join . ' JOIN ' . $this->protect($tableName, \Db::FIELD) . 
						($alias == $tableName ? '' : ' AS ' . $alias) . ' ON ' .
						$this->protect($alias, \Db::FIELD) . '.' . $this->protect($table->join->attribute, \Db::FIELD) . '=' .
						$this->protect($table->join->onAlias, \Db::FIELD) . '.' . $this->protect($table->join->onAttribute, \Db::FIELD);
						
				}
			}
		}

		// Where
		if ($array = $query->getWhere())
			$sql.= ' WHERE ' . implode(' ',$array);
		
		if ($query->getNotIn()) {

			$subSql = $this->queryToSql(new QueryResult($query->getNotIn()[1], $query->getNotIn()[1]->getReplaces(), false));

			$subSql = substr_replace($subSql, '', -1);
	
			if (!$query->getWhere())
				$sql.= ' WHERE ';
			else
				$sql.=' AND ';
			$sql.= $query->getNotIn()[0] . ' NOT IN (' . $subSql . ')';

		}

		
		// Group by
		if ($array = $query->getGroupBy()) {
			foreach ($array as &$group)
				if (strpos($group, '.') === false)
					$group = $this->protect($group, \Db::FIELD);
					
			unset($group);
			$sql.= ' GROUP BY ' . implode(',',$array);
		}

		// Having
		if ($array = $query->getHaving())
			$sql.= ' HAVING ' . implode(' ',$array);

		// Order by
		if ($array = $query->getOrderBy()) {
			foreach ($array as &$order) {
				if (strpos($order[0], '.') === false)
					$order = $this->protect($order[0], \Db::FIELD) . ' ' . $order[1];
				else
					$order = $order[0] . ' ' . $order[1];
			}
			unset($order);
			$sql.= ' ORDER BY ' . implode(',',$array);
		}

		// Limit
		$limit = $query->getLimit();
		if ($query->isOne() and count($query->getTables()) == 1 and !$limit)
			$limit = object('offset', 0, 'total', 1);
		if ($limit)
			$sql.= ' LIMIT ' . $limit->offset . ', ' . $limit->total;
		
		$sql.= ';';

		return $sql;
	
	}
	
	/*
	 * Return the sql for a insert query
	 *
	 */
	private function queryToSqlInsert(\Db\Object\QueryResult $query)
	{
		$table = $query->getInsert();
		$sql = 'INSERT INTO ' . $this->protect($table->tableName, \Db::FIELD) . ($table->alias == $table->tableName ? '' : ' AS ' . $table->alias);
		
		$attributes = $sqlValues = array();
		$attributeSet = false;

		foreach ($query->getValues() as $values) {
			$sqlValue = array();
			foreach ($values as $attribute => $value) {
				if (!$attributeSet)
					$attributes[] = $this->protect($attribute, \Db::FIELD);
				$sqlValue[] = $this->protect($value);
			}
			$attributeSet = true;
			$sqlValues[] = '(' . implode(',', $sqlValue) . ')';
		}
		
		$sql.= ' (' . implode(',', $attributes) . ') VALUES' . implode(',', $sqlValues) . ';';

		return $sql;
	}
	
	/*
	 * Return the sql for a update query
	 *
	 */
	private function queryToSqlUpdate(\Db\Object\QueryResult $query)
	{
		$table = $query->getUpdate();
		
		$sql = '';
		
		foreach ($query->getValues() as $values) {
		
			$sql.= 'UPDATE ' . $this->protect($table->tableName, \Db::FIELD) . ($table->alias == $table->tableName ? '' : ' AS ' . $table->alias) . ' SET ';
		
			$set = array();
			foreach ($values as $attribute => $value)
				$set[] = $this->protect($attribute, \Db::FIELD) . '=' . $this->protect($value);
			
			$sql.= implode(',', $set);
	
			// Where
			if ($where = $query->getWhere())
				$sql.= ' WHERE ' . implode(' ',$where);

			$sql.= ';';
		}

		return $sql;
	}
	
	/*
	 * Return the sql for a delete query
	 *
	 */
	private function queryToSqlDelete(\Db\Object\QueryResult $query)
	{
		$table = $query->getDelete();
		$sql = 'DELETE FROM ' . $this->protect($table->tableName, \Db::FIELD) . ($table->alias == $table->tableName ? '' : ' AS ' . $table->alias);
		
		// Where
		if ($where = $query->getWhere())
			$sql.= ' WHERE ' . implode(' ',$where);

		$sql.= ';';

		return $sql;
	}
	
	/*
	 * Check if sql contains other query (injection)
	 *
	 */
	public function checkSqlInjection($sql)
	{
		$sql = trim($sql);
		
		$match = str_replace('\\\\', '', $sql);
		$toTest = '';
		$quote = null;
		preg_match('/(.*?)("|\')(.*)/', $match, $matches);
		while (count($matches) > 0) {

			$toTest.= $matches[1];
			$quote = $matches[2];
			$match = ' ' . $matches[3];
			
			preg_match('/(.*?)([^\\\]' . preg_quote($quote) . ')(.*)/', $match, $matches);
			if ($matches)
				$match = $matches[3];
			else
				$match = '';

			preg_match('/(.*?)("|\')(.*)/', $match, $matches);
		}
		$toTest.= $match;

		if (substr_count($toTest, ';') > 0 and preg_match('/;[\\s\\S]+/', $toTest) > 0)
			$this->error('Multiple queries are not allowed');
		
		return $sql;
	}
	
	/*
	 * Execute a sql query of any type
	 *	return an array for "select" and integer for others
	 *
	 * Usage :
	 *	execute('<sql>')
	 *	execute('<sql>', true) : return one line in select mode
	 *
	 */
	public function execute($sql, $one = false, $object = true)
	{
		return $this->_execute($this->checkSqlInjection($this->sql = $sql), $one, $object);
	}
	
	/*
	 * Execute a sql query of non select type without injection test
	 *
	 */
	public function executeMultiple($sql)
	{
		return $this->_execute($this->sql = $sql);
	}
	
	/*
	 * Private execution of sql
	 *
	 */
	private function _execute($sql, $one = false, $object = true)
	{
trace($sql, \Debug::chronoStart());
		$result = null;
		$sql = trim($sql);
		preg_match('/\\w*/', $sql, $match);
		if (in_array(strtolower($match[0]), array('select', 'describe', 'show', 'pragma'))) {
			if ($statement = $this->pdo->query($sql, $object ? \PDO::FETCH_OBJ : \PDO::FETCH_ASSOC)) {
				$result = $statement->fetchAll();
				$statement->closeCursor();
				unset($statement);

				if ($one) {
					if (count($result) > 0)
						$result = $result[0];
					else
						$result = null;
				}
			} else
				$result = false;
			
		} else {

			// Transform INSERT INTO SET in INSERT INTO VALUES
			if (preg_match ('/INSERT INTO\s*([\S]+)\s*SET\s*([\S\s]*)/i', $sql, $match)) {
				preg_match_all('/\s*`?([_a-zA-Z]+)`?\s*=\s*(([\'"]?)(?:\\\\\3|[^\3])*?(?:[^,;]*)\3)?/', $match[2], $data);
				$sql = 'INSERT INTO ' . $match[1] . '(' . implode($data[1], ',') . ') VALUES(' . implode($data[2], ',') . ');';
			}

			$result = $this->pdo->exec($sql);
			
		}
		if ($result === false)
			$this->error(geta($this->pdo->errorInfo(), k(2)));

		$this->query_total++;
trace(\Debug::chronoGet());
		return $result;
	}
	
	/*
	 * Protects a user value
	 *
	 */
	public function protect($value, $type = \Db::STRING)
	{
		$result = $value;

		if (is_array($result)) {
			foreach ($result as &$value)
				$value = $this->protect($value, $type);
			unset($value);
			return $result;
		}

		if ($type == \Db::FIELD)
			$result = '`' . str_replace('`', '``', $value) . '`';
		else
			$result = $value === null ? 'NULL' : $this->pdo->quote($value, $type);

		return $result;
	}
	
	public function protectArray($array, $type = \Db::STRING)
	{
		foreach ($array as &$value)
			$value = $this->protect($value, $type);
		unset($value);
		return $array;
	}
	
	/*
	 * Create a sql function
	 *
	 */
	public function createFunction($sql, $ifNotExists = false)
	{
		preg_match('/CREATE FUNCTION ([a-zA-Z0-9_-]*)/', $sql, $name);
		$name = $name[1];
		
		$exists = false;
		$functions = $this->execute('SHOW FUNCTION STATUS WHERE Db = ' . $this->protect($this->Db) . ';');
		foreach ($functions as $function)
			if ($exists = $function['Name'] == $name)
				break;

		if ($exists and !$ifNotExists) {
			$this->execute('DROP FUNCTION ' . $this->protect($name, Db::FIELD) . ';');
			$exists = false;
		}
		
		if (!$exists)
			$this->executeMultiple($sql);
		
	}
	

	/*
	 * Returns the id of the last inserted row
	 *
	 */
	public function lastId()
	{	
		return $this->pdo->lastInsertId();
	}
	
	public function getFoundRows()
	{	
		return (int)$this->execute('SELECT FOUND_ROWS() AS total')[0]->total;
	}
	
	/*
	 * Execute insert, update and deletes queries depending on array $data
	 *
	 *	$table : Name of the table where the query must be executed
	 *	$data : The data array or object. Array can containt 1 line for execute
	 *			1 query.
	 *			
	 *				non exists id or id = 0 or +id : insert
	 *				id > 0 : update
	 *				id < 0 : delete
	 *				
	 *			Other key must be named according to the fields to insert or
	 *			update.
	 *			
	 *			In case of insert, the value of the id is updated with the new
	 *			id generated.
	 *	return : A summary of queries executed including key 'insert', 'update'
	 *			and 'delete'.
	 */
	public function executeArray($table, $data)
	{
		$records = object(
			'insert', object(
				'withId', array(),
				'withoutId', array()
			),
			'update', array(),
			'delete', array()
		);
		
		if (is_object($data))
			$data = array($data);		

		foreach($data as $line)
			if ((int)$line->id == 0)
				$records->insert->withoutId[] = $line;
			elseif (substr($line->id, 0, 1) == '+')
				$records->insert->withId[$line->id = (int)$line->id] = $line;
			elseif ($line->id > 0)
				$records->update[(int)$line->id] = $line;
			else
				$records->delete[-1*$line->id] = $line;

		// Delete
		foreach ($records->delete as $record)
			$this->execute('
				DELETE FROM
					' . $this->protect($table, \Db::FIELD) . '
				WHERE
					id = ' . $this->protect($line->id * -1) . '
				;
			');

		// Update
		foreach ($records->update as $record) {
			$set = array();
			foreach ($record as $field => $value)
				$set[] = $this->protect($field, \Db::FIELD) . '=' . ($value === null ? 'NULL' : $this->protect($value));
			$this->execute('
				UPDATE
					' . $this->protect($table, \Db::FIELD) . '
				SET
					' . implode($set, ',') . '
				WHERE
					id = ' . $this->protect($line->id) . '
				;
			');
		}
		
		// Insert with id
		foreach ($records->insert->withId as $record) {
			$set = array();
			foreach ($record as $field => $value)
				$set[] = $this->protect($field, \Db::FIELD) . '=' . ($value === null ? 'NULL' : $this->protect($value));
			$this->execute('
				INSERT INTO
					' . $this->protect($table, \Db::FIELD) . '
				SET
					' . implode($set, ',') . '
				;
			');
		}
		
		// Insert without id
		$nextId = get($this->execute('SELECT MAX(id) AS max FROM ' . $this->protect($table, \Db::FIELD) . ';', true), k('max')) + 1;
		foreach ($records->insert->withoutId as $record) {
			$record->id = $nextId++;
			$set = array();
			foreach ($record as $field => $value)
				$set[] = $this->protect($field, \Db::FIELD) . '=' . ($value === null ? 'NULL' : $this->protect($value));
			$this->execute('
				INSERT INTO
					' . $this->protect($table, \Db::FIELD) . '
				SET
					' . implode($set, ',') . '
				;
			');
			$records->insert->withId[$record->id] = $record;
		}
		
		$records->insert = $records->insert->withId;

		return $records;
		
	}
	
	/*
	 * Compare the array $new whith the data in $table or array $old depending
	 *	on the $keys (array or string) and where condition
	 *
	 */
	public function compareArray($tableOrOld, $new, $keys = 'id', $where = null) {
	
		if (is_string($tableOrOld)) {
			$old = $this->execute('SELECT * FROM ' . $this->protect($tableOrOld, \Db::FIELD) . (is_null($where) ? '' : ' WHERE ' . $where) . ';');
		} else {
			$old = &$tableOrOld;
		}

		if (!is_array($keys)) {
			$keys = array($keys);
		}
	
		// Indexation du tableau old pour comparaison plus rapide
		$oldIndexed = array();
		foreach ($old as $line) {
			$index = array();
			foreach ($keys as $key) {
				$index[] = $line->$key;
			}
			$oldIndexed[serialize($index)] = $line;
		}
		$old = $oldIndexed;

		// Comparaison
		$results = array();
		foreach ($new as $line) {
			$index = array();
			foreach ($keys as $key) {
				$index[] = $line->$key;
			}
			$index = serialize($index);
			$line->id = array_key_exists($index, $old) ? $old[$index]->id : '+' . $line->id;
			$results[] = $line;
			unset($old[$index]);
		}
		foreach ($old as $line) {
			$line->id*= -1;
			$results[] = $line;
		}
		return $results;
	}
	
	/*
	 * Update all data in $table by comparing 2 arrays
	 *
	 */
	public function updateArray($table, $new, $old = null, $keys = 'id', $where = null) {
		
		if (is_null($old))
			$old = $table;
		$results = $this->compareArray($old, $new, $keys, $where);
		return $this->executeArray($table, $results);

	}

}
