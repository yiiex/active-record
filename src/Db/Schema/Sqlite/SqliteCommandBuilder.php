<?php
/**
 * CSqliteCommandBuilder class file.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link https://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace Yii1x\ActiveRecord\Db\Schema\Sqlite;

use Yii1x\ActiveRecord\Db\DbCommand;
use Yii1x\ActiveRecord\Db\Schema\DbCommandBuilder;
use Yii1x\ActiveRecord\Db\Schema\DbTableSchema;

/**
 * CSqliteCommandBuilder provides basic methods to create query commands for SQLite tables.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.db.schema.sqlite
 * @since 1.0
 */
class SqliteCommandBuilder extends DbCommandBuilder
{
    /**
     * Generates the expression for selecting rows with specified composite key values.
     * This method is overridden because SQLite does not support the default
     * IN expression with composite columns.
     * @param DbTableSchema $table the table schema
     * @param array $values list of primary key values to be selected within
     * @param string $prefix column prefix (ended with dot)
     * @return string the expression for selection
     */
    protected function createCompositeInCondition($table, $values, $prefix)
    {
        $keyNames = array();
        foreach (array_keys($values[0]) as $name)
            $keyNames[] = $prefix . $table->columns[$name]->rawName;
        $vs = array();
        foreach ($values as $value)
            $vs[] = implode("||','||", $value);
        return implode("||','||", $keyNames) . ' IN (' . implode(', ', $vs) . ')';
    }

    /**
     * Creates a multiple INSERT command.
     * This method could be used to achieve better performance during insertion of the large
     * amount of data into the database tables.
     * Note that SQLite does not keep original order of the inserted rows.
     * @param mixed $table the table schema ({@link DbTableSchema}) or the table name (string).
     * @param array[] $data list data to be inserted, each value should be an array in format (column name=>column value).
     * If a key is not a valid column name, the corresponding value will be ignored.
     * @return DbCommand multiple insert command
     * @since 1.1.14
     */
    public function createMultipleInsertCommand($table, array $data)
    {
        $templates = array(
            'main' => 'INSERT INTO {{tableName}} ({{columnInsertNames}}) {{rowInsertValues}}',
            'columnInsertValue' => '{{value}} AS {{column}}',
            'columnInsertValueGlue' => ', ',
            'rowInsertValue' => 'SELECT {{columnInsertValues}}',
            'rowInsertValueGlue' => ' UNION ',
            'columnInsertNameGlue' => ', ',
        );
        return $this->composeMultipleInsertCommand($table, $data, $templates);
    }

    /**
     * Alters the SQL to apply UNION.
     *
     * Overrides parent method because SQLite does not support parenthesized SELECT
     * statements directly in UNION clauses (syntax error).
     *
     * Users must wrap per-branch queries in subqueries instead:
     *   ->union('SELECT * FROM (SELECT id FROM posts LIMIT 5)')
     *
     * @param string $sql SQL query string without UNION clause
     * @param string|array $union UNION clause(s) to append
     * @return string SQL with UNION clause (no parentheses)
     */
    public function applyUnion(string $sql, string|array $union): string
    {
        if ($union != '') {
            return $sql . "\nUNION " . (is_array($union) ? implode("\nUNION ", $union) : $union);
        }
        return $sql;
    }
}
