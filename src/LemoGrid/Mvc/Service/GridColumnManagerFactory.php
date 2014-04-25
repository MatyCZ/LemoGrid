<?php

namespace LemoGrid\Mvc\Service;

use LemoGrid\Column;
use LemoGrid\Column\ColumnInterface;
use Zend\Console\Console;
use Zend\Mvc\Exception;
use Zend\Mvc\Router\RouteMatch;
use Zend\Mvc\Service\AbstractPluginManagerFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

class GridColumnManagerFactory extends AbstractPluginManagerFactory
{
    const PLUGIN_MANAGER_CLASS = 'LemoGrid\GridColumnManager';

    /**
     * Create and return the view helper manager
     *
     * @param  ServiceLocatorInterface $serviceLocator
     * @return ColumnInterface
     * @throws Exception\RuntimeException
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $plugins = parent::createService($serviceLocator);

        // Configure Route column with router
        $plugins->setFactory('route', function ($sm) use($serviceLocator) {
            $router = Console::isConsole() ? 'HttpRouter' : 'Router';

            $column = new Column\Route;
            $column->setRouter($serviceLocator->get($router));

            \Zend\Debug\Debug::dump(get_class($serviceLocator->get($router)));
            exit;

            $match = $serviceLocator->get('application')
                ->getMvcEvent()
                ->getRouteMatch();

            if ($match instanceof RouteMatch) {
                $column->setRouteMatch($match);
            }

            return $column;
        });

        return $plugins;
    }
}
