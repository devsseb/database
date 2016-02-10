<?
namespace Db\Object;

class Collection implements \arrayaccess, \iterator, \countable
{
	private $db;
	private $query;
	private $data;
	private $position;
	private $entityName;

	public function __construct($queryOrEntityName, $data = array())
	{
		$this->db = \Db::getInstance();
		$this->setData($queryOrEntityName, $data);
	}
	
	public function getEntityName()
	{
		return $this->entityName;
	}
	
	public function setData($queryOrEntityName, $data)
	{
		if (is_string($queryOrEntityName))
			$entityName = $queryOrEntityName;
		else {
			$entityName = $queryOrEntityName->getEntityName();
			$data = $queryOrEntityName->get();
		}
	
		$this->entityName = $entityName;

		if (is_array($data))
			$this->data = array_values($data);
		elseif ($data and get_class($data) == 'Db\\Object\\Collection')
			$this->data = $data->getData();
		else
			$this->data = array();

		$this->position = 0;
	}
	
	public function getData()
	{
		return $this->data;
	}

	/*
	 * Save all entities collection in database
	 *
	 */
	public function save()
	{
		$args = func_get_args();
		$total = 0;
		foreach ($this as $entity)
			$total+= call_user_func_array(array($entity, 'save'), $args);
		return $total;
	}
	
	/*
	 * Delete all entities collection in database
	 *
	 */
	public function delete()
	{
			$args = func_get_args();
		$total = 0;
		foreach ($this as $entity)
			$total+= call_user_func_array(array($entity, 'delete'), $args);
		return $total;
	}
	
	/*
	 * Arrayaccess methods
	 */
	public function offsetExists($key)
	{
		return array_key_exists($key, $this->data);
	}
	public function offsetGet($key)
	{
	
		if (is_array($this->data[$key]) or get_class($this->data[$key]) != 'Db\\' . $this->entityName) {
			$entity = '\\Db\\' . $this->entityName;
			$this->data[$key] = new $entity($this->data[$key]);
		}
	
		return $this->data[$key];
	}
	public function offsetSet($key, $value)
	{
		if ($key === null)
			$key = count($this);
		
		return $this->data[$key] = $value;
	}

	public function offsetUnset($key)
	{
		if (array_key_exists($key, $this->data)) {
			unset($this->data[$key]);
			$this->data = array_values($this->data);
		}
	}

	/*
	 * Iterator methods
	 */
	public function current()
	{
		return $this[$this->position];
	}
	
	public function key()
	{
		return $this->position;
	}
	
	public function next()
	{
		++$this->position;
	}
	
	public function rewind()
	{
		$this->position = 0;
	}
	
	public function valid()
	{
		return $this->offsetExists($this->position);
	}
	
	/*
	 * Countable method
	 */
	public function count($what = '', $values = null)
	{
		if (func_num_args() == 0)
			return count($this->data);
			
		return $this->find($what, $values)->count();

	}
	
	/*
	 *
	 */
	public function __call($name, $args)
	{

		if (strpos($name, 'find') === 0)
			return $this->find(substr($name, 4), $args);
			
		if (strpos($name, 'count') === 0)
			return $this->count(substr($name, 5), $args);
			
		if (strpos($name, 'remove') === 0)
			return $this->find(substr($name, 6), $args, true);

	}
	
	private function find($what = '', $values = null, $remove = false)
	{
		$parse = \Db::parseFindString('\\Db\\' . $this->entityName . 'Table', $what, $values);

		$evalFilter = 'return ';
		if ($parse->what)
			foreach ($parse->what as $what) {
				if (property_exists($what, 'operator'))
					$evalFilter.= ' ' . $what->operator . ' ';
	
				if ($what->type == 'like')
					$evalFilter.= 'preg_match(\'/^' . str_replace(array('%', '?'), array('.*', '.'), preg_quote($what->value)) . '$/i\', $value->' . $what->attribute . ')';
				else
					$evalFilter.= '$value->' . $what->attribute . '==\'' . addslashes($what->type == 'by' ? $what->value : '1') . '\'';
	
			}
		else
			$evalFilter.= 'true';
		$evalFilter.= ';';
		$evalOrder = '';
		foreach ($parse->order as $attribute => $order) {
			$evalOrder.= 'if ($a->' . $attribute . ' ' . ($order == 'asc' ? '<' : '>') . ' $b->' . $attribute . ') return -1;';
			$evalOrder.= 'elseif ($a->' . $attribute . ' ' . ($order == 'asc' ? '>' : '<') . ' $b->' . $attribute . ') return 1;';
		}
		$evalOrder.= 'return 0;';

		$data = array();
		$keepIndex = $removeIndex = 0;
		foreach ($this->data as $value) {
			if (eval($evalFilter)) {
				
				$keepIndex++;
				
				if ($remove)
					array_splice($this->data, $removeIndex, 1);

				if ($parse->one) {
					$data = $value;
					break;
				} else {

					if ($parse->limit and $keepIndex <= $parse->limit->offset)
						continue;
					 elseif ($parse->limit and $keepIndex > $parse->limit->offset + $parse->limit->total)
						break;
					
					$data[] = $value;
				
				}
			} else
				$removeIndex++;
		}

		if ($parse->order) {
			
			$i = 0;
			usort($data, function($a, $b) use ($evalOrder) {
				return eval($evalOrder);
			});
		
		}

		if ($parse->one) {
			if (!$data)
				return null;
			$entity = '\\Db\\' . $this->entityName;
			return new $entity($data);
		}
		
		return new Collection($this->entityName, $data);

	}

}
