<?php

namespace AJUR\FluentPDO\Queries;

use AJUR\FluentPDO\{Exception, Query, Utilities};

/**
 * SELECT query builder
 */
class Select extends Common implements \Countable
{
    
    /**
     * @var mixed
     */
    private $fromTable;

    /**
     * @var mixed
     */
    private $fromAlias;

    /**
     * SelectQuery constructor.
     *
     * @param Query $fluent
     * @param           $from
     * @param bool $includeTableAliasColumns
     */
    public function __construct(Query $fluent, $from, $includeTableAliasColumns = true)
    {
        $clauses = [
            'SELECT' => ', ',
            'FROM' => null,
            'JOIN' => [ $this, 'getClauseJoin' ],
            'WHERE' => [ $this, 'getClauseWhere' ],
            'GROUP BY' => ',',
            'HAVING' => ' AND ',
            'ORDER BY' => ', ',
            'LIMIT' => null,
            'OFFSET' => null,
            "\n--" => "\n--",
        ];
        parent::__construct( $fluent, $clauses );
        
        // initialize statements
        $fromParts = explode( ' ', $from );
        $this->fromTable = reset( $fromParts );
        $this->fromAlias = end( $fromParts );
        
        $this->statements[ 'FROM' ] = $from;
        if ($includeTableAliasColumns === true) {
            $this->statements['SELECT'][] = $this->fromAlias . '.*';
        }
        $this->joins[] = $this->fromAlias;
    }
    
    /**
     * @param mixed $columns
     * @param bool $overrideDefault
     *
     * @return $this
     */
    public function select($columns, bool $overrideDefault = false)
    {
        if ($overrideDefault === true) {
            $this->resetClause( 'SELECT' );
        } elseif ($columns === null) {
            return $this->resetClause( 'SELECT' );
        }
        
        $this->addStatement( 'SELECT', $columns, [] );
        
        return $this;
    }
    
    /**
     * Return table name from FROM clause
     */
    public function getFromTable()
    {
        return $this->fromTable;
    }
    
    /**
     * Return table alias from FROM clause
     */
    public function getFromAlias()
    {
        return $this->fromAlias;
    }
    
    /**
     * Returns a single column
     *
     * @param int $columnNumber
     *
     * @return string
     * @throws Exception
     *
     */
    public function fetchColumn(int $columnNumber = 0)
    {
        if (($s = $this->execute()) !== false) {
            return $s->fetchColumn( $columnNumber );
        }
        
        return $s;
    }
    
    /**
     * Fetch first row or column
     *
     * @param string $column - column name or empty string for the whole row
     * @param int $cursorOrientation
     *
     * @return mixed string, array or false if there is no row
     * @throws Exception
     *
     */
    public function fetch(?string $column = null, int $cursorOrientation = \PDO::FETCH_ORI_NEXT)
    {
        if ($this->result === null) {
            $this->execute();
        }
        
        if ($this->result === false) {
            return false;
        }

        if (is_null($this->currentFetchMode)) {
            $row = $this->result->fetch( \PDO::FETCH_ASSOC, $cursorOrientation );
        } else {
            $row = $this->result->fetch( $this->currentFetchMode, $cursorOrientation );
        }
        
        if ($this->fluent->convertRead === true) {
            $row = Utilities::stringToNumeric( $this->result, $row );
        }
        
        if ($row && $column !== null) {
            if (is_object( $row )) {
                return $row->{$column};
            } else {
                return $row[ $column ];
            }
        }
        
        return $row;
    }
    
    /**
     * Fetch pairs
     *
     * @param $key
     * @param $value
     * @param $object
     *
     * @return array|\PDOStatement
     * @throws Exception
     *
     */
    public function fetchPairs($key, $value, $object = false)
    {
        if (($s = $this->select( "$key, $value", true )->asObject( $object )->execute()) !== false) {
            return $s->fetchAll( \PDO::FETCH_KEY_PAIR );
        }
        
        return $s;
    }
    
    /** Fetch all row
     *
     * @param string $index - specify index column. Allows for data organization by field using 'field[]'
     * @param string $selectOnly - select columns which could be fetched
     *
     * @return array|bool -  fetched rows
     * @throws Exception
     *
     */
    public function fetchAll($index = '', $selectOnly = '')
    {
        $indexAsArray = strpos( $index, '[]' );
        
        if ($indexAsArray !== false) {
            $index = str_replace( '[]', '', $index );
        }
        
        if ($selectOnly) {
            $this->select( $index . ', ' . $selectOnly, true );
        }
        
        if ($index) {
            return $this->buildSelectData( $index, $indexAsArray );
        } else {
            if (($result = $this->execute()) !== false) {
                if ($this->fluent->convertRead === true) {
                    return Utilities::stringToNumeric( $result, $result->fetchAll() );
                } else {
                    return $result->fetchAll();
                }
            }
            
            return false;
        }
    }
    
    /**
     * \Countable interface doesn't break current select query
     *
     * @return int
     * @throws Exception
     *
     */
    #[\ReturnTypeWillChange]
    public function count(): int
    {
        $fluent = clone $this;
        
        return (int)$fluent->select( 'COUNT(*)', true )->fetchColumn();
    }
    
    /**
     * @return \ArrayIterator|\PDOStatement
     * @throws Exception
     *
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        if ($this->fluent->convertRead === true) {
            return new \ArrayIterator( $this->fetchAll() );
        } else {
            return $this->execute();
        }
    }
    
    /**
     * @param $index
     * @param $indexAsArray
     *
     * @return array
     */
    private function buildSelectData($index, $indexAsArray): array
    {
        $data = [];
        
        foreach ($this as $row) {
            if (is_object( $row )) {
                $key = $row->{$index};
            } else {
                $key = $row[ $index ];
            }
            
            if ($indexAsArray) {
                $data[ $key ][] = $row;
            } else {
                $data[ $key ] = $row;
            }
        }
        
        return $data;
    }
}
