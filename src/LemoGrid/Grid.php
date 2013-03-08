<?php

namespace LemoGrid;

use LemoGrid\Adapter\AdapterInterface;
use LemoGrid\Column\ColumnInterface;
use Traversable;
use Zend\Session\SessionManager;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Stdlib\PriorityQueue;

class Grid implements GridInterface
{
    /**
     * Adapter
     *
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @var array
     */
    protected $byName    = array();

    /**
     * @var array
     */
    protected $columns  = array();

    /**
     * @var Factory
     */
    protected $factory;

    /**
     * @var PriorityQueue
     */
    protected $iterator;

    /**
     * Grid name
     *
     * @var string
     */
    protected $name;

    /**
     * @var GridOptions
     */
    protected $options;

    /**
     * Grid metadata and attributes
     *
     * @var array
     */
    protected $_attribs = array();

    /**
     * Name of grid
     *
     * @var string
     */
    protected $_name = null;

    /**
     * Order in which to display and iterate columns
     *
     * @var array
     */
    protected $_order = array();

    /**
     * Whether internal order has been updated or not
     *
     * @var bool
     */
    protected $_orderUpdated = false;

    /**
     * Parameter container responsible for query parameters
     *
     * @var \Zend\Stdlib\Parameters
     */
    protected $_queryParams = null;

    /**
     * Request object
     *
     * @var \Zend\Http\PhpEnvironment\Request
     */
    protected $_request = null;

    /**
     * @var ServiceLocatorInterface
     */
    protected $serviceManager;

    /**
     * @var SessionManager
     */
    protected $_session = null;

    /**
     * @var string
     */
    protected $_sessionNamespace = 'lemoLibrary_grid';

    /**
     * @var View
     */
    protected $_view;

    /**
     * List of grid options
     *
     * @var array
     */
    private $_gridOptions = array(
        'alternativeRows' => 'altRows',
        'alternativeRowsClass' => 'altclass',
        'autoEncodeIncomingAndPostData' => 'autoencode',
        'autowidth' => null,
        'caption' => null,
        'cellLayout' => null,
        'cellEdit' => null,
        'cellEditUrl' => 'editurl',
        'cellSaveType' => 'cellsubmit',
        'cellSaveUrl' => 'cellurl',
        'dataString' => null,
        'dataType' => null,
        'defaultPage' => 'page',
        'defaultSortColumn' => 'sortname',
        'defaultSortOrder' => 'sortorder',
        'expandColumnOnClick' => 'ExpandColClick',
        'expandColumnIdentifier' => 'ExpandColumn',
        'forceFit' => null,
        'gridState' => null,
        'grouping' => null,
        'headerTitles' => null,
        'height' => null,
        'hoverRows' => null,
        'loadOnce' => null,
        'loadType' => 'loadui',
        'multiSelect' => null,
        'multiSelectKey' => 'multikey',
        'multiSelectWidth' => null,
        'pagerElementId' => 'pager',
        'pagerPosition' => 'pagerpos',
        'pagerShowButtions' => 'pgbuttons',
        'pagerShowInput' => 'pginput',
        'requestType' => 'mtype',
        'renderFooterRow' => 'footerrow',
        'renderRecordsInfo' => 'viewrecords',
        'renderRowNumbersColumn' => 'rownumbers',
        'resizeClass' => null,
        'recordsPerPage' => 'rowNum',
        'recordsPerPageList' => 'rowList',
        'scroll' => null,
        'scrollOffset' => null,
        'scrollRows' => null,
        'scrollTimeout' => null,
        'shrinkToFit' => null,
        'sortingColumns' => 'sortable',
        'sortingColumnsDefinition' => 'viewsortcols',
        'shrinkToFit' => null,
        'treeGrid' => null,
        'treeGridType' => 'treeGridModel',
        'treeGridIcons' => 'treeIcons',
        'url' => null,
        'width' => null,
    );

    // ===== GRID PROPERTIES =====

    /**
     * Constructor
     *
     * @param  ServiceLocatorInterface            $serviceManager
     * @param  null|AdapterInterface              $adapter
     * @param  null|array|Traversable|GridOptions $options
     * @return \LemoGrid\Grid
     */
    public function __construct(ServiceLocatorInterface $serviceManager, AdapterInterface $adapter = null, $options = null)
    {
        $this->iterator = new PriorityQueue();
        $this->serviceManager = $serviceManager;

        if (null !== $adapter) {
            $this->setAdapter($adapter);
        }
        if (null !== $options) {
            $this->setOptions($options);
        }
    }

    /**
     * Set grid options
     *
     * @param  array|\Traversable|GridOptions $options
     * @throws Exception\InvalidArgumentException
     * @return Grid
     */
    public function setOptions($options)
    {
        if (!$options instanceof GridOptions) {
            if (is_object($options) && !$options instanceof Traversable) {
                throw new Exception\InvalidArgumentException(sprintf(
                        'Expected instance of LemoGrid\GridOptions; '
                        . 'received "%s"', get_class($options))
                );
            }

            $options = new GridOptions($options);
        }

        $this->options = $options;

        return $this;
    }

    /**
     * Get grid options
     *
     * @return GridOptions
     */
    public function getOptions()
    {
        if (!$this->options) {
            $this->setOptions(new GridOptions());
        }

        return $this->options;
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
        return $this->adapter;
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
            $column = $factory->create($column);
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
        if (!$this->has($column)) {
            return $this;
        }

        $entry = $this->byName[$column];
        unset($this->byName[$column]);

        $this->iterator->remove($entry);

        unset($this->columns[$column]);

        return $this;
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
     * Retrieve all attached columns
     *
     * Storage is an implementation detail of the concrete class.
     *
     * @return array|Traversable
     */
    public function getColumns()
    {
        return $this->columns;
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
     * Make a deep clone of a grid
     *
     * @return void
     */
    public function __clone()
    {
        $items = $this->iterator->toArray(PriorityQueue::EXTR_BOTH);

        $this->byName    = array();
        $this->columns  = array();
        $this->iterator  = new PriorityQueue();

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
     * Set name
     *
     * @param  string $name
     * @return Grid
     */
    public function setName($name)
    {
        return $this->name = (string) $name;
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
     * Set query params
     *
     * @param array $params
     * @return Grid
     */
    public function setQueryParams($params)
    {
        if(isset($params['filters'])) {
            if(is_array($params['filters'])) {
                $rules = $params['filters'];
            } else {
                $rules = \Zend\Json\Decoder::decode(stripslashes($params['filters']), \Zend\Json\Json::TYPE_ARRAY);
            }

            foreach($rules['rules'] as $rule) {
                $params[$rule['field']] = $rule['data'];
            }
        }

        $this->_queryParams = $params;

        return $this;
    }

    /**
     * Get query param
     *
     * @param string $name
     * @return string
     */
    public function getQueryParam($name)
    {
        if(null === $this->_queryParams) {
            $this->setQueryParams($this->getRequest()->getQuery()->toArray());
        }

        if(isset($this->_queryParams[$name])) {
            return $this->_queryParams[$name];
        }

        return null;
    }

    /**
     * Get query params
     *
     * @return array
     */
    public function getQueryParams()
    {
        if(null === $this->_queryParams) {
            $this->setQueryParams($this->getRequest()->getQuery()->toArray());
        }

        return $this->_queryParams;
    }

    /**
     * @param string $sessionNamespace
     * @return Grid
     */
    public function setSessionNamespace($sessionNamespace)
    {
        $this->_sessionNamespace = $sessionNamespace;

        return $this;
    }

    /**
     * @return string
     */
    public function getSessionNamespace()
    {
        return $this->_sessionNamespace;
    }

    /**
     * Set grid source
     *
     * @param string $source
     * @return Grid
     */
    public function setSource($source)
    {
        $this->_source = $source;

        return $this;
    }

    /**
     * Return grid source
     *
     * @return string
     */
    public function getSource()
    {
        return $this->_source;
    }

    /**
     * Set request instance
     *
     * @param \Zend\Http\PhpEnvironment\Request $request
     * @return Grid
     */
    public function setRequest(\Zend\Http\PhpEnvironment\Request $request)
    {
        $this->_request = $request;

        return $this;
    }

    /**
     * Return instance of request
     *
     * @return \Zend\Http\PhpEnvironment\Request
     */
    public function getRequest()
    {
        if(null === $this->_request) {
            $this->_request = $this->getServiceManager()->get('Zend\Http\PhpEnvironment\Request');
        }

        return $this->_request;
    }

    // Rendering

    /**
     * Retrieves the view instance.
     *
     * If none registered, instantiates a PhpRenderer instance.
     *
     * @return \Zend\View\Renderer\RendererInterface|null
     */
    public function getView()
    {
        if ($this->_view === null) {
            $this->_view = $this->getServiceManager()->get('Zend\View\Renderer\PhpRenderer');
        }

        return $this->_view;
    }

    /**
     * Sets the view object.
     *
     * @param  \Zend\View\Renderer\RendererInterface $view
     * @return Paginator
     */
    public function setView(\Zend\View\Renderer\RendererInterface $view = null)
    {
        $this->_view = $view;

        return $this;
    }

    /**
     * Set service manager instance
     *
     * @param ServiceManager $locator
     * @return void
     */
    public function setServiceManager(ServiceLocatorInterface $serviceManager)
    {
        $this->serviceManager = $serviceManager;

        return $this;
    }

    /**
     * Retrieve service manager instance
     *
     * @return ServiceManager
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }

    /**
     * Render grid
     *
     * @return string
     */
    public function render()
    {
        $sessionContainer = new \Zend\Session\Container($this->getSessionNamespace());
        $query = $this->getQueryParams();

        $sessionName = 'grid_' . $this->getName();

        if($this->getRequest()->isXmlHttpRequest() && $this->getQueryParam('_name') != $this->getName()) {
            return null;
        }

        if($sessionContainer->offsetExists($sessionName)) {
            $session = $sessionContainer->offsetGet($sessionName);
        } else {
            $session = $sessionContainer->offsetSet($sessionName, array());
        }

        if(true === $this->getRequest()->isXmlHttpRequest()) {
            if(isset($session['loaded']) && $session['loaded'] == true) {
                if(isset($session['filters']) && $session['loaded'] == true && !isset($query['filters'])) {
                    $query['filters'] = $session['filters'];
                }

                $session['page'] = @$query['page'];
                $session['rows'] = @$query['rows'];
                $session['sidx'] = @$query['sidx'];
                $session['sord'] = @$query['sord'];
                $session['filters'] = @$query['filters'];

            } else {
                $session['loaded'] = true;

                if(isset($session['page'])) {
                    $query['page'] = $session['page'];
                    $query['rows'] = $session['rows'];
                    $query['sidx'] = $session['sidx'];
                    $query['sord'] = $session['sord'];
                    $query['filters'] = $session['filters'];
                }
            }
        } else {
            unset($session['loaded']);

            if(isset($session['page'])) {
                $query['page'] = $session['page'];
                $query['rows'] = $session['rows'];
                $query['sidx'] = $session['sidx'];
                $query['sord'] = $session['sord'];
                $query['filters'] = $session['filters'];
            }
        }

        $sessionContainer->offsetSet($sessionName, $session);
        $this->setQueryParams($query);

    // ===== RENDERING =====

        if(true == $this->getRequest()->isXmlHttpRequest()) {
            $this->getAdapter()->setGrid($this);
            $items = array();
            $data = $this->getAdapter()->getData();

            if(is_array($data)) {
                foreach($data as $index => $item) {
                    $rowData = $item;

                    if($this->getTreeGrid() == true AND $this->getTreeGridModel() == self::TREE_MODEL_NESTED) {
                        $item['leaf'] = ($item['rgt'] == $item['lft'] + 1) ? 'true' : 'false';
                        $item['expanded'] = 'true';
                    }

                    if($this->getTreeGrid() == true AND $this->getTreeGridModel() == self::TREE_MODEL_ADJACENCY) {
                        $item['parent'] = $item['level'] > 0 ? $item['parent'] : 'NULL';
                        $item['leaf'] = $item['child_count'] > 0 ? 'false' : 'true';
                        $item['expanded'] = 'true';
                    }

                    // Pridame radek
                    $items[] = array(
                        'id' => $index +1,
                        'cell' => array_values($rowData)
                    );
                }
            }

            @ob_clean();
            echo \Zend\Json\Encoder::encode(array(
                'page' => $this->getAdapter()->getNumberOfCurrentPage(),
                'total' => $this->getAdapter()->getNumberOfPages(),
                'records' => $this->getAdapter()->getNumberOfRecords(),
                'rows' => $items,
            ));
            exit;
        } else {
            $colNames = array();

            foreach($this->_gridOptions as $nameProperty => $nameGrid) {
                $methodName = 'get' . ucfirst($nameProperty);

                if(method_exists($this, $methodName)) {
                    $value = call_user_func(array($this, $methodName));

                    if(null === $value) {
                        if('pagerElementId'== $nameProperty) {
                            $value = $this->getId() . '_pager';
                        }
                        if('height' == $nameProperty) {
                            $value = '100%';
                        }
                    }

                    if(!empty($value) || is_bool($value)) {
                        if(null !== $nameGrid) {
                            $nameProperty = $nameGrid;
                        }

                        $attribs[strtolower($nameProperty)] = $value;
                    }
                }
            }

            if(isset($query['sidx'])) {
                $attribs['sortname'] = $query['sidx'];
            } else {
                $attribs['sortname'] = $this->getDefaultSortColumn();
            }
            if(isset($query['sord'])) {
                $attribs['sortorder'] = $query['sord'];
            } else {
                $attribs['sortorder'] = $this->getDefaultSortOrder();
            }

            ksort($attribs);

            foreach($this->getColumns() as $column) {
                $label = $column->getLabel();

                if(!empty($label)) {
                    $label = $this->getView()->translate($label);

                }

                $colNames[] = $label;

                unset($label);
            }

            $script[] = '	$(document).ready(function(){';
            $script[] = '		$(\'#' . $this->getId() . '\').jqGrid({';

            foreach($attribs as $key => $value) {
                if(is_array($value)) {
                    $values = array();
                    foreach($value as $k => $val) {
                        if(is_bool($val)) {
                            if($val == true) {
                                $values[] = 'true';
                            } else {
                                $values[] = 'false';
                            }
                        } elseif(is_numeric($val)) {
                            $values[] = $val;
                        } elseif(strtolower($key) == 'treeicons') {
                            $values[] = $k . ":'" .  $val . "'";
                        } else {
                            $values[] = "'" .  $val . "'";
                        }
                    }

                    if(strtolower($key) == 'treeicons') {
                        $script[] = '			' . $key . ': {' . implode(',', $values) . '},';
                    } else {
                        $script[] = '			' . $key . ': [' . implode(',', $values) . '],';
                    }
                } elseif(is_numeric($value)) {
                    $script[] = '			' . $key . ': ' . $value . ',';
                } elseif(is_bool($value)) {
                    if($value == true) {
                        $value = 'true';
                    } else {
                        $value = 'false';
                    }
                    $script[] = '			' . $key . ': ' . $value . ',';
                } else {
                    $script[] = '			' . $key . ': \'' . $value . '\',';
                }
            }

            $script[] = '			colNames: [\'' . implode('\', \'', $colNames) . '\'],';
            $script[] = '			colModel: [';

            $columnsCount = count($this->getColumns());
            $a = 1;
            foreach($this->getColumns() as $column) {
                if($a != $columnsCount) { $delimiter = ','; } else { $delimiter = ''; }
                $script[] = '				{' . $column->render() . '}' . $delimiter;
                $a++;
            }

            $script[] = '			]';
            $script[] = '		});';

            $filterToolbar = $this->getFilterToolbar();
            if($filterToolbar['enabled'] == true) {
                $filterToolbar = $this->getFilterToolbar();
                if($filterToolbar['stringResult'] == true) { $stringResult = 'true'; } else { $stringResult = 'false'; }
                if($filterToolbar['searchOnEnter'] == true) { $searchOnEnter = 'true'; } else { $searchOnEnter = 'false'; }
                $script[] = '		$(\'#' . $this->getName() . '\').jqGrid(\'filterToolbar\',{stringResult: ' . $stringResult . ', searchOnEnter: ' . $searchOnEnter . '});' . PHP_EOL;
            }

            $script[] = '	$(window).bind(\'resize\', function() {';
            $script[] = '		$(\'#' . $this->getId() . '\').setGridWidth($(\'#gbox_' . $this->getId() . '\').parent().width());';
            $script[] = '	}).trigger(\'resize\');';

            $script[] = '	});';
            $xhtml[] = '<table id="' . $this->getId() . '"></table>';

            // Pokud se nema zobrazit paticka
            if($this->getRenderFooterRow() !== false) {
                $xhtml[] = '<div id="' . $attribs['pager'] . '"></div>';
            }
        }

        $this->getView()->inlineScript()->appendScript(implode(PHP_EOL, $script));

        return implode(PHP_EOL, $xhtml);
    }

    /**
     * Serializes the object as a string.  Proxies to {@link render()}.
     *
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        } catch (\Exception $e) {

            ob_clean();
            trigger_error($e->getMessage(), E_USER_WARNING);

            return $e->getMessage();
        }

        return '';
    }
}
