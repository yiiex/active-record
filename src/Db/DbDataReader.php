<?php
/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link https://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace Yii1x\ActiveRecord\Db;

use Countable;
use Iterator;
use PDO;
use Yii1x\ActiveRecord\Exceptions\DbException;

/**
 * CDbDataReader represents a forward-only stream of rows from a query result set.
 *
 * To read the current row of data, call {@link read}. The method {@link readAll}
 * returns all the rows in a single array.
 *
 * One can also retrieve the rows of data in CDbDataReader by using foreach:
 * <pre>
 * foreach($reader as $row)
 *     // $row represents a row of data
 * </pre>
 * Since CDbDataReader is a forward-only stream, you can only traverse it once.
 *
 * It is possible to use a specific mode of data fetching by setting
 * {@link setFetchMode FetchMode}. See {@link https://www.php.net/manual/en/function.PDOStatement-setFetchMode.php}
 * for more details.
 *
 * @property boolean $isClosed Whether the reader is closed or not.
 * @property integer $rowCount Number of rows contained in the result.
 * @property integer $columnCount The number of columns in the result set.
 * @property mixed $fetchMode Fetch mode.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.db
 * @since 1.0
 */
class DbDataReader implements Iterator, Countable
{
    private \PDOStatement $_statement;
    private bool $_closed = false;
    private $_row = null;
    private int $_index = -1;

    /**
     * Constructor.
     * @param DbCommand $command the command generating the query result
     */
    public function __construct(DbCommand $command)
    {
        $this->_statement = $command->getPdoStatement();
        $this->_statement->setFetchMode(PDO::FETCH_ASSOC);
    }

    /**
     * Binds a column to a PHP variable.
     * When rows of data are being fetched, the corresponding column value
     * will be set in the variable. Note, the fetch mode must include PDO::FETCH_BOUND.
     * @param mixed $column Number of the column (1-indexed) or name of the column
     * in the result set. If using the column name, be aware that the name
     * should match the case of the column, as returned by the driver.
     * @param mixed $value Name of the PHP variable to which the column will be bound.
     * @param integer|null $dataType Data type of the parameter
     * @see https://www.php.net/manual/en/function.PDOStatement-bindColumn.php
     */
    public function bindColumn(mixed $column, mixed &$value, ?int $dataType = null): void
    {
        if ($dataType === null)
            $this->_statement->bindColumn($column, $value);
        else
            $this->_statement->bindColumn($column, $value, $dataType);
    }

    /**
     * Set the default fetch mode for this statement
     * @param mixed $mode fetch mode
     * @see https://www.php.net/manual/en/function.PDOStatement-setFetchMode.php
     */
    public function setFetchMode(mixed $mode): void
    {
        $params = func_get_args();
        call_user_func_array(array($this->_statement, 'setFetchMode'), $params);
    }

    /**
     * Advances the reader to the next row in a result set.
     * @return array|false the current row, false if no more row available
     */
    public function read(): false|array
    {
        return $this->_statement->fetch();
    }

    /**
     * Returns a single column from the next row of a result set.
     * @param integer $columnIndex zero-based column index
     * @return mixed|false the column of the current row, false if no more row available
     */
    public function readColumn(int $columnIndex): mixed
    {
        return $this->_statement->fetchColumn($columnIndex);
    }

    /**
     * Returns an object populated with the next row of data.
     * @param string $className class name of the object to be created and populated
     * @param array $fields Elements of this array are passed to the constructor
     * @return mixed|false the populated object, false if no more row of data available
     */
    public function readObject(string $className, array $fields): mixed
    {
        return $this->_statement->fetchObject($className, $fields);
    }

    /**
     * Reads the whole result set into an array.
     * @return array the result set (each array element represents a row of data).
     * An empty array will be returned if the result contains no row.
     */
    public function readAll(): array
    {
        return $this->_statement->fetchAll();
    }

    /**
     * Advances the reader to the next result when reading the results of a batch of statements.
     * This method is only useful when there are multiple result sets
     * returned by the query. Not all DBMS support this feature.
     * @return boolean Returns true on success or false on failure.
     */
    public function nextResult(): bool
    {
        if (($result = $this->_statement->nextRowset()) !== false)
            $this->_index = -1;
        return $result;
    }

    /**
     * Closes the reader.
     * This frees up the resources allocated for executing this SQL statement.
     * Read attempts after this method call are unpredictable.
     */
    public function close(): void
    {
        $this->_statement->closeCursor();
        $this->_closed = true;
    }

    /**
     * whether the reader is closed or not.
     * @return boolean whether the reader is closed or not.
     */
    public function getIsClosed(): bool
    {
        return $this->_closed;
    }

    /**
     * Returns the number of rows in the result set.
     * Note, most DBMS may not give a meaningful count.
     * In this case, use "SELECT COUNT(*) FROM tableName" to obtain the number of rows.
     * @return integer number of rows contained in the result.
     */
    public function getRowCount(): int
    {
        return $this->_statement->rowCount();
    }

    public function count(): int
    {
        return $this->getRowCount();
    }

    /**
     * Returns the number of columns in the result set.
     * Note, even there's no row in the reader, this still gives correct column number.
     * @return integer the number of columns in the result set.
     */
    public function getColumnCount(): int
    {
        return $this->_statement->columnCount();
    }

    public function rewind(): void
    {
        if ($this->_index < 0) {
            $this->_row = $this->_statement->fetch();
            $this->_index = 0;
        } else {
            throw new DbException('CDbDataReader cannot rewind. It is a forward-only reader.');
        }
    }

    public function key(): int
    {
        return $this->_index;
    }

    public function current(): mixed
    {
        return $this->_row;
    }

    public function next(): void
    {
        $this->_row = $this->_statement->fetch();
        $this->_index++;
    }

    public function valid(): bool
    {
        return $this->_row !== false;
    }
}
