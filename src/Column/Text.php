<?php

namespace Lemo\Grid\Column;

use Lemo\Grid\Adapter\AdapterInterface;
use Lemo\Grid\Exception;
use Traversable;

class Text extends AbstractColumn
{
    /**
     * Column options
     *
     * @var TextOptions
     */
    protected $options;

    /**
     * Set column options
     *
     * @param  array|\Traversable|TextOptions $options
     * @throws Exception\InvalidArgumentException
     * @return Text
     */
    public function setOptions($options)
    {
        if (!$options instanceof TextOptions) {
            if (is_object($options) && !$options instanceof Traversable) {
                throw new Exception\InvalidArgumentException(
                    sprintf(
                        'Expected instance of Lemo\Grid\Column\TextOptions; received "%s"',
                        get_class($options)
                    )
                );
            }

            $options = new TextOptions($options);
        }

        $this->options = $options;

        return $this;
    }

    /**
     * Get column options
     *
     * @return TextOptions
     */
    public function getOptions()
    {
        if (!$this->options) {
            $this->setOptions(new TextOptions());
        }

        return $this->options;
    }

    /**
     * @param  AdapterInterface $adapter
     * @param  array            $item
     * @return string
     */
    public function renderValue(AdapterInterface $adapter, array $item)
    {
        $textToReplace = $this->getOptions()->getTextToReplace();

        if (!empty($textToReplace)) {
            return str_replace(
                array_keys($textToReplace),
                array_values($textToReplace),
                $this->getValue()
            );
        } else {
            return $this->getValue();
        }
    }
}
