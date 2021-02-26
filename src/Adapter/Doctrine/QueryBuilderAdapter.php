<?php

namespace Lemo\Grid\Adapter\Doctrine;

use DateTime;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder AS DoctrineQueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Generator;
use Lemo\Grid\Adapter\AbstractAdapter;
use Lemo\Grid\Column\ColumnInterface;
use Lemo\Grid\Column\Concat as ColumnConcat;
use Lemo\Grid\Event\AdapterEvent;
use Lemo\Grid\Exception;
use Lemo\Grid\GridInterface;
use Lemo\Grid\Platform\AbstractPlatform;
use Lemo\Grid\Platform\JqGridPlatform as JqGridPlatform;
use Lemo\Grid\Platform\JqGridPlatformOptions;
use Throwable;

class QueryBuilderAdapter extends AbstractAdapter
{
    /**
     * @var array
     */
    protected $aliases = [];

    /**
     * @var DoctrineQueryBuilder
     */
    protected $queryBuilder = null;

    /**
     * Prepare adapter
     *
     * @return $this
     * @throws Throwable
     */
    public function prepareAdapter()
    {
        if ($this->isPrepared) {
            return $this;
        }

        if (!$this->getGrid() instanceof GridInterface) {
            throw new Exception\UnexpectedValueException("No Grid instance given");
        }

        if (!$this->getQueryBuilder() instanceof DoctrineQueryBuilder) {
            throw new Exception\UnexpectedValueException("No QueryBuilder instance given");
        }

        if (empty($this->getQueryBuilder()->getDQLPart('select'))) {
            return $this;
        }

        // Find join aliases in DQL
        $this->findAliases();

        // Modify DQL
        $this->applyFilters();
        $this->applyPagination();
        $this->applySortings();

        $this->isPrepared = true;

        return $this;
    }

    /**
     * @throws Exception\UnexpectedValueException
     * @return QueryBuilderAdapter
     */
    public function fetchData()
    {
        $columns = $this->getGrid()->getIterator()->toArray();
        $paginator = new Paginator($this->getQueryBuilder()->getQuery()->setHydrationMode(Query::HYDRATE_ARRAY));
        $rows = $paginator->getIterator();
        $rowsCount = $paginator->getIterator()->count();

        // Update count of items
        $this->countItems = $rowsCount;
        $this->countItemsTotal = $paginator->count();

        /** @var JqGridPlatformOptions $platformOptions */
        $platformOptions = $this->getGrid()->getPlatform()->getOptions();
        $rowIdColumn = $platformOptions->getRowIdColumn();

        $data = [];
        for ($indexRow = 0; $indexRow < $rowsCount; $indexRow++) {
            $item = $rows[$indexRow];

            if (isset($item[0])) {
                $item = $this->mergeSubqueryItem($item);
            }

            if (null !== $rowIdColumn && !empty($item[$rowIdColumn])) {
                $data[$indexRow]['rowId'] = $item[$rowIdColumn];
            }

            foreach ($columns as $indexCol => $column) {
                $colIdentifier = $column->getIdentifier();
                $colName = $column->getName();
                $data[$indexRow][$colName] = null;

                // Can we render value?
                if (true === $column->isValid($this, $item)) {

                    // Nacteme si data radku
                    $value = $this->findValue($colIdentifier, $item);

                    // COLUMN - DateTime
                    if ($value instanceof DateTime) {
                        $value = $value->format('Y-m-d H:i:s');
                    }

                    $column->setValue($value);
                    $value = $column->renderValue($this, $item);

                    // Projdeme data a nahradime data ve formatu %xxx%
                    if (null !== $value && preg_match_all('/%(_?[a-zA-Z0-9\._-]+)%/', $value, $matches)) {
                        foreach ($matches[0] as $key => $match) {
                            if ('%_index%' == $matches[0][$key]) {
                                $value = str_replace($matches[0][$key], $indexRow, $value);
                            } else {
                                $value = str_replace($matches[0][$key], $this->findValue($matches[1][$key], $item), $value);
                            }
                        }
                    }

                    $data[$indexRow][$colName] = $value;
                    $column->setValue($value);
                }
            }

        }

        $this->getGrid()->getPlatform()->getResultSet()->setData($data);

        // Fetch summary data
        $this->fetchDataSummary();

        $event = new AdapterEvent();
        $event->setAdapter($this);
        $event->setGrid($this->getGrid());
        $event->setResultSet($this->getGrid()->getPlatform()->getResultSet());

        $this->getGrid()->getEventManager()->trigger(AdapterEvent::EVENT_FETCH_DATA, $this, $event);

        $this->getGrid()->setAdapter($event->getAdapter());
        $this->getGrid()->getPlatform()->setResultSet($event->getResultSet());

        return $this;
    }

    /**
     * @param  array $selectedRows
     * @return Generator
     */
    public function getExportGenerator(array $selectedRows = []): Generator
    {
        try {

            /** @var JqGridPlatformOptions $platformOptions */
            $platformOptions = $this->getGrid()->getPlatform()->getOptions();
            $rowIdColumn = $platformOptions->getRowIdColumn();
            if (empty($rowIdColumn)) {
                throw new \Exception('Grid identifier column is not set');
            }

            // fecth all ids or uuids
            if (empty($selectedRows)) {
                $qb = clone $this->getQueryBuilder();
                $qb->select('ite.' . $rowIdColumn);
                $qb->distinct(true);
                $res = $qb->getQuery()->getScalarResult();
                $selectedRows = array_column($res, 'id');
                unset($qb, $res);
            }

            // return count of items
            yield count($selectedRows);

            $columns = $this->getGrid()->getIterator()->toArray();

            foreach ($selectedRows as $id) {

                $qb = clone $this->getQueryBuilder();
                $qb->where($qb->expr()->eq('ite.' . $rowIdColumn, $id));
                $item = $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_ARRAY);
                unset($qb);

                if (null === $item) {
                    continue;
                }

                if (isset($item[0])) {
                    $item = $this->mergeSubqueryItem($item);
                }

                $result = [];
                foreach ($columns as $indexCol => $column) {

                    $colIdentifier = $column->getIdentifier();
                    $colName = $column->getName();
                    $result[$colName] = null;

                    if (true === $column->isValid($this, $item)) {

                        $value = $this->findValue($colIdentifier, $item);

                        if ($value instanceof DateTime) {
                            $value = $value->format('Y-m-d H:i:s');
                        }

                        $column->setValue($value);
                        $value = $column->renderValue($this, $item);

                        $result[$colName] = $value;
                    }
                }

                unset($item);

                yield $result;
            }

        } catch (Throwable $throwable) {
            yield $throwable;
        }
    }

    /**
     * @return QueryBuilderAdapter
     */
    protected function fetchDataSummary()
    {
        if ($this->getGrid()->getPlatform() instanceof JqGridPlatform && true === $this->getGrid()->getPlatform()->getOptions()->getUserDataOnFooter()) {
            $queryBuilder = $this->getQueryBuilder();
            $queryBuilder->resetDQLPart('select');
            $queryBuilder->resetDQLPart('orderBy');
            $queryBuilder->setFirstResult(null);
            $queryBuilder->setMaxResults(null);

            // Add group by
            $rootAliases = $queryBuilder->getRootAliases();
            $rootEntities = $queryBuilder->getRootEntities();

            $identifiers = $this->getQueryBuilder()
                ->getEntityManager()
                ->getClassMetadata($rootEntities[0])
                ->getIdentifierFieldNames();

            foreach ($identifiers as $identifier) {
                $queryBuilder->addGroupBy($rootAliases[0] . '.' . $identifier);
            }

            $summary = [];
            $countOfSummaryColumn = 0;
            foreach ($this->getGrid()->getColumns() as $indexCol => $column) {
                $columnQuery = clone $queryBuilder;

                // Sloupec je skryty, takze ho preskocime
                if (true === $column->getAttributes()->getIsHidden()) {
                    continue;
                }

                if (null !== $column->getAttributes()->getSummaryType()) {
                    $summaryType = $column->getAttributes()->getSummaryType();

                    $columnQuery->addSelect($column->getIdentifier());

                    $countOfSummaryColumn++;

                    $values = array_map('current', $columnQuery->getQuery()->getScalarResult());

                    switch ($summaryType) {
                        case 'avg':
                            $summary[$column->getName()] = array_sum($values) / count($values);
                            break;
                        case 'max':
                            $summary[$column->getName()] = max($values);
                            break;
                        case 'min':
                            $summary[$column->getName()] = min($values);
                            break;
                        case 'sum':
                            $summary[$column->getName()] = array_sum($values);
                            break;
                    }
                }
            }

            if (!empty($summary)) {
                $this->getGrid()->getPlatform()->getResultSet()->setDataUser($summary);
            }
        }

        return $this;
    }

    /**
     * Apply filters to the QueryBuilder
     *
     * @throws \Exception
     * @return QueryBuilderAdapter
     */
    protected function applyFilters()
    {
        $columns = $this->getGrid()->getIterator()->toArray();
        $filter = $this->getGrid()->getParam('filters');

        // WHERE
        if (!empty($filter['rules'])) {

            $whereCol = [];
            foreach($columns as $indexCol => $col) {
                if (true === $col->getAttributes()->getIsSearchable() && true !== $col->getAttributes()->getIsHidden()) {

                    // Jsou definovane filtry pro sloupec
                    if(!empty($filter['rules'][$col->getName()])) {

                        $whereColSub = [];
                        foreach ($filter['rules'][$col->getName()] as $filterDefinition) {
                            if (in_array($filterDefinition['operator'], ['~', '!~'])) {

                                // Odstranime duplicity a prazdne hodnoty
                                $filterWords = [];
                                foreach (explode(' ', $filterDefinition['value']) as $word) {
                                    if (in_array($word, $filterWords)) {
                                        continue;
                                    }

                                    if ('' == $word) {
                                        continue;
                                    }

                                    $filterWords[] = $word;
                                }

                                $wheres = [];
                                if ($col instanceof ColumnConcat) {
                                    $concat = $this->buildConcat($col->getOptions()->getIdentifiers());

                                    foreach ($filterWords as $filterWord) {
                                        $wheres[] = $this->buildWhereFromFilter($col, $concat, [
                                            'operator' => $filterDefinition['operator'],
                                            'value'    => $filterWord,
                                        ]);
                                    }

                                    // Urcime pomoci jakeho operatoru mame skladat jednotlive vyrazi hledani sloupce
                                    if ('and' == $col->getAttributes()->getSearchGroupOperator()) {
                                        $exp = new Expr\Andx();
                                        $exp->addMultiple($wheres);
                                    } else {
                                        $exp = ('~' === $filterDefinition['operator']) ? new Expr\Orx() : new Expr\Andx();
                                        $exp->addMultiple($wheres);
                                    }

                                    $whereColSub[] = $exp;
                                } else {

                                    foreach ($filterWords as $filterWord) {
                                        $wheres[] = $this->buildWhereFromFilter($col, $col->getIdentifier(), [
                                            'operator' => $filterDefinition['operator'],
                                            'value'    => $filterWord,
                                        ]);
                                    }

                                    if ('and' == $col->getAttributes()->getSearchGroupOperator()) {
                                        $exp = new Expr\Andx();
                                        $exp->addMultiple($wheres);
                                    } else {
                                        $exp = ('~' === $filterDefinition['operator']) ? new Expr\Orx() : new Expr\Andx();
                                        $exp->addMultiple($wheres);
                                    }

                                    $whereColSub[] = $exp;
                                }
                            } else {

                                // Sestavime filtr pro jednu podminku sloupce
                                $exprFilterColSub = [];
                                if($col instanceof ColumnConcat) {
                                    foreach ($col->getOptions()->getIdentifiers() as $identifier) {
                                        $exprFilterColSub[] = $this->buildWhereFromFilter($col, $identifier, $filterDefinition);
                                    }
                                } else {
                                    $exprFilterColSub[] = $this->buildWhereFromFilter($col, $col->getIdentifier(), $filterDefinition);
                                }

                                // Sloucime podminky sloupce pomoci OR (z duvodu Concat sloupce)
                                $exprColSub = new Expr\Orx();
                                $exprColSub->addMultiple($exprFilterColSub);

                                $whereColSub[] = $exprColSub;
                            }
                        }

                        // Urcime pomoci jako operatoru mame sloupcit jednotlive podminky
                        if ('and' == $filter['operator']) {
                            $exprCol = new Expr\Andx();
                            $exprCol->addMultiple($whereColSub);
                        } else {
                            $exprCol = new Expr\Orx();
                            $exprCol->addMultiple($whereColSub);
                        }

                        $whereCol[] = $exprCol;
                    }
                }
            }

            // Slouceni EXPR jednotlivych sloupcu do jednoho WHERE
            if ('and' == $filter['operator']) {
                $exprCols = new Expr\Andx();
                $exprCols->addMultiple($whereCol);
            } else {
                $exprCols = new Expr\Orx();
                $exprCols->addMultiple($whereCol);
            }

            // Pridame k vychozimu WHERE i WHERE z filtrace sloupcu
            $this->getQueryBuilder()->andWhere($exprCols);
        }

        return $this;
    }

    /**
     * Apply pagination to the QueryBuilder
     *
     * @return QueryBuilderAdapter
     */
    protected function applyPagination()
    {
        $numberCurrentPage = $this->getGrid()->getPlatform()->getNumberOfCurrentPage();
        $numberVisibleRows = $this->getGrid()->getPlatform()->getNumberOfVisibleRows();

        // Calculate offset
        if ($numberVisibleRows > 0) {
            $offset = $numberVisibleRows * $numberCurrentPage - $numberVisibleRows;
            if($offset < 0) {
                $offset = 0;
            }

            $this->getQueryBuilder()->setFirstResult($offset);
            $this->getQueryBuilder()->setMaxResults($numberVisibleRows);
        }

        return $this;
    }

    /**
     * Apply sorting to the QueryBuilder
     *
     * @return QueryBuilderAdapter
     */
    protected function applySortings()
    {
        $sort = $this->getGrid()->getPlatform()->getSort();

        // Store default order to variable and reset orderBy
        $orderBy = $this->getQueryBuilder()->getDQLPart('orderBy');
        $this->getQueryBuilder()->resetDQLPart('orderBy');

        // ORDER
        if (!empty($sort)) {
            foreach ($sort as $sortColumn => $sortDirect) {
                if ($this->getGrid()->has($sortColumn)) {
                    if (false !== $this->getGrid()->get($sortColumn)->getAttributes()->getIsSortable() && true !== $this->getGrid()->get($sortColumn)->getAttributes()->getIsHidden()) {
                        if ($this->getGrid()->get($sortColumn) instanceof ColumnConcat) {
                            foreach($this->getGrid()->get($sortColumn)->getOptions()->getIdentifiers() as $identifier){
                                if (count($this->getQueryBuilder()->getDQLPart('orderBy')) == 0) {
                                    $method = 'orderBy';
                                } else {
                                    $method = 'addOrderBy';
                                    $sortDirect = 'asc';
                                }

                                $this->getQueryBuilder()->{$method}($identifier, $sortDirect);
                            }
                        } else {
                            if (count($this->getQueryBuilder()->getDQLPart('orderBy')) == 0) {
                                $method = 'orderBy';
                            } else {
                                $method = 'addOrderBy';
                            }

                            $this->getQueryBuilder()->{$method}($this->getGrid()->get($sortColumn)->getIdentifier(), $sortDirect);
                        }
                    }
                }
            }
        }

        // Add default order from variable
        if (!empty($orderBy)) {
            foreach ($orderBy as $order) {
                $this->getQueryBuilder()->addOrderBy($order);
            }
        }

        return $this;
    }

    /**
     * Find aliases used in Query
     *
     * @return QueryBuilderAdapter
     */
    protected function findAliases()
    {
        $from = $this->getQueryBuilder()->getDqlPart('from');
        $join = $this->getQueryBuilder()->getDqlPart('join');

        if (empty($from)) {
            return $this;
        }

        $root = $from[0]->getAlias();

        $this->aliases = [];

        if(!empty($join[$root])) {
            foreach($join[$root] as $j) {
                preg_match('/JOIN (([a-zA-Z0-9_-]+)\.([a-zA-Z0-9\._-]+))( as| AS)?( )?([a-zA-Z0-9_]+)?/', $j->__toString(), $match);

                $this->aliases[$match[6]] = $match[2] . '.' . $match[3];
            }
        }

        return $this;
    }

    /**
     * Find value for column
     *
     * @param  string $identifier
     * @param  array  $item
     * @param  int    $depth
     * @return null|string
     */
    public function findValue($identifier, array $item, $depth = 0)
    {
        if (0 == $depth) {
            $identifier = $this->buildIdententifier($identifier);
        }

        $identifierNext = $identifier;
        if (false !== strpos($identifier, '.')) {
            $identifierNext = substr($identifier, strpos($identifier, '.') + 1);
        }

        $parts = explode('.', $identifierNext);

        if (isset($item[$parts[0]]) && count($parts) > 1) {
            return $this->findValue($identifierNext, $item[$parts[0]], $depth+1);
        }

        if (isset($item[$identifierNext])) {
            return $item[$identifierNext];
        } else {
            if (isset($item[0])) {

                $return = [];
                foreach ($item as $it) {
                    if (isset($it[$identifierNext])) {
                        $return[] = $it[$identifierNext];
                    }
                }

                return $return;
            }
        }

        return null;
    }

    /**
     * Sestavi identifier
     *
     * @param  string $identifier
     * @return string
     */
    protected function buildIdententifier($identifier)
    {
        $identifier = str_replace('_', '.', $identifier);

        // Determinate column name and alias name
        $identifierFirst = substr($identifier, 0, strpos($identifier, '.'));

        if (isset($this->aliases[$identifierFirst])) {
            $identifier = str_replace($identifierFirst . '.', $this->aliases[$identifierFirst] . '.', $identifier);

            return $this->buildIdententifier($identifier);
        }

        return $identifier;
    }

    /**
     * Sestavi CONCAT z predanych casti
     *
     * @param  array  $identifiers
     * @return Expr\Func|string
     */
    protected function buildConcat(array $identifiers)
    {
        if (count($identifiers) > 1) {
            $parts = [];
            foreach ($identifiers as $identifier) {
                $parts[] = "CASE WHEN  (" . $identifier . " IS NULL) THEN '' ELSE " . $identifier . " END";
            }

            return new Expr\Func('CONCAT', $parts);
        }

        return reset($identifiers);
    }


    /**
     * @param  ColumnInterface $column
     * @param  string          $identifier
     * @param  array           $filterDefinition
     * @return Expr\Comparison
     * @throws Exception\InvalidArgumentException
     */
    protected function buildWhereFromFilter(ColumnInterface $column, $identifier, array $filterDefinition)
    {
        $expr = new Expr();

        $value    = addcslashes($filterDefinition['value'], '%_');
        $operator = $filterDefinition['operator'];

        // Pravedeme neuplny string na DbDate
        if ('date' == $column->getAttributes()->getFormat()) {
            $value = $this->convertLocaleDateToDbDate($value);
        }

        $param = uniqid('param');

        switch ($operator) {
            case AbstractPlatform::OPERATOR_EQUAL:
                $where = $expr->eq($identifier, ':' . $param);
                $this->getQueryBuilder()->setParameter($param, $value);
                break;
            case AbstractPlatform::OPERATOR_NOT_EQUAL:
                $where = $expr->orX(
                    $expr->neq($identifier, ':' . $param),
                    $expr->isNull($identifier)
                );
                $this->getQueryBuilder()->setParameter($param, $value);
                break;
            case AbstractPlatform::OPERATOR_LESS:
                $where = $expr->lt($identifier, ':' . $param);
                $this->getQueryBuilder()->setParameter($param, $value);
                break;
            case AbstractPlatform::OPERATOR_LESS_OR_EQUAL:
                $where = $expr->lte($identifier, ':' . $param);
                $this->getQueryBuilder()->setParameter($param, $value);
                break;
            case AbstractPlatform::OPERATOR_GREATER:
                $where = $expr->gt($identifier, ':' . $param);
                $this->getQueryBuilder()->setParameter($param, $value);
                break;
            case AbstractPlatform::OPERATOR_GREATER_OR_EQUAL:
                $where = $expr->gte($identifier, ':' . $param);
                $this->getQueryBuilder()->setParameter($param, $value);
                break;
            case AbstractPlatform::OPERATOR_BEGINS_WITH:
                $where = $expr->like($identifier, ':' . $param);
                $this->getQueryBuilder()->setParameter($param, $value . '%');
                break;
            case AbstractPlatform::OPERATOR_NOT_BEGINS_WITH:
                $where = $expr->orX(
                    $expr->notLike($identifier, ':' . $param),
                    $expr->isNull($identifier)
                );
                $this->getQueryBuilder()->setParameter($param, $value . '%');
                break;
            case AbstractPlatform::OPERATOR_IN:
                $where = $expr->in($identifier, ':' . $param);
                $this->getQueryBuilder()->setParameter($param, $value);
                break;
            case AbstractPlatform::OPERATOR_NOT_IN:
                $where = $expr->orX(
                    $expr->notIn($identifier, ':' . $param),
                    $expr->isNull($identifier)
                );
                $this->getQueryBuilder()->setParameter($param, $value);
                break;
            case AbstractPlatform::OPERATOR_ENDS_WITH:
                $where = $expr->like($identifier, ':' . $param);
                $this->getQueryBuilder()->setParameter($param, '%' . $value);
                break;
            case AbstractPlatform::OPERATOR_NOT_ENDS_WITH:
                $where = $expr->orX(
                    $expr->notLike($identifier, ':' . $param),
                    $expr->isNull($identifier)
                );
                $this->getQueryBuilder()->setParameter($param, '%' . $value);
                break;
            case AbstractPlatform::OPERATOR_CONTAINS:
                $where = $expr->like($identifier, ':' . $param);
                $this->getQueryBuilder()->setParameter($param, '%' . $value . '%');
                break;
            case AbstractPlatform::OPERATOR_NOT_CONTAINS:
                $where = $expr->orX(
                    $expr->notLike($identifier, ':' . $param),
                    $expr->isNull($identifier)
                );
                $this->getQueryBuilder()->setParameter($param, '%' . $value . '%');
                break;
            default:
                throw new Exception\InvalidArgumentException('Invalid filter operator');
        }

        return $where;
    }

    /**
     * @param  array $item
     * @return array
     */
    protected function mergeSubqueryItem(array $item)
    {
        // Nacteme si samostatne data entity a seznam poli
        $fields = $item;
        $item = $item[0];
        unset($fields[0]);

        // Projdeme vsechna pole, ktera mame odebrat
        foreach ($fields as $name => $value) {
//            if (is_null($value)) {
//
//                // Jedna se o Pole
//                if (is_array($item)) {
//                    if (isset($item[$name])) {
            $item[$name] = $value;
//                    }
//                }
//            }
        }

        return $item;
    }

    /**
     * Set QueryBuilder
     *
     * @param  DoctrineQueryBuilder $queryBuilder
     * @return self
     */
    public function setQueryBuilder(DoctrineQueryBuilder $queryBuilder): self
    {
        $this->queryBuilder = $queryBuilder;

        return $this;
    }

    /**
     * Return QueryBuilder
     *
     * @return DoctrineQueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }
}