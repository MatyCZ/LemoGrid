<?php

namespace LemoGrid\ModuleManager\Feature;

interface GridStoragenterface
{
    /**
     * Expected to return \Laminas\ServiceManager\Config object or array to
     * seed such an object.
     *
     * @return array|\Laminas\ServiceManager\Config
     */
    public function getGridStorageConfig();
}
