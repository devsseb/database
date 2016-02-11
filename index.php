<?

/*TODO :
 *
 *	shortcut pour findwith
 */

try {
	include('varslib/varslib.inc.php');
	Debug::active();
	include('database/Database.class.php');

	$mysql_database = '';
	$mysql_user = '';
	$mysql_password = '';

	$dns_mysql = array(
		'dsn' => 'mysql:dbname=' . $mysql_database . ';charset=utf8',
		'user' => $mysql_user,
		'password' => $mysql_password,
		'driver_options' => array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8')
	);
	$dns_sqlite = array('dsn' => 'sqlite:db.sqlite', 'driver_options' => array('charset'=> 'UTF-8'));

	$db = new Database($dns_mysql);
//	exit($db->compile());

Debug::chronoStart();

	$cat = \Database\CategorieTable::findAllLimit2_2();
	trace($cat);
	$cat = \Database\CategorieTable::findAll();
	trace($cat->findAllLimit2_2());
	
quit('Chrono : ' . Debug::chronoGet(), 'Dbtotal : ' . $db->getTotal());


	
//	quit($db2->compile());


/*
	$c = \Database\Bin\UserTable::findAll();
	foreach ($c as $l) {
		trace('[' . $l->id . '] ' . $l->name . ' : ' . $l->mail);
	}

	$u = new Db\User(2, \Database::L_ALL);
	trace($u->name);
	trace($u->project->name);
	
quit();
*/


	showUser(1);
	showProject(2);

quit();
	$q = new \Database\Query();
	$q->from('project', 'user');
	$r = $q->execute();
	
	foreach ($r as $l) {
		trace($l->name);
//		tracec('blue', $l->project ? $l->project->name : null);
		$total = count($l->userCollection);
//		for ($i = 0; $i < $total; $i++)
//			tracec('blue', $l->userCollection[$i]->name);
		foreach ($l->userCollection as $u)
			tracec('blue', $u->name);
	}
quit($db2->getTotal());

	$c = \Database\Bin\UserTable::findLikeNameMail('%e%', '%@gmail.com');
//	$c = \Database\Bin\UserTable::findAll();
//	quit($c->name . ' : ' . $c->mail);
	foreach ($c as $l)
		trace($l->name . ' : ' . $l->mail);
	
	quit($db2->getTotal());

	$e = new \Database\Bin\User(2, Database::EE_ALL);
	showUser($e);

	$e = new Db\Project(2, Database::EE_ALL);
	showProject($e);

	quit($db2->getTotal());



	foreach ($c as $user) {
		trace($user->getName());
	}

	quit();

/*	
	$q = new DatabaseQuery();
	$q->from('user')->where('id=2')->one();

	quit($q->execute());
	
//	$user->save();
*/

} catch (Exception $e) {
	quit($e);
}

?>
