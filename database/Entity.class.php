<?
namespace Db\Object;

abstract class Entity implements \arrayaccess, \iterator
{
	private $_isLoad;
	private $_position;
	private $_table;
	private $_extras = array();

	/*
	 * Entity constructor
	 *
	 * Usage :
	 *	new Entity() : create an empty entity
	 *	new Entity(<id>) : load entity <id> from database
	 *	new Entity(array or object <data>) : create entity with <data> (data can contains extra)
	 *	new Entity(<id>, <extra1>, <extra2>, ...) : load or create entity with factorized entities (or collection) <extra1>, ...
	 *		<extra1> can be an array of extras
	 */

	public function __construct($data = null)
	{
		$this->_table = ($this) . 'Table';
		if ($data !== null) {
			
			// Retrieve extra list from arguments ...
			$extras = func_get_args();
			array_shift($extras);
			$this->_extras = $this->getFactorizedAttributesFromArgs($extras);

			// Loading entity
			$this->load($data);
		}

	}
	
	public function __toString()
	{
		return get_class($this);
	}

	public static function __from($data)
	{
		$entity = get_called_class();
		return new $entity($data);
	}

	private function __error($text, $stack = 0)
	{
		$backtrace = geta(debug_backtrace(), k(2 + $stack));
		trigger_error($text . ' at ' . $backtrace['file'] . ' (' . $backtrace['line'] . ')', E_USER_ERROR);
	}

	/*
	 * Iterator methods
	 */
	public function current()
	{
		return $this->_attributes[$this->key()];
	}
	
	public function key()
	{
		$table = $this->_table;
		return $table::getAttribute($this->_position);
	}
	
	public function next()
	{
		++$this->_position;
	}
	
	public function rewind()
	{
		$this->_position = 0;
	}
	
	public function valid()
	{
		return (bool)$this->key();
	}
	
	/*
	 * Getter and setter methods
	 */
	public function __call($name, $arguments)
	{
		// Default getter
		if (substr($name, 0, 3) === 'get' and $name = lcfirst(substr($name, 3))) {

			if (array_key_exists($name, $this->_attributes))
				return $this->_attributes[$name];

			if (array_key_exists($name, $this->_attributesFactorized))
				return $this->__getFactorized($name);
			
			if (array_key_exists($name, $this->_attributesExtended))
				return $this->_attributesExtended[$name];
			
			$backtrace = geta(debug_backtrace(), k(1));
			$this->__error('Get undefined entity attribute: ' . get_class($this) . '::' . $name);
		}
		
		// Default setter
		if (substr($name, 0, 3) === 'set' and $name = lcfirst(substr($name, 3))) {

			if (array_key_exists($name, $this->_attributes))
				return $this->_attributes[$name] = $arguments[0];

			if (array_key_exists($name, $this->_attributesFactorized))
				return $this->__setFactorized($name, $arguments[0]);

			if (array_key_exists($name, $this->_attributesExtended))
				return $this->_attributesExtended[$name] = $arguments[0];
			
			$this->__error('Set undefined entity attribute: ' . get_class($this) . '::' . $name);

		}

		// Brut getter
		if (substr($name, 0, 4) === '_get' and $name = lcfirst(substr($name, 4))) {

			if (array_key_exists($name, $this->_attributes))
				return $this->_attributes[$name];

			if (array_key_exists($name, $this->_attributesFactorized))
				return $this->_attributesFactorized[$name];
			
			if (array_key_exists($name, $this->_attributesExtended))
				return $this->_attributesExtended[$name];
			
			$this->__error('Get undefined entity brut attribute: ' . get_class($this) . '::' . $name);
		}

		// Brut setter
		if (substr($name, 0, 4) === '_set' and $name = lcfirst(substr($name, 4))) {

			if (array_key_exists($name, $this->_attributes))
				return $this->_attributes[$name] = $arguments[0];

			if (array_key_exists($name, $this->_attributesFactorized))
				return $this->_attributesFactorized[$name] = $arguments[0];
			
			if (array_key_exists($name, $this->_attributesExtended))
				return $this->_attributesExtended[$name] = $arguments[0];
			
			$this->__error('Set undefined entity brut attribute: ' . get_class($this) . '::' . $name);
		}

		// Is
		if (substr($name, 0, 2) === 'is' and $name = lcfirst(substr($name, 2)))
			return $this->{'get' . ucfirst($name)}() == 1;

		$this->__error('Call to undefined entity function: ' . get_class($this) . '::' . $name . '()');
	}
	
	public function __get($name)
	{
		return $this->{'get' . ucfirst($name)}();
	}
	
	public function __getFactorized($name)
	{

		$table = $this->_table;
		foreach ($table::getLinks() as $link) {
			if ($link->attributeFactorized != $name)
				continue;

			if ($this->_attributesFactorized[$name] === null) {

				// Is not already load
				if ($link->relationship == \Db::R_1_1 or $link->relationship == \Db::R_1_N) {
					$entity = forward_static_call(array($link->onTableClass, 'getEntityClass'));
					try {
						$entity = new $entity($this->{$link->attribute});
					} catch(\Exception $e) {
						if ($e->getCode() != 404)
							throw $e;
						$entity = null;
					}
					return $this->_attributesFactorized[$name] = $entity;
				} elseif ($link->relationship == \Db::R_N_1) {
					$onTableClass = $link->onTableClass;
					return $this->_attributesFactorized[$name] = $onTableClass::{'findBy' . ucfirst($link->onAttribute)}($this->{$link->attribute});
				} else {
					$onTableClass = $link->onTableClass;
					return $this->_attributesFactorized[$name] = $table::{'findOneBy' . ucfirst($link->attribute) . 'With' . ucfirst($onTableClass::getName())}($this->{$link->attribute})->{'get' . ucfirst($link->attributeFactorized)}();
				}

			} elseif (is_array($this->_attributesFactorized[$name])) {

				// Is array : collection
				$entity = forward_static_call(array($link->onTableClass, 'getEntityName'));

				return $this->_attributesFactorized[$name] = new \Db\Object\Collection($entity, $this->_attributesFactorized[$name]);
			
			} elseif (get_class($this->_attributesFactorized[$name]) == 'Object') {

				// Is object : entity
				$entity = forward_static_call(array($link->onTableClass, 'getEntityClass'));
				return $this->_attributesFactorized[$name] = new $entity($this->_attributesFactorized[$name]);
			

			} else
				return $this->_attributesFactorized[$name];
				
		}

	}
	
	public function __set($name, $value)
	{
		return $this->{'set' . ucfirst($name)}($value);
	}
	
	public function __setFactorized($name, $value)
	{

		if ($value === null)
			return;

		$table = $this->_table;
		foreach ($table::getLinks() as $link) {
			if ($link->attributeFactorized != $name)
				continue;
			
			$onTable = $link->onTableClass;

			if ($link->relationship == \Db::R_1_1 or $link->relationship == \Db::R_1_N) {
				
				if (get_class($value) != 'Object' and !is_array($value) and get_class($value) != $onTable::getEntityClass())
					$this->__error('Set incorrect type of factorized attribute: ' . get_class($this) . '::' . $name, 1);

				$onTableClass = $link->onTableClass;

				$this->_attributes[$link->attribute] = is_object($value) ? $value->{$onTableClass::getId()} : $value[$onTableClass::getId()];
				return $this->_attributesFactorized[$name] = $value;
			
			} else {

				if (gettype($value) == 'object') {
					// Check if object is of correct isntance
					if (get_class($value) != 'Db\Object\Collection')
						$this->__error(get_class($value) . ' is forbidden for ' . get_class($this) . '::' . $name, 1);

					if (strtolower($value->getEntityName()) != strtolower($onTable::getEntityName()))
						$this->__error('Set incorrect type of factorized attribute: ' . get_class($this) . '::' . $name, 1);
				
				} elseif (is_array($value))
					// Convert array to collection
					$value = new \Db\Object\Collection($onTable::getEntityName(), $value);
					
				if ($link->relationship == \Db::R_N_1)
					foreach ($value as $entity)
						$entity->{'set' . ucfirst($link->onAttribute)}($this->{'get' . ucfirst($link->attribute)}());

				return $this->_attributesFactorized[$name] = $value;
			
			}
		}
	}

	public function get($name)
	{
		return $this->$name;
	}
	
	public function set($name, $value)
	{
		return $this->$name = $value;
	}

	/*
	 * Arrayaccess methods
	 */
	public function offsetExists($name)
	{
		return array_key_exists($name, $this->_attributes);
	}
	public function offsetGet($name)
	{
		return $this->$name;
	}
	public function offsetSet($name, $value)
	{
		return $this->$name = $value;
	}
	public function offsetUnset($name)
	{
		if (array_key_exists($name, $this->_attributes))
			$this->_attributes[$name] = null;
	}

	/*
	 * Database methods
	 */
	private function load($data)
	{

		if (is_object($data) or is_array($data))
			return $this->loadData($data);

		$id = $data;

		$q = new \Db\Object\Query();
		$table = $this->_table;

		$db = \Db::getInstance();

		$q->from($table::getName())->where($db->protect($table::getName(), \Db::FIELD) . '.' . $db->protect($table::getId(), \Db::FIELD) . ' = :?', $id)->one();

		foreach ($this->_extras as $extra)
			$q->attributeJoin($table, $extra);

		if (!$data = $q->execute())
			throw new \Exception('Entity ' . get_class($this) . '(' . $id . ') not found', '404');

		$this->loadData($data);

		return $this;
	}
	
	private function loadData($data)
	{

		if (is_object($data) and get_class($data) === get_called_class()) {

			$table = $this->_table;
			foreach ($table::getAttributes() as $attribute)
				$this->{'_set' . ucfirst($attribute)}($data->{'_get' . ucfirst($attribute)}());
			foreach ($table::getAttributesFactorized() as $attribute)
				$this->{'_set' . ucfirst($attribute)}($data->{'_get' . ucfirst($attribute)}());
			foreach ($table::getAttributesExtended() as $attribute)
				$this->{'_set' . ucfirst($attribute)}($data->{'_get' . ucfirst($attribute)}());
			
		} else
			foreach ($data as $key => $value)
				if (array_key_exists($key, $this->_attributes) or array_key_exists($key, $this->_attributesFactorized) or array_key_exists($key, $this->_attributesExtended))
					$this->{'set' . ucfirst($key)}($value);


		$this->_isLoad = true;
		
		return $this;
	}

	/*
	 * Save entity from database and his factorized attributes if desired
	 *
	 *	Usage :
	 *		save()
	 *		save(R_*)
	 *		save('<factorizedAttribute1>', '<factorizedAttribute2>', ...)
	 *		save(array(
	 *			'<factorizedAttribute1>', '<factorizedAttribute2>', ...
	 *		))
	 *
	 */
	public function save($saveFactorized = \Db::R_NONE)
	{
		$table = $this->_table;
	
		$db = \Db::getInstance();

		$query = new \Db\Object\Query($table::getName());

		if ($table::getId())
			$insert = !$this->{$table::getId()};
		else
			$insert = !$table::count();

		if ($insert)
			$query->insert();
		else
			$query->update();

		if ($table::getId() and !$insert)
			$query->where($db->protect($table::getId(), \Db::FIELD) . '=:?', $this->{$table::getId()});
		
		$query->values($this->_attributes);
		
		$total = $query->execute();
		
		// Update attribute <id>
		if ($table::getId() and $insert)
			$this->{$table::getId()} = $query->getIdInserted();

		// Save factorized attributes desired
		foreach ($this->getFactorizedAttributesFromArgs(func_get_args()) as $factorized)
			if ($this->$factorized)
				$total+= $this->$factorized->save();

		// Save relationships of type R_N_N		
		foreach ($table::getLinks() as $link) {

			if ($link->relationship != \Db::R_N_N or $this->_attributesFactorized[$link->attributeFactorized] === null)
				continue;

			$query = new \Db\Object\Query('*' . $link->nnTable);
			$query->delete()->where($db->protect($link->nnAttribute, \Db::FIELD) . '=:?', $this->{$link->attribute})->execute();

			foreach ($this->{$link->attributeFactorized} as $entity) {
				$values = object(
					$link->nnAttribute, $this->{$link->attribute},
					$link->nnOnAttribute, $entity->{$link->onAttribute}
				);
				foreach ($link->nnAttributes as $attributeExtended)
					$values->$attributeExtended = $entity->$attributeExtended;
				$query->values($values);
			}
			if ($query->getValues())
				$query->insert()->execute();

		}
		
		return $total;
	}
	
	/*
	 * Delete entity from database and his factorized attributes if desired
	 *
	 *	Usage :
	 *		delete()
	 *		delete(R_*)
	 *		delete('<factorizedAttribute1>', '<factorizedAttribute2>', ...)
	 *		delete(array(
	 *			'<factorizedAttribute1>', '<factorizedAttribute2>', ...
	 *		))
	 *
	 */
	public function delete($deleteFactorized = array())
	{
		$table = $this->_table;
	
		$db = \Db::getInstance();
	
		$query = new \Db\Object\Query();
		$query->delete($table::getName())->where($db->protect($table::getId(), \Db::FIELD) . '=:?', $this->{$table::getId()});
		$total = $query->execute();

		// Save relationships of type R_N_N		
		foreach ($table::getLinks() as $link) {

			if ($link->relationship != \Db::R_N_N)
				continue;
			
			$query = new \Db\Object\Query('*' . $link->nnTable);
			$query->delete()->where($db->protect($link->nnAttribute, \Db::FIELD) . '=:?', $this->{$link->attribute})->execute();

		}

		// Delete factorized attributes desired
		foreach ($this->getFactorizedAttributesFromArgs(func_get_args()) as $factorized)
			if ($this->$factorized)
				$total+= $this->$factorized->delete();

		return $total;			
	}
	
	/*
	 * Return the list of factorized attributes from a list of args
	 *
	 *
	 */
	private function getFactorizedAttributesFromArgs($args)
	{
		$attributes = array();
	
		if (count($args)) {
	
			if (is_string($args[0]))
				$attributes = $args;
			elseif (is_array($args[0]))
				$attributes = $args[0];
			elseif (is_int($args[0]) and $args[0] != \Db::R_NONE) {
				$table = $this->_table;
				foreach ($table::getLinks() as $link)
					if ($args[0] == $link->relationship or $args[0] == \Db::R_ALL)
						$attributes[] = $link->attributeFactorized;
			}
		}
			
		return $attributes;
	}
}
