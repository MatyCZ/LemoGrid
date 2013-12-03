<?php

namespace LemoGrid\Platform;

use LemoGrid\GridInterface;

abstract class AbstractPlatform implements PlatformInterface
{
    const OPERATOR_BEGINS_WITH      = '^';
    const OPERATOR_CONTAINS         = '~';
    const OPERATOR_EQUAL            = '==';
    const OPERATOR_ENDS_WITH        = '$';
    const OPERATOR_GREATER          = '>';
    const OPERATOR_GREATER_OR_EQUAL = '>=';
    const OPERATOR_IN               = '|';
    const OPERATOR_LESS             = '<';
    const OPERATOR_LESS_OR_EQUAL    = '<=';
    const OPERATOR_NOT_BEGINS_WITH  = '!^';
    const OPERATOR_NOT_CONTAINS     = '!~';
    const OPERATOR_NOT_EQUAL        = '!=';
    const OPERATOR_NOT_ENDS_WITH    = '!$';
    const OPERATOR_NOT_IN           = '!|';

    /**
     * @var GridInterface
     */
    protected $grid;

    /**
     * Set grid instance
     *
     * @param  GridInterface $grid
     * @return AbstractPlatform
     */
    public function setGrid(GridInterface $grid)
    {
        $this->grid = $grid;

        return $this;
    }

    /**
     * Get grid instance
     *
     * @return GridInterface
     */
    public function getGrid()
    {
        return $this->grid;
    }
}
