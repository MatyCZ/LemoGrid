<?php

namespace LemoGrid;

use ArrayAccess;
use LemoGrid\Adapter\AdapterInterface;
use LemoGrid\Column\ColumnInterface;
use LemoGrid\Platform\PlatformInterface;
use LemoGrid\Storage\StorageInterface;
use Traversable;
use Zend\Stdlib\ArrayUtils;

class GridFactory
{
    /**
     * @var GridAdapterManager
     */
    protected $gridAdapterManager;

    /**
     * @var GridColumnManager
     */
    protected $gridColumnManager;

    /**
     * @var GridPlatformManager
     */
    protected $gridPlatformManager;

    /**
     * @var GridStorageManager
     */
    protected $gridStorageManager;

    /**
     * @param GridAdapterManager  $gridAdapterManager
     * @param GridColumnManager   $gridColumnManager
     * @param GridPlatformManager $gridPlatformManager
     * @param GridStorageManager  $gridStorageManager
     */
    public function __construct(
        GridAdapterManager $gridAdapterManager,
        GridColumnManager $gridColumnManager,
        GridPlatformManager $gridPlatformManager,
        GridStorageManager $gridStorageManager
    ) {
        $this->gridAdapterManager = $gridAdapterManager;
        $this->gridColumnManager = $gridColumnManager;
        $this->gridPlatformManager = $gridPlatformManager;
        $this->gridStorageManager = $gridStorageManager;
    }

    /**
     * Create an adapter
     *
     * @param  array $spec
     * @throws Exception\DomainException
     * @return AdapterInterface
     */
    public function createAdapter($spec)
    {
        $spec = $this->validateSpecification($spec, __METHOD__);
        if (!isset($spec['type'])) {
            $spec['type'] = 'LemoGrid\Adapter';
        }

        $adapter = $this->gridAdapterManager->get($spec['type']);

        if ($adapter instanceof AdapterInterface) {
            return $this->configureAdapter($adapter, $spec);
        }

        throw new Exception\DomainException(sprintf(
            '%s expects the $spec["type"] to implement one of %s, %s, or %s; received %s',
            __METHOD__,
            'LemoGrid\Adapter\AdapterInterface',
            $spec['type']
        ));
    }

    /**
     * Create a column
     *
     * @param  array|Traversable $spec
     * @return ColumnInterface
     * @throws Exception\DomainException
     */
    public function createColumn($spec)
    {
        $spec = $this->validateSpecification($spec, __METHOD__);
        $type = isset($spec['type']) ? $spec['type'] : 'LemoGrid\Column';

        $column = $this->gridColumnManager->get($type);

        if ($column instanceof ColumnInterface) {
            return $this->configureColumn($column, $spec);
        }

        throw new Exception\DomainException(sprintf(
            '%s expects the $spec["type"] to implement one of %s, %s, or %s; received %s',
            __METHOD__,
            'LemoGrid\Column\ColumnInterface',
            $spec['type']
        ));
    }

    /**
     * Create a grid
     *
     * @param  array $spec
     * @throws Exception\DomainException
     * @return GridInterface
     */
    public function createGrid($spec)
    {
        $spec = $this->validateSpecification($spec, __METHOD__);

        return $this->configureGrid(new Grid(), $spec);
    }

    /**
     * Create a platform
     *
     * @param  array $spec
     * @throws Exception\DomainException
     * @return PlatformInterface
     */
    public function createPlatform($spec)
    {
        $spec = $this->validateSpecification($spec, __METHOD__);
        if (!isset($spec['type'])) {
            $spec['type'] = 'LemoGrid\Platform';
        }

        $platform = $this->gridPlatformManager->get($spec['type']);

        if ($platform instanceof PlatformInterface) {
            return $this->configurePlatform($platform, $spec);
        }

        throw new Exception\DomainException(sprintf(
            '%s expects the $spec["type"] to implement one of %s, %s, or %s; received %s',
            __METHOD__,
            'LemoGrid\Platform\PlatformInterface',
            $spec['type']
        ));
    }

    /**
     * Create a storage
     *
     * @param  array $spec
     * @throws Exception\DomainException
     * @return StorageInterface
     */
    public function createStorage($spec)
    {
        $spec = $this->validateSpecification($spec, __METHOD__);
        if (!isset($spec['type'])) {
            $spec['type'] = 'LemoGrid\Storage';
        }

        $storage = $this->gridStorageManager->get($spec['type']);

        if ($storage instanceof StorageInterface) {
            return $this->configureStorage($storage, $spec);
        }

        throw new Exception\DomainException(sprintf(
            '%s expects the $spec["type"] to implement one of %s, %s, or %s; received %s',
            __METHOD__,
            'LemoGrid\Storage\StorageInterface',
            $spec['type']
        ));
    }

    /**
     * Configure an adapter based on the provided specification
     *
     * Specification can contain any of the following:
     * - options: an array, Traversable, or ArrayAccess object of adapter options
     *
     * @param  AdapterInterface              $adapter
     * @param  array|Traversable|ArrayAccess $spec
     * @throws Exception\DomainException
     * @return AdapterInterface
     */
    public function configureAdapter(AdapterInterface $adapter, $spec)
    {
        $spec = $this->validateSpecification($spec, __METHOD__);

        $options = isset($spec['options']) ? $spec['options'] : null;

        if ($adapter instanceof Adapter\AdapterOptionsInterface && (is_array($options) || $options instanceof Traversable || $options instanceof ArrayAccess)) {
            $adapter->setOptions($options);
        }

        return $adapter;
    }

    /**
     * Configure an column based on the provided specification
     *
     * Specification can contain any of the following:
     * - type: the Column class to use; defaults to \LemoGrid\Column
     * - name: what name to provide the column, if any
     * - options: an array, Traversable, or ArrayAccess object of column options
     * - attributes: an array, Traversable, or ArrayAccess object of column
     *   attributes to assign
     *
     * @param  ColumnInterface              $column
     * @param  array|Traversable|ArrayAccess $spec
     * @throws Exception\DomainException
     * @return ColumnInterface
     */
    public function configureColumn(ColumnInterface $column, $spec)
    {
        $spec = $this->validateSpecification($spec, __METHOD__);

        $name       = isset($spec['name'])       ? $spec['name']       : null;
        $identifier = isset($spec['identifier']) ? $spec['identifier'] : null;
        $options    = isset($spec['options'])    ? $spec['options']    : null;
        $attributes = isset($spec['attributes']) ? $spec['attributes'] : null;
        $conditions = isset($spec['conditions']) ? $spec['conditions'] : null;

        if ($name !== null && $name !== '') {
            $column->setName($name);
        }

        if ($identifier !== null && $identifier !== '') {
            $column->setIdentifier($identifier);
        }

        if (is_array($options) || $options instanceof Traversable || $options instanceof ArrayAccess) {
            $column->setOptions($options);
        }

        if (is_array($attributes) || $attributes instanceof Traversable || $attributes instanceof ArrayAccess) {
            $column->setAttributes($attributes);
        }

        if (is_array($conditions) || $conditions instanceof Traversable || $conditions instanceof ArrayAccess) {
            $column->setConditions($conditions);
        }

        return $column;
    }

    /**
     * Configure a grid based on the provided specification
     *
     * Specification can contain any of the following:
     * - type: the Grid class to use; defaults to \LemoGrid\Grid
     * - name: what name to provide the grid, if any
     * - adapter: adapter instance, named adapter class
     * - columns: an array or Traversable object where each entry is an array
     *   or ArrayAccess object containing the keys:
     *   - flags: (optional) array of flags to pass to GridInterface::add()
     *   - spec: the actual column specification, per {@link configureColumn()}
     * - platform: platform instance, named platform class
     *
     * @param  GridInterface                 $grid
     * @param  array|Traversable|ArrayAccess $spec
     * @throws Exception\DomainException
     * @return GridInterface
     */
    public function configureGrid(GridInterface $grid, $spec)
    {
        $spec = $this->validateSpecification($spec, __METHOD__);

        $name = isset($spec['name']) ? $spec['name'] : null;

        if ($name !== null && $name !== '') {
            $grid->setName($name);
        }

        if (isset($spec['adapter'])) {
            $this->prepareAndInjectAdapter($spec['adapter'], $grid, __METHOD__);
        }

        if (isset($spec['columns'])) {
            $this->prepareAndInjectColumns($spec['columns'], $grid, __METHOD__);
        }

        if (isset($spec['platform'])) {
            $this->prepareAndInjectPlatform($spec['platform'], $grid, __METHOD__);
        }

        return $grid;
    }

    /**
     * Configure an platform based on the provided specification
     *
     * Specification can contain any of the following:
     * - options: an array, Traversable, or ArrayAccess object of platform options
     *
     * @param  PlatformInterface              $platform
     * @param  array|Traversable|ArrayAccess $spec
     * @throws Exception\DomainException
     * @return PlatformInterface
     */
    public function configurePlatform(PlatformInterface $platform, $spec)
    {
        $spec = $this->validateSpecification($spec, __METHOD__);

        $options = isset($spec['options'])? $spec['options'] : null;

        if (is_array($options) || $options instanceof Traversable || $options instanceof ArrayAccess) {
            $platform->setOptions($options);
        }

        return $platform;
    }

    /**
     * Configure an storage based on the provided specification
     *
     * Specification can contain any of the following:
     * - options: an array, Traversable, or ArrayAccess object of storage options
     *
     * @param  StorageInterface              $storage
     * @param  array|Traversable|ArrayAccess $spec
     * @throws Exception\DomainException
     * @return StorageInterface
     */
    public function configureStorage(StorageInterface $storage, $spec)
    {
        $this->validateSpecification($spec, __METHOD__);

        return $storage;
    }

    /**
     * Validate a provided specification
     *
     * Ensures we have an array, Traversable, or ArrayAccess object, and returns it.
     *
     * @param  array|Traversable|ArrayAccess $spec
     * @param  string $method Method invoking the validator
     * @return array|ArrayAccess
     * @throws Exception\InvalidArgumentException for invalid $spec
     */
    protected function validateSpecification($spec, $method)
    {
        if (is_array($spec)) {
            return $spec;
        }

        if ($spec instanceof Traversable) {
            $spec = ArrayUtils::iteratorToArray($spec);
            return $spec;
        }

        if (!$spec instanceof ArrayAccess) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array, or object implementing Traversable or ArrayAccess; received "%s"',
                $method,
                (is_object($spec) ? get_class($spec) : gettype($spec))
            ));
        }

        return $spec;
    }

    /**
     * Prepare and inject a named adapter
     *
     * Takes a string indicating a adapter class name (or a concrete instance), try first to instantiates the class
     * by pulling it from service manager, and injects the adapter instance into the form.
     *
     * @param  string|array|Adapter\AdapterInterface $adapterOrName
     * @param  GridInterface                         $grid
     * @param  string                                $method
     * @return void
     * @throws Exception\DomainException If $adapterOrName is not a string, does not resolve to a known class, or
     *                                   the class does not implement Adapter\AdapterInterface
     */
    protected function prepareAndInjectAdapter($adapterOrName, GridInterface $grid, $method)
    {
        if (is_object($adapterOrName) && $adapterOrName instanceof Adapter\AdapterInterface) {
            $grid->setAdapter($adapterOrName);
            return;
        }

        if (is_array($adapterOrName)) {
            if (!isset($adapterOrName['type'])) {
                throw new Exception\DomainException(sprintf(
                    '%s expects array specification to have a type value',
                    $method
                ));
            }
            $adapterOptions = (isset($adapterOrName['options'])) ? $adapterOrName['options'] : [];
            $adapterOrName = $adapterOrName['type'];
        } else {
            $adapterOptions = [];
        }

        if (is_string($adapterOrName)) {
            $adapter = $this->getAdapterFromName($adapterOrName);
        }

        if (!$adapter instanceof Adapter\AdapterInterface) {
            throw new Exception\DomainException(sprintf(
                '%s expects a valid implementation of LemoGrid\Adapter\AdapterInterface; received "%s"',
                $method,
                $adapterOrName
            ));
        }

        if (!empty($adapterOptions) && $adapter instanceof Adapter\AdapterOptionsInterface) {
            $adapter->setOptions($adapterOptions);
        }

        $grid->setAdapter($adapter);
    }

    /**
     * Takes a list of column specifications, creates the columns, and injects them into the provided grid
     *
     * @param  array|Traversable|ArrayAccess $columns
     * @param  GridInterface $grid
     * @param  string $method Method invoking this one (for exception messages)
     * @return void
     */
    protected function prepareAndInjectColumns($columns, GridInterface $grid, $method)
    {
        $columns = $this->validateSpecification($columns, $method);

        foreach ($columns as $columnSpecification) {
            $flags = isset($columnSpecification['flags']) ? $columnSpecification['flags'] : [];
            $spec  = isset($columnSpecification['spec'])  ? $columnSpecification['spec']  : [];

            if (!isset($spec['type'])) {
                $spec['type'] = 'LemoGrid\Column';
            }
            $column = $this->createColumn($spec);
            $grid->add($column, $flags);
        }
    }

    /**
     * Prepare and inject a named platform
     *
     * Takes a string indicating a platform class name (or a concrete instance), try first to instantiates the class
     * by pulling it from service manager, and injects the platform instance into the form.
     *
     * @param  string|array|Platform\PlatformInterface $platformOrName
     * @param  GridInterface                           $grid
     * @param  string                                  $method
     * @return void
     * @throws Exception\DomainException If $platformOrName is not a string, does not resolve to a known class, or
     *                                   the class does not implement Platform\PlatformInterface
     */
    protected function prepareAndInjectPlatform($platformOrName, GridInterface $grid, $method)
    {
        if (is_object($platformOrName) && $platformOrName instanceof Platform\PlatformInterface) {
            $grid->setPlatform($platformOrName);
            return;
        }

        if (is_array($platformOrName)) {
            if (!isset($platformOrName['type'])) {
                throw new Exception\DomainException(sprintf(
                    '%s expects array specification to have a type value',
                    $method
                ));
            }
            $platformOptions = (isset($platformOrName['options'])) ? $platformOrName['options'] : [];
            $platformOrName = $platformOrName['type'];
        } else {
            $platformOptions = [];
        }

        if (is_string($platformOrName)) {
            $platform = $this->getPlatformFromName($platformOrName);
        }

        if (!$platform instanceof Platform\PlatformInterface) {
            throw new Exception\DomainException(sprintf(
                '%s expects a valid implementation of LemoGrid\Platform\PlatformInterface; received "%s"',
                $method,
                $platformOrName
            ));
        }

        $platform->setOptions($platformOptions);
        $grid->setPlatform($platform);
    }

    /**
     * Prepare and inject a named storage
     *
     * Takes a string indicating a storage class name (or a concrete instance), try first to instantiates the class
     * by pulling it from service manager, and injects the storage instance into the form.
     *
     * @param  string|array|Storage\StorageInterface $storageOrName
     * @param  GridInterface                           $grid
     * @param  string                                  $method
     * @return void
     * @throws Exception\DomainException If $storageOrName is not a string, does not resolve to a known class, or
     *                                   the class does not implement Storage\StorageInterface
     */
    protected function prepareAndInjectStorage($storageOrName, GridInterface $grid, $method)
    {
        if (is_object($storageOrName) && $storageOrName instanceof Storage\StorageInterface) {
            $grid->setStorage($storageOrName);
            return;
        }

        if (is_array($storageOrName)) {
            if (!isset($storageOrName['type'])) {
                throw new Exception\DomainException(sprintf(
                    '%s expects array specification to have a type value',
                    $method
                ));
            }

            $storageOrName = $storageOrName['type'];
        }

        if (is_string($storageOrName)) {
            $storage = $this->getStorageFromName($storageOrName);
        }

        if (!$storage instanceof Storage\StorageInterface) {
            throw new Exception\DomainException(sprintf(
                '%s expects a valid implementation of LemoGrid\Storage\StorageInterface; received "%s"',
                $method,
                $storageOrName
            ));
        }

        $grid->setStorage($storage);
    }

    /**
     * Try to pull adapter from service manager, or instantiates it from its name
     *
     * @param  string $adapterName
     * @return mixed
     * @throws Exception\DomainException
     */
    protected function getAdapterFromName($adapterName)
    {
        $serviceLocator = $this->getGridAdapterManager()->getServiceLocator();

        if ($serviceLocator && $serviceLocator->has($adapterName)) {
            return $serviceLocator->get($adapterName);
        }

        if (!class_exists($adapterName)) {
            throw new Exception\DomainException(sprintf(
                'Expects string adapter name to be a valid class name; received "%s"',
                $adapterName
            ));
        }

        $adapter = new $adapterName;
        return $adapter;
    }

    /**
     * Try to pull platform from service manager, or instantiates it from its name
     *
     * @param  string $platformName
     * @return mixed
     * @throws Exception\DomainException
     */
    protected function getPlatformFromName($platformName)
    {
        $serviceLocator = $this->getGridPlatformManager()->getServiceLocator();

        if ($serviceLocator && $serviceLocator->has($platformName)) {
            return $serviceLocator->get($platformName);
        }

        if (!class_exists($platformName)) {
            throw new Exception\DomainException(sprintf(
                'Expects string platform name to be a valid class name; received "%s"',
                $platformName
            ));
        }

        $platform = new $platformName;
        return $platform;
    }

    /**
     * Try to pull storage from service manager, or instantiates it from its name
     *
     * @param  string $storageName
     * @return mixed
     * @throws Exception\DomainException
     */
    protected function getStorageFromName($storageName)
    {
        $serviceLocator = $this->getGridStorageManager()->getServiceLocator();

        if ($serviceLocator && $serviceLocator->has($storageName)) {
            return $serviceLocator->get($storageName);
        }

        if (!class_exists($storageName)) {
            throw new Exception\DomainException(sprintf(
                'Expects string storage name to be a valid class name; received "%s"',
                $storageName
            ));
        }

        $storage = new $storageName;
        return $storage;
    }
}
