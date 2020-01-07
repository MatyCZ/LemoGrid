<?php

namespace LemoGrid;

use Laminas\Loader\AutoloaderFactory;
use Laminas\Loader\StandardAutoloader;
use Laminas\ModuleManager\Feature\ViewHelperProviderInterface;
use Laminas\ModuleManager\ModuleManagerInterface;
use Laminas\ModuleManager\Feature\AutoloaderProviderInterface;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\ModuleManager\Feature\InitProviderInterface;
use Laminas\ModuleManager\Feature\ServiceProviderInterface;

class Module implements
    AutoloaderProviderInterface,
    ConfigProviderInterface,
    InitProviderInterface,
    ServiceProviderInterface,
    ViewHelperProviderInterface
{
    /**
     * @inheritdoc
     */
    public function getAutoloaderConfig()
    {
        return array(
            AutoloaderFactory::STANDARD_AUTOLOADER => array(
                StandardAutoloader::LOAD_NS => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    /**
     * @inheritdoc
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * @inheritdoc
     */
    public function init(ModuleManagerInterface $moduleManager)
    {
        $event = $moduleManager->getEvent();

        $serviceManager = clone $event->getParam('ServiceManager');
        $serviceListener = $serviceManager->get('ServiceListener');

        // Add managers to listener
        $serviceListener->addServiceManager(
            'Laminas\ServiceManager\ServiceManager',
            'grids',
            'LemoGrid\ModuleManager\Feature\GridProviderInterface',
            'getGridConfig'
        );
        $serviceListener->addServiceManager(
            'GridAdapterManager',
            'grid_adapters',
            'LemoGrid\ModuleManager\Feature\GridAdapterProviderInterface',
            'getGridAdapterConfig'
        );
        $serviceListener->addServiceManager(
            'GridColumnManager',
            'grid_columns',
            'LemoGrid\ModuleManager\Feature\GridColumnProviderInterface',
            'getGridColumnConfig'
        );
        $serviceListener->addServiceManager(
            'GridPlatformManager',
            'grid_platforms',
            'LemoGrid\ModuleManager\Feature\GridPlatformProviderInterface',
            'getGridPlatformConfig'
        );
        $serviceListener->addServiceManager(
            'GridStorageManager',
            'grid_storages',
            'LemoGrid\ModuleManager\Feature\GridStorageProviderInterface',
            'getGridStorageConfig'
        );

        // Add initializer to service manager
        $serviceManager->addInitializer(function ($instance) use ($serviceManager) {
            if ($instance instanceof GridFactoryAwareInterface) {
                $instance->setGridFactory($serviceManager->get('LemoGrid\GridFactory'));
            }
        });
    }

    /**
     * @inheritdoc
     */
    public function getServiceConfig()
    {
        return array(
            'abstract_factories' => array(
                'LemoGrid\GridAbstractServiceFactory',
            ),
            'factories' => array(
                'GridAdapterManager'  => 'LemoGrid\GridAdapterManagerFactory',
                'GridColumnManager'   => 'LemoGrid\GridColumnManagerFactory',
                'GridFactory' => function ($serviceManager) {
                    return new GridFactory(
                        $serviceManager->get('GridAdapterManager'),
                        $serviceManager->get('GridColumnManager'),
                        $serviceManager->get('GridPlatformManager'),
                        $serviceManager->get('GridStorageManager')
                    );
                },
                'GridPlatformManager' => 'LemoGrid\GridPlatformManagerFactory',
                'GridStorageManager'  => 'LemoGrid\GridStorageManagerFactory',
            ),
        );
    }

    /**
     * @inheritdoc
     */
    public function getViewHelperConfig()
    {
        return array(
            'invokables' => array(
                'jqgrid' => 'LemoGrid\View\Helper\JqGrid',
            ),
        );
    }
}
