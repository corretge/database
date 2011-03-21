# fluxbb-database
A lightweight wrapper around [PHP::PDO](http://www.php.net/manual/en/book.pdo.php), providing basic SQL abstraction and a more compact API.

Abstraction can be split into 2 different types - driver abstraction, and SQL syntax abstraction. The SQL syntax abstraction we perform has 2 goals:
 * Allowing portability between different DBMS.
 * Allowing queries to be easily modified by hooks and/or filters before execution.

## Supported drivers
 * [Any supported by PDO](http://www.php.net/manual/en/pdo.drivers.php)

## Supported dialects
 * MySQL
 * SQLite3
 * PostgreSQL

## License
[LGPL - GNU Lesser General Public License](http://www.gnu.org/licenses/lgpl.html)

## API overview

### __construct()

	Database::__construct(string $dsn [, array $args = array() [, string $dialect = null]])


	$db = new Database('sqlite::memory:', array('debug' => true), 'sqlite');


## Query structures

### SELECT

	$query = new SelectQuery(array('tid' => 't.id AS tid', 'time' => 't.time', 'fieldname' => 't.fieldname', 'uid' => 'u.id AS uid', 'username' => 'u.username'), 'topics AS t');
	
	$query->joins['u'] = new InnerJoin('users AS u');
	$query->joins['u']->on = 'u.id = t.user_id';
	
	$query->where = 't.time > :now';
	$query->group_by = array('tid' => 't.id');
	$query->order_by = array('time' => 't.time DESC');
	$query->limit = 25;
	$query->offset = 100;


	SELECT t.id AS tid, t.time, t.fieldname, u.id AS uid, u.username FROM topics AS t INNER JOIN users AS u ON (u.id = t.user_id) WHERE (t.time > :now) GROUP BY t.id ORDER BY t.time DESC LIMIT 25 OFFSET 100

### UPDATE

	$query = new UpdateQuery(array('user_id' => ':user_id'), 'topics');
	$query->where = 'id > :tid';
	$query->order_by = array('id' => 'id DESC');
	$query->limit = 1;
	$query->offset = 100;


	UPDATE topics SET user_id = :user_id WHERE id > :tid ORDER BY id DESC LIMIT 1 OFFSET 100

### INSERT

	$query = new InsertQuery(array('time' => ':now', 'fieldname' => ':fieldname', 'user_id' => ':user_id'), 'topics');


	INSERT INTO topics(time, fieldname, user_id) VALUES (:now, :fieldname, :user_id)

### REPLACE

	$query = new ReplaceQuery(array('id' => ':tid', 'time' => ':now', 'fieldname' => ':fieldname', 'user_id' => ':user_id'), 'topics', 'id');


	REPLACE INTO topics(id, time, fieldname, user_id) VALUES(:tid, :now, :fieldname, :user_id)

### DELETE

	$query = new DeleteQuery('topics');
	$query->where = 'time < :now';
	$query->order_by = array('time' => 'time DESC');
	$query->limit = 25;
	$query->offset = 100;


	DELETE FROM topics WHERE time < :now ORDER BY time DESC LIMIT 25 OFFSET 100

### TRUNCATE

	$query = new TruncateQuery('topics');


	TRUNCATE TABLE topics
