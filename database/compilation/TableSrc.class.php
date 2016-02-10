<?
namespace Db\Compilation;

abstract class TableSrc
{
	protected $entityName, $name,
		$attributes = array(), $links = array();

	public function __construct()
	{
		// Retrieve entity name
		$entityName = explode('\\', get_class($this));
		$this->entityName = end($entityName);
		$this->entityName = substr($this->entityName, 0, strlen($this->entityName) - 5);

		// Set name of table (default is the same as entity name)
		$this->setName(strtolower($this->getEntityName()));
		
		if (method_exists($this, '_construct'))
			call_user_func_array(array($this, '_construct'), func_get_args());
	}

	public function __toString()
	{
		return get_class($this);
	}

	public function getEntityName()
	{
		return $this->entityName;
	}

	/*
	 * Set the name in database
	 *
	 */
	protected function setName($name)
	{
		return $this->name = $name;
	}
	
	/*
	 * Retrieve the name in database
	 *
	 */
	public function getName()
	{
		return $this->name;
	}

	protected function addAttribute($name, $type, $size = null, $null = false, $primary_key = false, $auto_increment = false, $default = null)
	{
		if (is_object($name))
			$attribute = $name;
		else
			$attribute = object(
				'name', $name,
				'type', $type,
				'size', $size,
				'primary_key', $primary_key,
				'auto_increment', $auto_increment,
				'null', $null,
				'default', $default
			);

		$this->attributes[] = $attribute;
	}

	public function getAttributes()
	{
		return $this->attributes;
	}

	/*
	 * Set a link with an other table
	 *
	 * Params
	 *	$onTable : Name of the class "Table" to which the link points
	 *	$relationship : constant of type R_*
	 *	$attribute : Name of the attribute that must be generated for the current table (or link by the relationship (n,[1n]) )
	 *		default = '<onTable>_<attribute>'
	 *	$attributeFactorized : Name of the factorized attribute (entity) that must be generated for the current table
	 *		default = '<onTable>' or '<onTable>Collection'
	 *	$onAttribute : Attribute's table to which the link points
	 *		default = 'id'
	 *	$onAttributeFactorized : Name of the factorized attribute (entity) that must be generated for the targeted table
	 *		default = '<table>' or '<table>Collection'
	 *	$nnTable : Name of the relationship table in case of (n,n)
	 *		default = '<table>_<onTable>'
	 *	$nnAttribute : Name of the attribute that must be generated in the middle table for this table
	 *		default = '<table>_<attribute>'
	 *	$nnOnAttribute : Name of the attribute that must be generated in the middle table for the onTable
	 *		default = '<onTable>_<onAttribute>'
	 *	$nnAttributes : List of extras attributes for relationship table in case of (n,n)
	 *		default = array()
	 * 
	 */
	protected function addLink($onTable, $relationship, $attribute = null, $attributeFactorized = null, $onAttribute = null, $onAttributeFactorized = null,
		$nnTable = null, $nnAttribute = null, $nnOnAttribute = null, $nnAttributes = array()
	)
	{
		// Set default parameters
		if ($onAttribute == null)
			$onAttribute = 'id';
		if ($attribute === null) {
			if ($relationship == \Db::R_1_1 or $relationship == \Db::R_1_N)
				$attribute = $onTable . '_' . $onAttribute;
			else
				$attribute = 'id';
		}
		if ($attributeFactorized == null) {
			$attributeFactorized = $onTable;
			if ($relationship == \Db::R_N_1 or $relationship == \Db::R_N_N)
				$attributeFactorized.= 'Collection';
		}
		if ($onAttributeFactorized == null) {
			$onAttributeFactorized = strtolower($this->getEntityName());
			if ($relationship == \Db::R_1_1 or $relationship == \Db::R_1_N or $relationship == \Db::R_N_N)
				$onAttributeFactorized.= 'Collection';
		}
		if ($relationship == \Db::R_N_N) {
			if ($nnTable == null)
				$nnTable = strtolower($this->getEntityName()) . '_' . $onTable;
			if ($nnAttribute == null)
				$nnAttribute = strtolower($this->getEntityName()) . '_' . $attribute;
			if ($nnOnAttribute == null)
				$nnOnAttribute = $onTable . '_' . $onAttribute;
			
			array_unshift($nnAttributes,
				object(
					'name', $nnAttribute,
					'type', \Db::T_INTEGER
				),
				object(
					'name', $nnOnAttribute,
					'type', \Db::T_INTEGER
				)
			);

		}

		// Add attribute
		if ($relationship == \Db::R_1_1 or $relationship == \Db::R_1_N)
			$this->addAttribute($attribute, \Db::T_INTEGER);

		// Add link
		$this->links[] = object(
			'relationship', $relationship,
			'attribute', $attribute,
			'attributeFactorized', $attributeFactorized,
			'onTableClass', '\\Db\\' . ucfirst($onTable) . 'Table',
			'onAttribute', $onAttribute,
			'onAttributeFactorized', $onAttributeFactorized,
			'nnTable', $nnTable,
			'nnAttribute', $nnAttribute,
			'nnOnAttribute', $nnOnAttribute,
			'nnAttributes', $nnAttributes
		);

	}

	public function getLinks()
	{
		return $this->links;
	}

	public function getUserFunctions()
	{
		$class = get_called_class();
		$reflection = new \ReflectionClass($class);
		$source = file($reflection->getFileName());
		$methods = $reflection->getMethods();
		$this->userMethods = array();
		foreach ($methods as $method) {
			if ($method->class != $class or (in_array($method->name, array('_construct', '__construct'))))
				continue;

			$methodSource = implode('', array_slice($source, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

			$this->userMethods[$method->name] = $methodSource;
		}

		return $this->userMethods;

	}

	public function compile($links)
	{

		$replace = array();

		$replace['tablePath'] = dirname(dirname(__FILE__)) . '/';
		$replace['className'] = $this->getEntityName() . 'Table';
		$replace['tableName'] = $this->getName();
		$replace['id'] = 'null';

		$attributes = $attributesFactorized = $attributesExtended = array();
		foreach ($this->attributes as $attribute) {
			if ($attribute->primary_key)
				$replace['id'] = '\'' . $attribute->name . '\'';
			$attributes[] = '\'' . $attribute->name . '\'';
		}
		$replace['attributes'] = implode(',' . chr(10) . chr(9) . chr(9), $attributes);

		foreach ($links as &$link) {
		
			$attributesFactorized[] = '\'' . $link->attributeFactorized . '\'';

			$nnAttributes = array();
			for ($i = 2; $i < count($link->nnAttributes); $i++) {
				$nnAttributes[] = '\'' . $link->nnAttributes[$i]->name . '\'';
				$attributesExtended[] = '\'' . $link->nnAttributes[$i]->name . '\'';
			}
		
			$link = 'array(
			\'relationship\' => ' . $this->compileRelationship($link->relationship) . ',
			\'attribute\' => \'' . $link->attribute . '\',
			\'attributeFactorized\' => \'' . $link->attributeFactorized . '\',
			\'onTableClass\' => \'' . $link->onTableClass . '\',
			\'onAttribute\' => \'' . $link->onAttribute . '\',
			\'onAttributeFactorized\' => \'' . $link->onAttributeFactorized . '\',
			\'nnTable\' => \'' . $link->nnTable . '\',
			\'nnAttribute\' => \'' . $link->nnAttribute . '\',
			\'nnOnAttribute\' => \'' . $link->nnOnAttribute . '\',
			\'nnAttributes\' => array(' . implode(',', $nnAttributes). ')
		)';
		}
		unset($link);
		$replace['links'] = implode(',' . chr(10) . chr(9) . chr(9), $links);
		$replace['attributesFactorized'] = implode(',' . chr(10) . chr(9) . chr(9), $attributesFactorized);
		$replace['attributesExtended'] = implode(',' . chr(10) . chr(9) . chr(9), $attributesExtended);

		$replace['userFunctions'] = implode(chr(10), $this->getUserFunctions());

		$return = preg_replace_callback('/\|\w+\|/', function($match) use ($replace) {
			$match = substr($match[0], 1, strlen($match[0]) - 2);
			if (exists($replace, $match))
				return $replace[$match];
		}, file_get_contents(__DIR__ . '/TableTemplate.class.php'));
		
		return $return;

	}
	
	private function compileRelationship($relationship)
	{
		$return  = '\Db::R_';
		if ($relationship == \Db::R_1_1)
			$return.= '1_1';
		elseif ($relationship == \Db::R_1_N)
			$return.= '1_N';
		elseif ($relationship == \Db::R_N_1)
			$return.= 'N_1';
		elseif ($relationship == \Db::R_N_N)
			$return.= 'N_N';
		return $return;
	}

}
