<?php

namespace Lemo\Grid\ModuleManager\Feature;

interface GridPlatformProviderInterface
{
    /**
     * Expected to return \Laminas\ServiceManager\Config object or array to
     * seed such an object.
     *
     * @return array|\Laminas\ServiceManager\Config
     */
    public function getGridPlatformConfig();
}
