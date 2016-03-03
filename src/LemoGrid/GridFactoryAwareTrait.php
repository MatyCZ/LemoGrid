<?php

namespace LemoGrid;

use \LemoGrid\GridFactory;

trait GridFactoryAwareTrait
{
    /**
     * @var GridFactory
     */
    protected $factory = null;

    /**
     * Compose a grid factory into the object
     
     * 
*@param GridFactory $factory
     * @return mixed
     */
    public function setGridFactory(GridFactory $factory)
    {
        $this->factory = $factory;

        return $this;
    }
}
