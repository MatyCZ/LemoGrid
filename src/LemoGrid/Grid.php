<?php

namespace LemoGrid;

use ArrayAccess;
use ArrayIterator;
use LemoGrid\Adapter\AbstractAdapter;
use LemoGrid\Adapter\AdapterInterface;
use LemoGrid\Column\Button;
use LemoGrid\Column\Buttons;
use LemoGrid\Column\ColumnInterface;
use LemoGrid\Column\ColumnPrepareAwareInterface;
use LemoGrid\Platform\PlatformInterface;
use Traversable;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Json;
use Zend\Session\SessionManager;
use Zend\Session\Container as SessionContainer;
use Zend\Stdlib\PriorityQueue;

class Grid implements
    GridInterface,
    EventManagerAwareInterface
{
    /**
     * Default grid namespace
     */
    const NAMESPACE_DEFAULT = 'grid';

    /**
     * Adapter
     *
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @var array
     */
    protected $byName = array();

    /**
     * @var array
     */
    protected $columns = array();

    /**
     * @var SessionContainer
     */
    protected $container;

    /**
     * @var EventManagerInterface
     */
    protected $eventManager;

    /**
     * @var Factory
     */
    protected $factory;

    /**
     * @var PriorityQueue
     */
    protected $iterator;

    /**
     * Is the grid prepared?
     *
     * @var bool
     */
    protected $isPrepared = false;

    /**
     * Grid name
     *
     * @var string
     */
    protected $name = 'grid';

    /**
     * Instance namespace
     *
     * @var string
     */
    protected $namespace;

    /**
     * Platform
     *
     * @var PlatformInterface
     */
    protected $platform;

    /**
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * Constructor
     *
     * @param  null|string            $name
     * @param  null|AdapterInterface  $adapter
     * @param  null|PlatformInterface $platform
     * @return Grid
     */
    public function __construct($name = null, AdapterInterface $adapter = null, $platform = null)
    {
        $this->iterator = new PriorityQueue();

        if (null !== $name) {
            $this->setName($name);
        }

        if (null !== $adapter) {
            $this->setAdapter($adapter);
        }

        if (null !== $platform) {
            $this->setPlatform($platform);
        }
    }

    /**
     * This function is automatically called when creating grid with factory. It
     * allows to perform various operations (add columns...)
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * Add a column
     *
     * $flags could contain metadata such as the alias under which to register
     * the column, order in which to prioritize it, etc.
     *
     * @param  array|Traversable|ColumnInterface $column
     * @param  array                             $flags
     * @throws Exception\InvalidArgumentException
     * @return Grid
     */
    public function add($column, array $flags = array())
    {
        if (is_array($column)
        || ($column instanceof Traversable && !$column instanceof ColumnInterface)
        ) {
            $factory = $this->getGridFactory();
            $column = $factory->createColumn($column);
        }

        if (!$column instanceof ColumnInterface) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s requires that $column be an object implementing %s; received "%s"',
                __METHOD__,
            __NAMESPACE__ . '\ColumnInterface',
                (is_object($column) ? get_class($column) : gettype($column))
            ));
        }

        $name = $column->getName();
        if ((null === $name || '' === $name)
        && (!array_key_exists('name', $flags) || $flags['name'] === '')
        ) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: column provided is not named, and no name provided in flags',
                __METHOD__
            ));
        }

        if (array_key_exists('name', $flags) && $flags['name'] !== '') {
            $name = $flags['name'];

            // Rename the column or fieldset to the specified alias
            $column->setName($name);
        }
        $order = 0;
        if (array_key_exists('priority', $flags)) {
            $order = $flags['priority'];
        }

        $this->iterator->insert($column, $order);
        $this->byName[$name] = $column;
        $this->columns[$name] = $column;

        return $this;
    }

    /**
     * Does the grid have a column by the given name?
     *
     * @param  string $column
     * @return bool
     */
    public function has($column)
    {
        return array_key_exists($column, $this->byName);
    }

    /**
     * Retrieve a named column
     *
     * @param  string $column
     * @return ColumnInterface
     */
    public function get($column)
    {
        if (!$this->has($column)) {
            return null;
        }

        return $this->byName[$column];
    }

    /**
     * Remove a named column
     *
     * @param  string $column
     * @return Grid
     */
    public function remove($column)
    {
        if(is_array($this->byName) && array_key_exists($column, $this->byName)) {
            $this->iterator->remove($this->byName[$column]);
            unset($this->byName[$column]);
        }

        if(is_array($this->columns) && array_key_exists($column, $this->columns)) {
            unset($this->columns[$column]);
        }

        return $this;
    }

    /**
     * Set columns
     *
     * @return Grid
     */
    public function setColumns(array $columns)
    {
        $this->clear();

        foreach ($columns as $column) {
            $this->add($column);
        }

        return $this;
    }

    /**
     * Retrieve all attached columns
     *
     * Storage is an implementation detail of the concrete class.
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Clear all attached columns
     *
     * @return Grid
     */
    public function clear()
    {
        $this->byName = array();
        $this->columns = array();
        $this->iterator = new PriorityQueue();

        return $this;
    }

    /**
     * Countable: return count of attached columns
     *
     * @return int
     */
    public function count()
    {
        return $this->iterator->count();
    }

    /**
     * IteratorAggregate: return internal iterator
     *
     * @return PriorityQueue
     */
    public function getIterator()
    {
        return $this->iterator;
    }

    /**
     * Set/change the priority of a column
     *
     * @param  string $column
     * @param  int    $priority
     * @return Grid
     */
    public function setPriority($column, $priority)
    {
        $column = $this->get($column);
        $this->remove($column);
        $this->add($column, array('priority' => $priority));

        return $this;
    }

    /**
     * Sets the grid adapter
     *
     * @param  AdapterInterface $adapter
     * @return Grid
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * Returns the grid adapter
     *
     * @return AdapterInterface|null
     */
    public function getAdapter()
    {
        $this->adapter->setGrid($this);

        return $this->adapter;
    }

    /**
     * Get session container for grid
     *
     * @return SessionContainer
     */
    public function getContainer()
    {
        if ($this->container instanceof SessionContainer) {
            return $this->container;
        }

        $this->container = new SessionContainer('Grid', $this->getSessionManager());

        return $this->container;
    }

    /**
     * Compose a grid factory to use when calling add() with a non-element
     *
     * @param  Factory $factory
     * @return Grid
     */
    public function setGridFactory(Factory $factory)
    {
        $this->factory = $factory;

        return $this;
    }

    /**
     * Retrieve composed grid factory
     *
     * Lazy-loads one if none present.
     *
     * @return Factory
     */
    public function getGridFactory()
    {
        if (null === $this->factory) {
            $this->setGridFactory(new Factory());
        }

        return $this->factory;
    }

    /**
     * @param  EventManagerInterface $eventManager
     * @return Grid
     */
    public function setEventManager(EventManagerInterface $eventManager)
    {
        $this->eventManager = $eventManager;

        return $this;
    }

    /**
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        return $this->eventManager;
    }

    /**
     * Set name
     *
     * @param  string $name
     * @return Grid
     */
    public function setName($name)
    {
        $this->name = (string) $name;
        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Change the grid namespace for params
     *
     * @param  string $namespace
     * @return Grid
     */
    public function setNamespace($namespace = self::NAMESPACE_DEFAULT)
    {
        $this->namespace = (string) $namespace;
        return $this;
    }

    /**
     * Get the grid namespace for params
     *
     * @return string
     */
    public function getNamespace()
    {
        if (null === $this->namespace) {
            $this->namespace = $this->getName();
        }

        return $this->namespace;
    }

    /**
     * Set param
     *
     * @param  string $key
     * @param  mixed  $value
     * @return Grid
     */
    public function setParam($key, $value)
    {
        $container = $this->getContainer();
        $namespace = $this->getNamespace();

        if (!isset($container[$namespace]) || !($container[$namespace] instanceof Traversable)) {
            $container[$namespace] = new ArrayIterator();
        }

        // Modifi param in Platform
        $value = $this->getPlatform()->modifiParam($key, $value);

        if (false !== $value) {
            $container[$namespace]->offsetSet($key, $value);
        }

        return $this;
    }

    /**
     * Get param
     *
     * @param  string $key
     * @return mixed
     */
    public function getParam($key)
    {
        $container = $this->getContainer();
        $namespace = $this->getNamespace();

        if (isset($container[$namespace]) && isset($container[$namespace][$key])) {
            return $container[$namespace]->offsetGet($key);
        }

        if ('filters' == $key) {
            return array();
        }

        return null;
    }

    /**
     * Exist param with given name?
     *
     * @param  string $name
     * @return bool
     */
    public function hasParam($name)
    {
        $container = $this->getContainer();
        $namespace = $this->getNamespace();

        if (isset($container[$namespace])) {
            return $container[$namespace]->offsetExists($name);
        }

        return false;
    }

    /**
     * Set params
     *
     * @param  array|ArrayAccess|Traversable $params
     * @throws Exception\InvalidArgumentException
     * @return Grid
     */
    public function setParams($params)
    {
        if (!is_array($params) && !$params instanceof Traversable) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable argument; received "%s"',
                __METHOD__,
                (is_object($params) ? get_class($params) : gettype($params))
            ));
        }

        if (isset($params['sidx']) && empty($params['sidx'])) {
            unset($params['sidx'], $params['sord']);
        }

        foreach ($params as $key => $value) {
            $this->setParam($key, $value);
        }

        return $this;
    }

    /**
     * Get params from a specific namespace
     *
     * @return array
     */
    public function getParams()
    {
        $container = $this->getContainer();
        $namespace = $this->getNamespace();

        if ($this->hasParams()) {
            return $container[$namespace];
        }

        return array();
    }

    /**
     * Whether a specific namespace has params
     *
     * @return bool
     */
    public function hasParams()
    {
        $container = $this->getContainer();
        $namespace = $this->getNamespace();

        return isset($container[$namespace]);
    }

    /**
     * Set the platform
     *
     * @param  PlatformInterface $platform
     * @return Grid
     */
    public function setPlatform(PlatformInterface $platform)
    {
        $this->platform = $platform;

        return $this;
    }

    /**
     * Get the platform
     *
     * @return PlatformInterface
     */
    public function getPlatform()
    {
        $this->platform->setGrid($this);

        return $this->platform;
    }

    /**
     * Set the session manager
     *
     * @param  SessionManager $manager
     * @return Grid
     */
    public function setSessionManager(SessionManager $manager)
    {
        $this->sessionManager = $manager;
        return $this;
    }

    /**
     * Retrieve the session manager
     *
     * If none composed, lazy-loads a SessionManager instance
     *
     * @return SessionManager
     */
    public function getSessionManager()
    {
        if (!$this->sessionManager instanceof SessionManager) {
            $this->setSessionManager(SessionContainer::getDefaultManager());
        }

        return $this->sessionManager;
    }

    /**
     * Make a deep clone of a grid
     *
     * @return void
     */
    public function __clone()
    {
        $items = $this->getIterator()->toArray(PriorityQueue::EXTR_BOTH);

        $this->byName    = array();
        $this->columns  = array();
        $this->container = null;
        $this->iterator  = new PriorityQueue();
        $this->namespace  = self::NAMESPACE_DEFAULT;
        $this->sessionManager = null;

        foreach ($items as $item) {
            $column = clone $item['data'];
            $name = $column->getName();

            $this->iterator->insert($column, $item['priority']);
            $this->byName[$name] = $column;

            if ($column instanceof ColumnInterface) {
                $this->columns[$name] = $column;
            }
        }
    }

    /**
     * Check if is prepared
     *
     * @return bool
     */
    public function isPrepared()
    {
        return $this->isPrepared;
    }

    /**
     * Ensures state is ready for use
     * Prepares grid and any columns that require  preparation.
     *
     * @throws Exception\InvalidArgumentException
     * @return Grid
     */
    public function prepare()
    {
        if ($this->isPrepared) {
            return $this;
        }

        $this->init();

        $name = $this->getName();
        if ((null === $name || '' === $name)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: grid is not named',
                __METHOD__
            ));
        }

        // If the user wants to, elements names can be wrapped by the form's name
        foreach ($this->getColumns() as $column) {
            if ($column instanceof ColumnPrepareAwareInterface) {
                $column->prepareColumn($this);
            }
        }

        $this->isPrepared = true;

        return $this;
    }

    /**
     * Render data form given adapter
     *
     * @return void
     */
    public function renderData()
    {
        if (!$this->getAdapter() instanceof AbstractAdapter) {
            throw new Exception\InvalidArgumentException('No Adapter instance given');
        }

        $resultSet = $this->getAdapter()->getResultSet();
        $rows = $resultSet->getArrayCopy();
        $rowsCount = count($rows);

        $json = array(
            'page'    => $this->getPlatform()->getNumberOfCurrentPage(),
            'total'   => $this->getAdapter()->getNumberOfPages(),
            'records' => $this->getAdapter()->getCountOfItemsTotal(),
            'rows'    => array(),
        );

        for ($indexRow = 0; $indexRow < $rowsCount; $indexRow++) {
            $json['rows'][] = array(
                'id'   => $indexRow +1,
                'cell' => $rows[$indexRow]
            );
        }

        $userData = $resultSet->getUserData();
        if (!empty($userData)) {
            $json['userdata'] = $userData;
        }

        ob_clean();
        echo Json\Encoder::encode($json);
        exit;
    }
}
