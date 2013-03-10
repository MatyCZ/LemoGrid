<?php

namespace LemoGrid\Column;

use LemoGrid\Exception;
use Traversable;
use Zend\Stdlib\AbstractOptions;

abstract class AbstractColumn implements ColumnInterface
{
    /**
     * @var array|Traversable|AbstractOptions
     */
    protected $attributes;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $value;

    /**
     * This function is automatically called when creating column with factory. It
     * allows to perform various operations (add columns...)
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * Set column attributes
     *
     * @param  array|\Traversable|ColumnAttributes $attributes
     * @throws Exception\InvalidArgumentException
     * @return AbstractColumn
     */
    public function setAttributes($attributes)
    {
        if (!$attributes instanceof ColumnAttributes) {
            if (is_object($attributes) && !$attributes instanceof Traversable) {
                throw new Exception\InvalidArgumentException(sprintf(
                        'Expected instance of LemoGrid\Column\ColumnAttributes; '
                            . 'received "%s"', get_class($attributes))
                );
            }

            $attributes = new ColumnAttributes($attributes);
        }

        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Get column attributes
     *
     * @return ColumnAttributes
     */
    public function getAttributes()
    {
        if (!$this->attributes) {
            $this->setAttributes(new ColumnAttributes());
        }

        return $this->attributes;
    }

    /**
     * Set the column name
     *
     * @param  string $name
     * @return AbstractColumn
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the column name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the column value
     *
     * @param string $value
     * @return AbstractColumn
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get the column value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}
