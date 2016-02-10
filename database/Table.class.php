<?
namespace Db\Object;

include_once __DIR__ . '/Collection.class.php';

abstract class Table
{

	public static function getClass()
	{
		return get_called_class();
	}

	public static function getName()
	{
		$class = self::getClass();
		return strtolower(substr($class, 3, strlen($class) - 8));
	}

	public static function getEntityClass()
	{
		$class = self::getClass();
		return substr($class, 0, strlen($class) - 5);		
	}

	public static function getEntityName()
	{
		$class = self::getEntityClass();
		return substr($class, 3);
	}

	public static function getId()
	{
		return static::$id;
	}

	public static function getDatabaseName()
	{
		return static::$name;
	}
	
	public static function getAttributes()
	{
		return static::$attributes;
	}
	
	public static function getAttributesFactorized()
	{
		return static::$attributesFactorized;
	}
	
	public static function getAttributesExtended()
	{
		return static::$attributesExtended;
	}
	
	public static function getAttribute($position)
	{
		return get(static::$attributes, k($position));
	}
	
	public static function getLinks()
	{
		if (static::$links and is_array(current(static::$links))) {
				foreach (static::$links as &$link)
					$link = object($link);
				unset($link);
		}
		return static::$links;
	}

	public static function __callStatic($name, $args)
	{
		if (strpos($name, 'find') === 0)
			return self::find(substr($name, 4), $args);
			
		if (strpos($name, 'count') === 0)
			return self::count(substr($name, 5), $args);
	}

	public static function find($what = '', $values = null)
	{

		$parse = \Db::parseFindString(get_called_class(), $what, $values);

		$query = new \Db\Object\Query();
		$query->one($parse->one)->from(self::getName());
		foreach ($parse->with as $with)
			$query->attributeJoin(self::getClass(), $with);

		foreach ($parse->order as $order => $direction)
			$query->orderBy($order, $direction);

		if ($parse->limit)
			$query->limit($parse->limit->offset, $parse->limit->total);

		self::applyWhere($query, $parse->what);

		return $query->execute();
	}
	
	public static function count($what = '', $values = null)
	{
		$parse = \Db::parseFindString(get_called_class(), $what, $values);

		$query = new \Db\Object\Query();
		$query->select('COUNT(*) AS total')->one(true)->from(self::getName());
		foreach ($parse->with as $with)
			$query->attributeJoin(self::getClass(), $with);

		foreach ($parse->order as $order => $direction)
			$query->orderBy($order, $direction);

		if ($parse->limit)
			$query->limit($parse->limit->offset, $parse->limit->total);

		self::applyWhere($query, $parse->what);

		return geta($query->execute(), k('total'));
	}

	private static function applyWhere(&$query, $what)
	{
		$db = \Db::getInstance();
		foreach ($what as $find) {
			$whereFunc = get($find, k('operator'), 'and') . 'Where';
			$where = $db->protect(self::getName(), \Db::FIELD) . '.' . $db->protect($find->attribute, \Db::FIELD) . ' ';

			if ($find->type == 'like')
				$where.= 'LIKE';
			elseif ($find->type == 'lower')
				$where.= '<';
			elseif ($find->type == 'upper')
				$where.= '>';
			elseif ($find->type == 'lowere')
				$where.= '<=';
			elseif ($find->type == 'uppere')
				$where.= '>=';
			else
				$where.= '=';
			$where.= ' :?';
			
			$query->$whereFunc($where, $find->type == 'is' ? 1 : $find->value);
		}
	}

}
