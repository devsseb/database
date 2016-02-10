<?
class Db
{
	private static $instance;
	
	// Path to the entities sources and compiles
	private $pathConnectors, $pathCompilation, $pathSrc, $pathBin;
	
	// Const for values protect
	const STRING	= PDO::PARAM_STR;
	const INTEGER	= PDO::PARAM_INT;
	const NULL		= PDO::PARAM_NULL;
	const OBJECT	= PDO::PARAM_LOB;
	const BOOLEAN	= PDO::PARAM_BOOL;
	const FIELD		= 10;
	
	// Constants for attributes table
	//	Numbers
	const T_INTEGER = 'INTEGER';
	const T_BIG_INTEGER = 'BIG_INTEGER';
	const T_DECIMAL = 'DECIMAL';
	const T_FLOAT = 'FLOAT';
	const T_BOOL = 'BOOLEAN';
	//	Date
	const T_DATE = 'DATE';
	const T_TIME = 'TIME';
	const T_DATETIME = 'DATETIME';
	const T_TIMESTAMP = 'TIMESTAMP';
	//	Characters
	const T_CHAR = 'CHAR';
	const T_BIG_CHAR = 'BIG_CHAR';
	//	Binary
	const T_BIN = 'BIN';

	// Constants for tables relationships
	const R_1_1 = 11;
	const R_1_N = 19;
	const R_N_1 = 91;
	const R_N_N = 99;
	
	// Constants for tables action relationships
	const R_NONE = 0;
	const R_ALL = 411;
	
	// Store Data Source Name and options
	private static $options;
	// Store pdo object
	private static $connector, $pdo;
	
	private static $initialized = false;
	
	private static $findTypes = array('by', 'like', 'is', 'lower', 'upper', 'lowere', 'uppere');
	private static $findOperators = array('and', 'or');
	
	
	public function __construct($pdo = null, $options = null, $pathBin = null)
	{
		self::$instance = $this;
	
		// Set default pdo data
		if (!self::$pdo)
		 	self::$pdo = object(
				'dsn', null,
				'user', null,
				'password', null,
				'driver_options', array()
			);
		// Set default options data
		if (!self::$options)
			self::$options = object(
				'prefix', '',
				'mails', array()
			);

		// Format new pdo data
		if (is_string($pdo))
			$pdo = object('dsn', $pdo);
		elseif (is_array($pdo))
			$pdo = object($pdo);

		// Apply new pdo data
		self::$pdo = object((array)$pdo + (array)self::$pdo);
		// Apply new options data
		self::$options = object((array)$options + (array)self::$options);

		// Build paths
		$this->pathConnectors = __DIR__ . '/connectors/';
		$this->pathCompilation = __DIR__ . '/compilation/';
		$this->pathSrc = __DIR__ . '/src/';
		$this->pathBin = $pathBin ? $pathBin : __DIR__ . '/bin/';

		$this->connect();
	}

	/*
	 * 
	 * @return Database instance
	 */
	public static function getInstance()
	{
		return self::$instance;
	}

	/*
	 * Execute a method from connector
	 *
	 */
	public function __call($name, $args)
	{
		return call_user_func_array(array(self::$connector, $name), $args);
	}

	/*
	 * Connection to database
	 *
	 */
	public function connect()
	{
		if (!self::$pdo->dsn)
			return;
	
		$connector = ucfirst(substr(self::$pdo->dsn, 0, strpos(self::$pdo->dsn, ':')));

		if (!is_file($file = $this->pathConnectors . $connector . '.class.php'))
			throw new Exception('Database error : Connector "' . $connector . '" not found');
		
		include_once $this->pathConnectors . 'Connector.class.php';
		include_once $file;
		$connector = '\\Db\\Connector\\' . $connector;
		self::$connector = new $connector(self::$pdo, self::$options);
		
		if (!self::$initialized) {

			include_once __DIR__ . '/Query.class.php';
			include_once __DIR__ . '/QueryResult.class.php';
		
			// Set the autoloader for tables and entities
			$pathBin = $this->pathBin;
			spl_autoload_register(function($name) use ($pathBin) {

				$name = explode('\\', $name);
				$name = end($name);

				$file = $pathBin . $name . '.class.php';
				// Check if file class exists (with case sensitive)
				if (is_file($file) and basename(realpath($file)) == basename($file))
					include_once($file);
			});
			
			// Set the persistant system
			set_error_handler(function($no, $message) {

				preg_match('/Argument ([0-9]+) .* instance of ([\\\\\w]+),/', $message, $match);
				$class = get($match, k(2));

				if (
					!($arg = get($match, k(1))) ||
					!method_exists($class, '__from')
				)
					return false;

				$parent = geta(debug_backtrace(), k(1));
				$parent['args'][$arg - 1] = $class::__from($parent['args'][$arg - 1]);
			});

			self::$initialized = true;
		}
		
	}
	
	/*
	 * Apply the src on database and generate tables and entities classes
	 *
	 */
	public function compile($dirs = null)
	{
		if (!$dirs)
			$dirs = array($this->pathSrc);
	
		// Include abstract classes for sources

		include_once $this->pathCompilation . 'TableSrc.class.php';
		include_once $this->pathCompilation . 'EntitySrc.class.php';

		$tables = array();
		// Retrieve tables and entities source list in src path
		foreach ($dirs as $pathSrc) {
			$tablesFiles = array_diff(scandir($pathSrc), array('..', '.'));
			foreach ($tablesFiles as $table) {

				// Retain only tables
				if ('Table.class.php' != substr($table, strlen($table) - 15))
					continue;

				// Include table source
				include_once $pathSrc . $table;
			
				$table = substr($table, 0, strlen($table) - 15);

				if (is_file($pathSrc . $table . '.class.php')) {
					$entityClass = '\\Db\\Src\\' . $table;
					include_once $pathSrc . $table . '.class.php';
				} else {
					$entityClass = '\\Db\\Src\\__DefaultEntity__';
					include_once $this->pathCompilation . 'EntitySrcDefault.class.php';
				}
				$tableClass = '\\Db\\Src\\' . $table . 'Table';
				$tableSrc = new $tableClass();
				$entitySrc = new $entityClass($tableSrc);

				$table = object(
					'tableInstance', $tableSrc,
					'entityInstance', $entitySrc,
					'attributes', $tableSrc->getAttributes(),
					'links', $tableSrc->getLinks()
				);

				$tableClass = substr($tableClass, strrpos($tableClass, '\\') + 1);
				$tables[$tableClass] = $table;

			}
		}

		// Create links for "onTables"
		foreach ($tables as $tableClass => $table) {
			
			foreach ($table->links as $link) {
				if (!exists($link, 'auto')) {

					$newLink = object('auto', true);

					if ($link->relationship == \Db::R_1_N)
						$newLink->relationship = \Db::R_N_1;
					elseif ($link->relationship == \Db::R_N_1)
						$newLink->relationship = \Db::R_1_N;
					else
						$newLink->relationship = $link->relationship;
					
					$newLink->attribute = $link->onAttribute;
					$newLink->attributeFactorized = $link->onAttributeFactorized;
					
					$newLink->onTableClass = '\\Db\\' . $tableClass;
					$newLink->onAttribute = $link->attribute;
					$newLink->onAttributeFactorized = $link->attributeFactorized;

					$newLink->nnTable = $link->nnTable;
					$newLink->nnAttribute = $link->nnOnAttribute;
					$newLink->nnOnAttribute = $link->nnAttribute;
					$newLink->nnAttributes = $link->nnAttributes;
					
					$onTableClass = substr($link->onTableClass, strrpos($link->onTableClass, '\\') + 1);

					// Check if 
					$tables[$onTableClass]->links[] = $newLink;

				}

			}

		}

		foreach (array_diff(scandir($this->pathBin), array('.', '..')) as $file)
			if (substr($file, -10) == '.class.php')
				unlink($this->pathBin . $file);

		// Generate tables and entities class files
		$applies = array();
		foreach ($tables as $tableClass => $table) {

			$applies[$table->tableInstance->getName()] = $table->tableInstance->getAttributes();

			foreach ($table->links as $link)
				if ($link->relationship == \Db::R_N_N and !property_exists($link, 'auto'))
					$applies[$link->nnTable] = $link->nnAttributes;

			$class = $table->tableInstance->getEntityName();

			file_put_contents($this->pathBin . $class . 'Table.class.php',
				$table->tableInstance->compile($table->links)
			);

			file_put_contents($this->pathBin . $class . '.class.php',
				$table->entityInstance->compile($table)
			);

		}

		$this->apply($applies);

	}

	// Apply entity to database
	public function apply($applies)
	{

		$db = \Db::getInstance();

		$tables = array_flip($db->getTables());

		foreach ($applies as $tableName => $tableAttributes) {

			$create = !array_key_exists($tableName, $tables);

			// Create table
			if ($create) {
			
				$create = $db->getCreateTable($tableName, $tableAttributes);

				$db->execute($create);
			} else {
		
				// Retrieve attributes in database
				$attributesDb = array();
				foreach ($db->getAttributes($tableName) as $i => $attribute) {
					$attribute->alter = 'delete';
					$attributesDb[$attribute->name] = $attribute;
				}
			
				// Set list of attributes with attributes in database
				$attributes = array();
				foreach ($tableAttributes as $attribute) {
					if (exists($attributesDb, $attribute->name)) {
						$attribute = $attribute;
						$attribute->alter = 'update';
						$attributes[] = $attribute;
						unset($attributesDb[$attribute->name]);
					} else {
						$attribute->alter = 'create';
						$attributes[] = $attribute;
					}
				}

				$attributes = array_merge($attributesDb, $attributes);

				$alter = $db->getAlterTable($tableName, $attributes);

				$db->executeMultiple($alter);
				
				unset($tables[$tableName]);
			}

		}
		
		foreach ($tables as $table => $null)
			$db->execute($db->getDropTable($table));
	}
	
	public static function parseFindString($table, $string, $values)
	{
		// Build where with $what string
		preg_match_all('/[A-Z][a-z0-9_]*/', $string, $explode);
		$explode = $explode[0];

		$what = array();
		$with = array();
		$order = array();
		$limit = array();
		
		if (!$one = (!$what and !$table::getId())) {

			if ($one = (count($explode) and $explode[0] == 'One'))
				array_shift($explode);
	
			$lastType = self::$findTypes[0];
			$all = false;
			$find = object();
			$isWith = false;
			$isOrder = false;
			$isAsc = true;
			foreach ($explode as $part) {
				$part = strtolower($part);
				if ($part == 'all')
					$all = true;
				elseif (in_array($part, self::$findOperators)) {
					
					$find->operator = $part;
	
				} elseif (!$all and in_array($part, self::$findTypes)) {

					if (!exists($find, 'operator') and $what)
						$find->operator = self::$findOperators[0];
		
					$lastType = $find->type = $part;

					$isWith = false;
					$isOrder = false;

				} elseif ($part == 'with') {
			
					$isWith = $find->with = true;
					$isOrder = $find->order = false;
				
				} elseif ($part == 'order' or $part == 'orderasc' or $part == 'orderdesc') {
			
					$isWith = $find->with = false;
					$isOrder = $find->order = true;
				
					$isAsc = !(substr($part, 5) == 'desc');
				
				} elseif (preg_match('/limit([0-9]+)(?:_([0-9]+))?/', $part, $match)) {
					
					$limit = object(
						'offset', array_key_exists(2, $match) ? $match[1] : 0,
						'total', array_key_exists(2, $match) ? $match[2] : $match[1]
					);
					
				} else {

					if ($isWith)
						$with[] = $part;
					elseif ($isOrder)
						$order[$part] = $isAsc ? 'asc' : 'desc';
					elseif (!$all) {
						if (!exists($find, 'operator') and $what)
							$find->operator = self::$findOperators[0];
			
						if (!exists($find, 'type'))
							$find->type = $lastType;
	
						$find->attribute = $part;
						if ($find->type != 'is')
							$find->value = array_shift($values);

						$what[] = $find;
					}
					$find = object();
					
					
				}

			}
		
			if (!$all and !$what and !$order) {
				if (!$all = !($value = $with ? $values : $string)) {
					$what[] = object(
						'type', 'by',
						'attribute', $table::getId(),
						'value', $value
					);
					$one = true;
				}
			}
		}

		return object(
			'what', $what,
			'with', $with,
			'order', $order,
			'limit', $limit,
			'one', $one
		);
	}

}
