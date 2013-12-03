<?php

namespace LemoGrid\Column;

use Exception;
use Zend\Stdlib\AbstractOptions;

class ButtonsOptions extends AbstractOptions
{
    /**
     * @var Button[]
     */
    protected $buttons = array();

    /**
     * @var string
     */
    protected $separator = '&nbsp;';

    /**
     * @param  array|Button[] $buttons
     * @throws Exception
     * @return ButtonsOptions
     */
    public function setButtons(array $buttons)
    {
        foreach ($buttons as $button) {
            if ($button instanceof Button) {
                $btn = $button;
            } else {
                $type       = isset($button['type']) ? ucfirst(strtolower($button['type'])) : null;
                $name       = isset($button['name']) ? $button['name'] : null;
                $options    = isset($button['options']) ? $button['options'] : null;
                $attributes = isset($button['attributes']) ? $button['attributes'] : null;
                $class = 'LemoGrid\Column\\' . $type;

                if (!in_array($type, array('Button', 'Route'))) {
                    throw new Exception('Button type must be Button or Route');
                }

                $btn = new $class($name, $options, $attributes);
            }

            $this->buttons[] = $btn;
        }

        return $this;
    }

    /**
     * @return Button[]
     */
    public function getButtons()
    {
        return $this->buttons;
    }

    /**
     * @param  string $separator
     * @return ButtonsOptions
     */
    public function setSeparator($separator)
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * @return string
     */
    public function getSeparator()
    {
        return $this->separator;
    }
}