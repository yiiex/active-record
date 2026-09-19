<?php
/**
 * DbConnection class file
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link https://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace Yii1x\ActiveRecord\Db;

use PDO;
use PDOException;
use Psr\SimpleCache\CacheInterface;
use Yii1x\ActiveRecord\Db\Schema\Cubrid\CubridSchema;
use Yii1x\ActiveRecord\Db\Schema\DbCommandBuilder;
use Yii1x\ActiveRecord\Db\Schema\DbSchema;
use Yii1x\ActiveRecord\Db\Schema\Mssql\{MssqlPdoAdapter, MssqlSchema, MssqlSqlsrvPdoAdapter};
use Yii1x\ActiveRecord\Db\Schema\Mysql\MysqlSchema;
use Yii1x\ActiveRecord\Db\Schema\Oci\OciSchema;
use Yii1x\ActiveRecord\Db\Schema\Pgsql\PgsqlSchema;
use Yii1x\ActiveRecord\Db\Schema\Sqlite\SqliteSchema;
use Yii1x\ActiveRecord\Exceptions\DbException;
use Yii1x\ActiveRecord\ORMContext;

/**
 * CDbConnection represents a connection to a database.
 *
 * CDbConnection works together with {@link DbCommand}, {@link DbDataReader}
 * and {@link DbTransaction} to provide data access to various DBMS
 * in a common set of APIs. They are a thin wrapper of the {@link https://www.php.net/manual/en/ref.pdo.php PDO}
 * PHP extension.
 *
 * To establish a connection, set {@link setActive active} to true after
 * specifying {@link connectionString}, {@link username} and {@link password}.
 *
 * The following example shows how to create a CDbConnection instance and establish
 * the actual connection:
 * <pre>
 * $connection=new CDbConnection($dsn,$username,$password);
 * $connection->active=true;
 * </pre>
 *
 * After the DB connection is established, one can execute an SQL statement like the following:
 * <pre>
 * $command=$connection->createCommand($sqlStatement);
 * $command->execute();   // a non-query SQL statement execution
 * // or execute an SQL query and fetch the result set
 * $reader=$command->query();
 *
 * // each $row is an array representing a row of data
 * foreach($reader as $row) ...
 * </pre>
 *
 * One can do prepared SQL execution and bind parameters to the prepared SQL:
 * <pre>
 * $command=$connection->createCommand($sqlStatement);
 * $command->bindParam($name1,$value1);
 * $command->bindParam($name2,$value2);
 * $command->execute();
 * </pre>
 *
 * To use transaction, do like the following:
 * <pre>
 * $transaction=$connection->beginTransaction();
 * try
 * {
 *    $connection->createCommand($sql1)->execute();
 *    $connection->createCommand($sql2)->execute();
 *    //.... other SQL executions
 *    $transaction->commit();
 * }
 * catch(Exception $e)
 * {
 *    $transaction->rollback();
 * }
 * </pre>
 *
 * CDbConnection also provides a set of methods to support setting and querying
 * of certain DBMS attributes, such as {@link getNullConversion nullConversion}.
 *
 * Since CDbConnection implements the interface IApplicationComponent, it can
 * be used as an application component and be configured in application configuration,
 * like the following,
 * <pre>
 * array(
 *     'components'=>array(
 *         'db'=>array(
 *             'class'=>'CDbConnection',
 *             'connectionString'=>'sqlite:path/to/dbfile',
 *         ),
 *     ),
 * )
 * </pre>
 *
 * Use the {@link driverName} property if you want to force the DB connection to use a particular driver
 * by the given name, disregarding of what was set in the {@link connectionString} property. This might
 * be useful when working with ODBC connections. Sample code:
 *
 * <pre>
 * 'db'=>array(
 *     'class'=>'CDbConnection',
 *     'driverName'=>'mysql',
 *     'connectionString'=>'odbc:Driver={MySQL};Server=127.0.0.1;Database=test',
 *     'username'=>'',
 *     'password'=>'',
 * ),
 * </pre>
 *
 * @property boolean $active Whether the DB connection is established.
 * @property PDO $pdoInstance The PDO instance, null if the connection is not established yet.
 * @property DbTransaction $currentTransaction The currently active transaction. Null if no active transaction.
 * @property DbSchema $schema The database schema for the current connection.
 * @property DbCommandBuilder $commandBuilder The command builder.
 * @property string $lastInsertID The row ID of the last row inserted, or the last value retrieved from the sequence object.
 * @property mixed $columnCase The case of the column names.
 * @property mixed $nullConversion How the null and empty strings are converted.
 * @property boolean $autoCommit Whether creating or updating a DB record will be automatically committed.
 * @property boolean $persistent Whether the connection is persistent or not.
 * @property string $driverName Name of the DB driver. This property is read-write since 1.1.16.
 * Before 1.1.15 it was read-only.
 * @property string $clientVersion The version information of the DB driver.
 * @property string $connectionStatus The status of the connection.
 * @property boolean $prefetch Whether the connection performs data prefetching.
 * @property string $serverInfo The information of DBMS server.
 * @property string $serverVersion The version information of DBMS server.
 * @property integer $timeout Timeout settings for the connection.
 * @property array $attributes Attributes (name=>value) that are previously explicitly set for the DB connection.
 * @property array $stats The first element indicates the number of SQL statements executed,
 * and the second element the total time spent in SQL execution.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.db
 * @since 1.0
 */
class DbConnection
{
    /**
     * @var string The Data Source Name, or DSN, contains the information required to connect to the database.
     * @see https://www.php.net/manual/en/function.PDO-construct.php
     *
     * Note that if you're using GBK or BIG5 then it's highly recommended to
     * update to PHP 5.3.6+ and to specify charset via DSN like
     * 'mysql:dbname=mydatabase;host=127.0.0.1;charset=GBK;'.
     */
    public string $connectionString;
    /**
     * @var string the username for establishing DB connection. Defaults to empty string.
     */
    public string $username = '';
    /**
     * @var string the password for establishing DB connection. Defaults to empty string.
     */
    public string $password = '';
    /**
     * @var integer number of seconds that table metadata can remain valid in cache.
     * Use 0 or negative value to indicate not caching schema.
     * If greater than 0 and the primary cache is enabled, the table metadata will be cached.
     * @see schemaCachingExclude
     */
    public int $schemaCachingDuration = 0;
    /**
     * @var array list of tables whose metadata should NOT be cached. Defaults to empty array.
     * @see schemaCachingDuration
     */
    public array $schemaCachingExclude = array();
    /**
     * @var string|null the service id of the cache used to cache table metadata.
     * Defaults to {@see CacheInterface::class}. Set to null to disable schema caching.
     * @see schemaCachingDuration
     */
    public ?string $schemaCacheID = CacheInterface::class;
    /**
     * @var integer number of seconds that query results can remain valid in cache.
     * Use 0 or negative value to indicate not caching query results (the default behavior).
     *
     * In order to enable query caching, this property must be a positive
     * integer and {@link queryCacheID} must not be null.
     *
     * The method {@link cache()} is provided as a convenient way of setting this property
     * and {@link queryCachingCount} on the fly.
     *
     * @see cache
     * @see queryCacheID
     * @since 1.1.7
     */
    public int $queryCachingDuration = 0;
    /**
     * @var integer the number of SQL statements that need to be cached next.
     * If this is 0, then even if query caching is enabled, no query will be cached.
     * Note that each time after executing a SQL statement (whether executed on DB server or fetched from
     * query cache), this property will be reduced by 1 until 0.
     * @since 1.1.7
     */
    public int $queryCachingCount = 0;
    /**
     * @var string|null the service id of the cache used for query caching.
     * Defaults to {@see CacheInterface::class}. Set to null to disable query caching.
     * @since 1.1.7
     */
    public ?string $queryCacheID = CacheInterface::class;
    /**
     * @var string|null the charset used for database connection. The property is only used
     * for MySQL, MariaDB and PostgreSQL databases. Defaults to null, meaning using default charset
     * as specified by the database.
     *
     * Note that if you're using GBK or BIG5 then it's highly recommended to
     * update to PHP 5.3.6+ and to specify charset via DSN like
     * 'mysql:dbname=mydatabase;host=127.0.0.1;charset=GBK;'.
     */
    public ?string $charset = null;
    /**
     * @var boolean whether to turn on prepare emulation. Defaults to false, meaning PDO
     * will use the native prepare support if available. For some databases (such as MySQL),
     * this may need to be set true so that PDO can emulate the prepare support to bypass
     * the buggy native prepare support. Note, this property is only effective for PHP 5.1.3 or above.
     * The default value is null, which will not change the ATTR_EMULATE_PREPARES value of PDO.
     */
    public ?bool $emulatePrepare = null;
    /**
     * @var boolean whether to log the values that are bound to a prepare SQL statement.
     * Defaults to false. During development, you may consider setting this property to true
     * so that parameter values bound to SQL statements are logged for debugging purpose.
     * You should be aware that logging parameter values could be expensive and have significant
     * impact on the performance of your application.
     */
    public bool $enableParamLogging = false;
    /**
     * @var boolean whether to enable profiling the SQL statements being executed.
     * Defaults to false. This should be mainly enabled and used during development
     * to find out the bottleneck of SQL executions.
     */
    public bool $enableProfiling = false;
    /**
     * @var string|null the default prefix for table names. Defaults to null, meaning no table prefix.
     * By setting this property, any token like '{{tableName}}' in {@link DbCommand::text} will
     * be replaced by 'prefixTableName', where 'prefix' refers to this property value.
     * @since 1.1.0
     */
    public ?string $tablePrefix = null;
    /**
     * @var array list of SQL statements that should be executed right after the DB connection is established.
     * @since 1.1.1
     */
    public $initSQLs;
    /**
     * @var array mapping between PDO driver and schema class name.
     * A schema class can be specified using path alias.
     * @since 1.1.6
     */
    public array $driverMap = [
        'cubrid' => CubridSchema::class,
        'pgsql' => PgsqlSchema::class,
        'mysqli' => MysqlSchema::class,   // MySQL
        'mysql' => MysqlSchema::class,    // MySQL,MariaDB
        'sqlite' => SqliteSchema::class,  // sqlite 3
        'sqlite2' => SqliteSchema::class, // sqlite 2
        'mssql' => MssqlSchema::class,    // Mssql driver on windows hosts
        'dblib' => MssqlSchema::class,    // dblib drivers on linux (and maybe others os) hosts
        'sqlsrv' => MssqlSchema::class,   // Mssql
        'oci' => OciSchema::class,        // Oracle driver
    ];

    /**
     * @var string Custom PDO wrapper class.
     * @since 1.1.8
     */
    public string $pdoClass = PDO::class;

    public DbSchema $schema {
        get => $this->getSchema();
    }

    private $_driverName;
    private array $_attributes = [];
    private bool $_active = false;
    private $_pdo;
    private $_transaction;
    private $_schema;


    /**
     * Constructor.
     * Note, the DB connection is not established when this connection
     * instance is created. Set {@link setActive active} property to true
     * to establish the connection.
     * @param string $dsn The Data Source Name, or DSN, contains the information required to connect to the database.
     * @param string $username The user name for the DSN string.
     * @param string $password The password for the DSN string.
     * @see https://www.php.net/manual/en/function.PDO-construct.php
     */
    public function __construct(
        string $dsn,
        string $username,
        string $password,
        public readonly string $connectionName,
        public bool $autoConnect = true,
    )
    {
        $this->connectionString = $dsn;
        $this->username = $username;
        $this->password = $password;
        if ($this->autoConnect) {
            $this->setActive(true);
        }
    }

    /**
     * Close the connection when serializing.
     * @return array
     */
    public function __sleep()
    {
        $this->close();
        return array_keys(get_object_vars($this));
    }

    /**
     * Returns a list of available PDO drivers.
     * @return array list of available PDO drivers
     * @see https://www.php.net/manual/en/function.PDO-getAvailableDrivers.php
     */
    public static function getAvailableDrivers(): array
    {
        return PDO::getAvailableDrivers();
    }

    /**
     * Returns whether the DB connection is established.
     * @return boolean whether the DB connection is established
     */
    public function getActive(): bool
    {
        return $this->_active;
    }

    /**
     * Open or close the DB connection.
     * @param boolean $value whether to open or close DB connection
     */
    public function setActive(bool $value): void
    {
        if ($value != $this->_active) {
            if ($value) {
                $this->open();
            } else {
                $this->close();
            }
        }
    }

    /**
     * Sets the parameters about query caching.
     * This method can be used to enable or disable query caching.
     * By setting the $duration parameter to be 0, the query caching will be disabled.
     * Otherwise, query results of the new SQL statements executed next will be saved in cache
     * and remain valid for the specified duration.
     * If the same query is executed again, the result may be fetched from cache directly
     * without actually executing the SQL statement.
     * @param integer $duration the number of seconds that query results may remain valid in cache.
     * If this is 0, the caching will be disabled.
     * @param integer $queryCount number of SQL queries that need to be cached after calling this method. Defaults to 1,
     * meaning that the next SQL query will be cached.
     * @return static the connection instance itself.
     * @since 1.1.7
     */
    public function cache(int $duration, int $queryCount = 1): static
    {
        $this->queryCachingDuration = $duration;
        $this->queryCachingCount = $queryCount;
        return $this;
    }

    /**
     * Opens DB connection if it is currently not
     */
    protected function open(): void
    {
        if ($this->_pdo === null) {
            if (empty($this->connectionString)) {
                throw new DbException('DSN cannot be empty.');
            }
            try {
                $this->_pdo = $this->createPdoInstance();
                $this->initConnection($this->_pdo);
                $this->_active = true;
            } catch (PDOException $e) {
                $msg = ORMContext::isDebug() ? "Connection failed: {$e->getMessage()}" : 'Database connection failed';
                throw new DbException($msg);
            }
        }
    }

    /**
     * Closes the currently active DB connection.
     * It does nothing if the connection is already closed.
     */
    protected function close(): void
    {
        $this->_pdo = null;
        $this->_active = false;
        $this->_schema = null;
    }

    /**
     * Creates the PDO instance.
     * When some functionalities are missing in the pdo driver, we may use
     * an adapter class to provide them.
     * @return PDO the pdo instance
     * @throws DbException when failed to open DB connection
     */
    protected function createPdoInstance(): PDO
    {
        $pdoClass = $this->pdoClass;
        if (($driver = $this->getDriverName()) !== null) {
            $pdoClass = match ($driver) {
                'mssql', 'dblib' => MssqlPdoAdapter::class,
                'sqlsrv' => MssqlSqlsrvPdoAdapter::class,
                default => $pdoClass,
            };
        }
        if ($pdoClass === null) {
            throw new DbException(sprintf('DbConnection is unable to find PDO class "%s". Make sure PDO is installed correctly.', $pdoClass));
        }
        return new $pdoClass($this->connectionString, $this->username, $this->password, $this->_attributes);
    }

    /**
     * Initializes the open db connection.
     * This method is invoked right after the db connection is established.
     * The default implementation is to set the charset for MySQL, MariaDB and PostgreSQL database connections.
     * @param PDO $pdo the PDO instance
     */
    protected function initConnection($pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->emulatePrepare !== null && constant('PDO::ATTR_EMULATE_PREPARES'))
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $this->emulatePrepare);
        if (PHP_VERSION_ID >= 80100 && strncasecmp($this->getDriverName(), 'sqlite', 6) === 0)
            $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
        if ($this->charset !== null) {
            $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            if (in_array($driver, ['pgsql', 'mysql', 'mysqli']))
                $pdo->exec('SET NAMES ' . $pdo->quote($this->charset));
        }
        if ($this->initSQLs !== null) {
            foreach ($this->initSQLs as $sql)
                $pdo->exec($sql);
        }
    }

    /**
     * Returns the PDO instance.
     * @return null|PDO the PDO instance, null if the connection is not established yet
     */
    public function getPdoInstance(): ?PDO
    {
        return $this->_pdo;
    }

    /**
     * Creates a command for execution.
     * @param mixed|null $query the DB query to be executed. This can be either a string representing a SQL statement,
     * or an array representing different fragments of a SQL statement. Please refer to {@link DbCommand::__construct}
     * for more details about how to pass an array as the query. If this parameter is not given,
     * you will have to call query builder methods of {@link DbCommand} to build the DB query.
     * @return DbCommand the DB command
     */
    public function createCommand(mixed $query = null): DbCommand
    {
        $this->setActive(true);
        return new DbCommand($this, $query);
    }

    /**
     * Returns the currently active transaction.
     * @return DbTransaction|null the currently active transaction. Null if no active transaction.
     */
    public function getCurrentTransaction(): ?DbTransaction
    {
        if ($this->_transaction !== null) {
            if ($this->_transaction->getActive())
                return $this->_transaction;
        }
        return null;
    }

    /**
     * Starts a transaction.
     * @return DbTransaction the transaction initiated
     */
    public function beginTransaction(): DbTransaction
    {
        $this->setActive(true);
        $this->_pdo->beginTransaction();
        return $this->_transaction = new DbTransaction($this);
    }

    /**
     * Returns the database schema for the current connection
     * @return DbSchema the database schema for the current connection
     * @throws DbException
     */
    public function getSchema(): DbSchema
    {
        if ($this->_schema !== null) {
            return $this->_schema;
        } else {
            $driver = $this->getDriverName();
            if (isset($this->driverMap[$driver])) {
                return $this->_schema = new $this->driverMap[$driver]($this);
            } else {
                throw new DbException(sprintf("DbConnection does not support reading schema for %s database.", $driver));
            }
        }
    }

    /**
     * Returns the SQL command builder for the current DB connection.
     * @return DbCommandBuilder the command builder
     */
    public function getCommandBuilder(): DbCommandBuilder
    {
        return $this->getSchema()->getCommandBuilder();
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     * @param string $sequenceName name of the sequence object (required by some DBMS)
     * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
     * @see https://www.php.net/manual/en/function.PDO-lastInsertId.php
     */
    public function getLastInsertID(string $sequenceName = '')
    {
        $this->setActive(true);
        return $this->_pdo->lastInsertId($sequenceName);
    }

    /**
     * Quotes a string value for use in a query.
     * @param string $str string to be quoted
     * @return float|int|string the properly quoted string
     * @see https://www.php.net/manual/en/function.PDO-quote.php
     */
    public function quoteValue(mixed $str): float|int|string
    {
        if (is_int($str) || is_float($str)) {
            return $str;
        }

        $this->setActive(true);
        return $this->quoteValueInternal($str, PDO::PARAM_STR);
    }

    /**
     * Quotes a value for use in a query using a given type.
     * @param mixed $value the value to be quoted.
     * @param integer $type The type to be used for quoting.
     * This should be one of the `PDO::PARAM_*` constants described in
     * {@link https://www.php.net/manual/en/pdo.constants.php PDO documentation}.
     * This parameter will be passed to the `PDO::quote()` function.
     * @return string the properly quoted string.
     * @see https://www.php.net/manual/en/function.PDO-quote.php
     * @since 1.1.18
     */
    public function quoteValueWithType(mixed $value, int $type): string
    {
        $this->setActive(true);
        return $this->quoteValueInternal($value, $type);
    }

    /**
     * Quotes a value for use in a query using a given type. This method is internally used.
     * @param mixed $value
     * @param int $type
     * @return string
     */
    private function quoteValueInternal(mixed $value, int $type): string
    {
        if (mb_stripos($this->connectionString, 'odbc:') === false) {
            if (($quoted = $this->_pdo->quote($value, $type)) !== false)
                return $quoted;
        }

        // fallback for drivers that don't support quote (e.g. oci and odbc)
        return "'" . addcslashes(str_replace("'", "''", $value), "\000\n\r\\\032") . "'";
    }

    /**
     * Quotes a table name for use in a query.
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quoteTableName(string $name): string
    {
        return $this->getSchema()->quoteTableName($name);
    }

    /**
     * Quotes a column name for use in a query.
     * If the column name contains prefix, the prefix will also be properly quoted.
     * @param string $name column name
     * @return string the properly quoted column name
     */
    public function quoteColumnName(string $name): string
    {
        return $this->getSchema()->quoteColumnName($name);
    }

    /**
     * Determines the PDO type for the specified PHP type.
     * @param string $type The PHP type (obtained by gettype() call).
     * @return integer the corresponding PDO type
     */
    public function getPdoType(string $type): int
    {
        return match($type) {
            'boolean' => PDO::PARAM_BOOL,
            'integer' => PDO::PARAM_INT,
            'resource' => PDO::PARAM_LOB,
            'NULL' => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    /**
     * Returns the case of the column names
     * @return mixed the case of the column names
     * @see https://www.php.net/manual/en/pdo.setattribute.php
     */
    public function getColumnCase(): mixed
    {
        return $this->getAttribute(PDO::ATTR_CASE);
    }

    /**
     * Sets the case of the column names.
     * @param mixed $value the case of the column names
     * @see https://www.php.net/manual/en/pdo.setattribute.php
     */
    public function setColumnCase(mixed $value): void
    {
        $this->setAttribute(PDO::ATTR_CASE, $value);
    }

    /**
     * Returns how the null and empty strings are converted.
     * @return mixed how the null and empty strings are converted
     * @see https://www.php.net/manual/en/pdo.setattribute.php
     */
    public function getNullConversion(): mixed
    {
        return $this->getAttribute(PDO::ATTR_ORACLE_NULLS);
    }

    /**
     * Sets how the null and empty strings are converted.
     * @param mixed $value how the null and empty strings are converted
     * @see https://www.php.net/manual/en/pdo.setattribute.php
     */
    public function setNullConversion(mixed $value): void
    {
        $this->setAttribute(PDO::ATTR_ORACLE_NULLS, $value);
    }

    /**
     * Returns whether creating or updating a DB record will be automatically committed.
     * Some DBMS (such as sqlite) may not support this feature.
     * @return boolean whether creating or updating a DB record will be automatically committed.
     */
    public function getAutoCommit(): bool
    {
        return $this->getAttribute(PDO::ATTR_AUTOCOMMIT);
    }

    /**
     * Sets whether creating or updating a DB record will be automatically committed.
     * Some DBMS (such as sqlite) may not support this feature.
     * @param boolean $value whether creating or updating a DB record will be automatically committed.
     */
    public function setAutoCommit(bool $value): void
    {
        $this->setAttribute(PDO::ATTR_AUTOCOMMIT, $value);
    }

    /**
     * Returns whether the connection is persistent or not.
     * Some DBMS (such as sqlite) may not support this feature.
     * @return boolean whether the connection is persistent or not
     */
    public function getPersistent(): bool
    {
        return $this->getAttribute(PDO::ATTR_PERSISTENT);
    }

    /**
     * Sets whether the connection is persistent or not.
     * Some DBMS (such as sqlite) may not support this feature.
     * @param boolean $value whether the connection is persistent or not
     */
    public function setPersistent(bool $value): void
    {
        $this->setAttribute(PDO::ATTR_PERSISTENT, $value);
    }

    /**
     * Returns the name of the DB driver.
     * @return string|null name of the DB driver.
     */
    public function getDriverName(): ?string
    {
        if ($this->_driverName !== null)
            return $this->_driverName;
        elseif (($pos = strpos($this->connectionString, ':')) !== false)
            return $this->_driverName = strtolower(substr($this->connectionString, 0, $pos));
        return null;
    }

    /**
     * Changes the name of the DB driver. Overrides value extracted from the {@link connectionString},
     * which is behavior by default.
     * @param string $driverName to be set. Valid values are the keys from the {@link driverMap} property.
     * @see getDriverName
     * @see driverName
     * @since 1.1.16
     */
    public function setDriverName(string $driverName): void
    {
        $this->_driverName = strtolower($driverName);
    }

    /**
     * Returns the version information of the DB driver.
     * @return string the version information of the DB driver
     */
    public function getClientVersion(): string
    {
        return $this->getAttribute(PDO::ATTR_CLIENT_VERSION);
    }

    /**
     * Returns the status of the connection.
     * Some DBMS (such as sqlite) may not support this feature.
     * @return string the status of the connection
     */
    public function getConnectionStatus(): string
    {
        return $this->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    }

    /**
     * Returns whether the connection performs data prefetching.
     * @return boolean whether the connection performs data prefetching
     */
    public function getPrefetch(): bool
    {
        return $this->getAttribute(PDO::ATTR_PREFETCH);
    }

    /**
     * Returns the information of DBMS server.
     * @return string the information of DBMS server
     */
    public function getServerInfo(): string
    {
        return $this->getAttribute(PDO::ATTR_SERVER_INFO);
    }

    /**
     * Returns the version information of DBMS server.
     * @return string the version information of DBMS server
     */
    public function getServerVersion(): string
    {
        return $this->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * Returns the timeout settings for the connection.
     * @return integer timeout settings for the connection
     */
    public function getTimeout(): int
    {
        return $this->getAttribute(PDO::ATTR_TIMEOUT);
    }

    /**
     * Obtains a specific DB connection attribute information.
     * @param integer $name the attribute to be queried
     * @return mixed the corresponding attribute information
     * @see https://www.php.net/manual/en/function.PDO-getAttribute.php
     */
    public function getAttribute(int $name): mixed
    {
        $this->setActive(true);
        return $this->_pdo->getAttribute($name);
    }

    /**
     * Sets an attribute on the database connection.
     * @param integer $name the attribute to be set
     * @param mixed $value the attribute value
     * @see https://www.php.net/manual/en/function.PDO-setAttribute.php
     */
    public function setAttribute(int $name, mixed $value): void
    {
        if ($this->_pdo instanceof PDO)
            $this->_pdo->setAttribute($name, $value);
        else
            $this->_attributes[$name] = $value;
    }

    /**
     * Returns the attributes that are previously explicitly set for the DB connection.
     * @return array attributes (name=>value) that are previously explicitly set for the DB connection.
     * @see setAttributes
     * @since 1.1.7
     */
    public function getAttributes(): array
    {
        return $this->_attributes;
    }

    /**
     * Sets a set of attributes on the database connection.
     * @param array $values attributes (name=>value) to be set.
     * @see setAttribute
     * @since 1.1.7
     */
    public function setAttributes(array $values): void
    {
        foreach ($values as $name => $value)
            $this->_attributes[$name] = $value;
    }

    /**
     * Returns the statistical results of SQL executions.
     * The results returned include the number of SQL statements executed and
     * the total time spent.
     * In order to use this method, {@link enableProfiling} has to be set true.
     * @return array the first element indicates the number of SQL statements executed,
     * and the second element the total time spent in SQL execution.
     */
    public function getStats(): array
    {
        return []; //TODO
    }
}
