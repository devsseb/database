<?

namespace Db\Connector;

class Mysql extends \Db\Object\Connector
{
	public function getTypes()
	{
		return array(
			\Db::T_INTEGER => array('INTEGER', 'INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'YEAR'),
			\Db::T_BIG_INTEGER => array('BIGINT'),
			\Db::T_DECIMAL => array('DECIMAL', 'DEC', 'NUMERIC'),
			\Db::T_FLOAT => array('FLOAT', 'REAL', 'DOUBLE', 'DOUBLE PRECISION'),
			\Db::T_BOOL => array('BOOLEAN', 'BOOL', 'TINYINT(1)'),
			\Db::T_CHAR => array('VARCHAR', 'TINYTEXT', 'CHAR'),
			\Db::T_BIG_CHAR => array('LONGTEXT', 'MEDIUMTEXT', 'TEXT'),
			\Db::T_BIN => array('LONGBLOB', 'MEDIUMBLOB', 'BLOB', 'TINYBLOB', 'BINARY', 'VARBINARY'),
			\Db::T_DATE => array('DATE'),
			\Db::T_TIME => array('TIME'),
			\Db::T_DATETIME => array('DATETIME'),
			\Db::T_TIMESTAMP => array('TIMESTAMP')
		);
	}

	function getTables()
	{
		$tables = array();
		foreach ($this->execute('SHOW TABLES;') as $table)
			foreach ($table as $name)
				$tables[] = $name;
		return $tables;
	}
	
	function getAttributes($table)
	{
		$attributes = array();
		foreach ($this->execute('SHOW COLUMNS FROM ' . $this->protect($table, \Db::FIELD) . ';') as $attribute) {
			$type = $this->getType($attribute->Type);
			$attributes[] = object(
				'name', $attribute->Field,
				'type', $type->name,
				'size', $type->size,
				'primary_key', $attribute->Key == 'PRI',
				'auto_increment', $attribute->Extra == 'auto_increment',
				'null', $attribute->Null != 'NO',
				'default', $attribute->Default
			);
		}
		return $attributes;
	}
	
	public function getCreateTable($name, $attributes)
	{
		$createAttributes = array();
		$createPrimaryKey = array();
		foreach ($attributes as $attribute) {
			$createAttributes[] = $this->getAttributeSyntax($attribute);
			// Primary key
			if (get($attribute, k('primary_key')))
				$createPrimaryKey[] = $this->protect($attribute->name, \Db::FIELD);
		}
			
		if (count($createPrimaryKey))
			$createAttributes[] = 'PRIMARY KEY (' . implode(',', $createPrimaryKey) . ')';
		
		return 'CREATE TABLE ' . $this->protect($name, \Db::FIELD) . ' (' . implode(',',$createAttributes) . ')';
		
	}
	
	private function getAttributeSyntax($attribute)
	{
		$types = $this->getTypes();

		// Name
		$syntax = $this->protect($attribute->name, \Db::FIELD);
		// Type
		$syntax.= ' ' . $type = $types[$attribute->type][0];
		// Size
		if (!exists($attribute, 'size'))
			$attribute->size = null;
		if ($type == 'VARCHAR' and !$attribute->size)
			$attribute->size = 255;
		if ($type == 'INTEGER' and !$attribute->size)
			$attribute->size = 11;
		if ($attribute->size or $type == 'VARCHAR')
			$syntax.= '('.$attribute->size.')';
		// Null
		if (!exists($attribute, 'null'))
			$attribute->null = false;
		$syntax.= ($attribute->null ? '' : ' NOT') . ' NULL';
		// Auto increment
		if (!exists($attribute, 'auto_increment'))
			$attribute->auto_increment = false;
		if ($attribute->auto_increment)
			$syntax.= ' AUTO_INCREMENT';
		// Default
		if (!exists($attribute, 'default'))
			$attribute->default = null;
		if ($attribute->default)
			$syntax.= ' DEFAULT ' . $this->protect($attribute->default);	

		return $syntax;
	}
	
	public function getAlterTable($name, $attributes)
	{

		$alterTable = 'ALTER TABLE ' . $this->protect($name, \Db::FIELD) . chr(10);

		$deleteAttributes = $updateAttributes = $createAttributes = array();
		$lastChange = $lastAlter = null;
		foreach ($attributes as $attribute) {
			$alter = substr($attribute->alter, 0, 6);
			$after = substr($attribute->alter, 7);
			if ($alter == 'delete')
				$deleteAttributes[] = 'DROP ' . $this->protect($attribute->name, \Db::FIELD);
			elseif ($alter == 'update') {
				$updateAttributes[] = 'CHANGE ' . $this->protect($attribute->name, \Db::FIELD) . ' ' . $this->getAttributeSyntax($attribute) . ' ' . ($lastChange ? 'AFTER ' . $this->protect($lastChange, \Db::FIELD) : 'FIRST');
				$lastChange = $attribute->name;
			} elseif ($alter == 'create')
				$createAttributes[] = 'ADD ' . $this->getAttributeSyntax($attribute) . ' ' . ($lastAlter ? 'AFTER ' . $this->protect($lastAlter, \Db::FIELD) : 'FIRST');
			$lastAlter = $attribute->name;
		}
		
		$alterTable.= implode(','.chr(10),array_merge($deleteAttributes,$updateAttributes,$createAttributes)) . ';';
		
		return $alterTable;
	}

	public function getDropTable($name)
	{
		return 'DROP TABLE ' . $this->protect($name, \Db::FIELD) . ';';
	}

}
