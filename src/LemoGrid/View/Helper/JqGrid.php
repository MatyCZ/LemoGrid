<?php

namespace LemoGrid\View\Helper;

use LemoGrid\Column\ColumnAttributes;
use LemoGrid\Column\ColumnInterface;
use LemoGrid\Exception;
use LemoGrid\GridInterface;
use LemoGrid\Platform\JqGridOptions;
use Zend\Stdlib\AbstractOptions;

class JqGrid extends AbstractHelper
{
    /**
     * List of valid column attributes with jqGrid attribute name
     *
     * @var array
     */
    protected $columnAttributes = array(
        'align'                => 'align',
        'column_attributes'    => 'cellattr',
        'class'                => 'classes',
        'date_format'          => 'datefmt',
        'default_value'        => 'defval',
        'edi_element'          => 'edittype',
        'edit_element_options' => 'formoptions',
        'edit_options'         => 'editoptions',
        'edit_rules'           => 'editrules',
        'format'               => 'formatter',
        'format_options'       => 'formatoptions',
        'is_editable'          => 'isEditable',
        'is_fixed'             => 'page',
        'is_frozen'            => 'frozen',
        'is_hidden'            => 'hidden',
        'is_hideable'          => 'hidedlg',
        'is_searchable'        => 'search',
        'is_sortable'          => 'sortable',
        'is_resizable'         => 'resizable',
        'label'                => 'label',
        'name'                 => 'name',
        'search_element'       => 'stype',
        'search_options'       => 'searchOptions',
        'search_url'           => 'surl',
        'sort_type'            => 'sortType',
        'width'                => 'width',
    );

    /**
     * List of valid grid attributes with jqGrid attribute name
     *
     * @var array
     */
    protected $gridAttributes = array(
        'alternative_rows'                   => 'altRows',
        'alternative_rows_class'             => 'altclass',
        'auto_encode_incoming_and_post_data' => 'autoencode',
        'autowidth'                          => 'autowidth',
        'caption'                            => 'caption',
        'cell_layout'                        => 'cellLayout',
        'cell_edit'                          => 'cellEdit',
        'cell_edit_url'                      => 'editurl',
        'cell_save_type'                     => 'cellsubmit',
        'cell_save_url'                      => 'cellurl',
        'data_string'                        => 'dataString',
        'data_type'                          => 'dataType',
        'default_page'                       => 'page',
        'expand_column_identifier'           => 'ExpandColumn',
        'expand_column_on_click'             => 'ExpandColClick',
        'force_fit'                          => 'forceFit',
        'grid_state'                         => 'gridState',
        'grouping'                           => 'grouping',
        'header_titles'                      => 'headerTitles',
        'height'                             => 'height',
        'hover_rows'                         => 'hoverrows',
        'load_once'                          => 'loadOnce',
        'load_type'                          => 'loadui',
        'multi_select'                       => 'multiselect',
        'multi_select_key'                   => 'multikey',
        'multi_select_width'                 => 'multiselectWidth',
        'pager_element_id'                   => 'pager',
        'pager_position'                     => 'pagerpos',
        'pager_show_buttions'                => 'pgbuttons',
        'pager_show_input'                   => 'pginput',
        'render_footer_row'                  => 'footerrow',
        'render_records_info'                => 'viewrecords',
        'render_row_numbers_column'          => 'rownumbers',
        'request_type'                       => 'mtype',
        'resize_class'                       => 'resizeClass',
        'records_per_page'                   => 'rowNum',
        'records_per_page_list'              => 'rowList',
        'scroll'                             => 'scroll',
        'scroll_offset'                      => 'scrollOffset',
        'scroll_rows'                        => 'scrollRows',
        'scroll_timeout'                     => 'scrollTimeout',
        'shrink_to_fit'                      => 'shrinkToFit',
        'sorting_columns'                    => 'sortable',
        'sorting_columns_definition'         => 'viewsortcols',
        'sort_name'                          => 'sortname',
        'sort_order'                         => 'sortorder',
        'tree_grid'                          => 'treeGrid',
        'tree_grid_type'                     => 'treeGridModel',
        'tree_grid_icons'                    => 'treeIcons',
        'url'                                => 'url',
        'user_data'                          => 'userData',
        'user_data_on_footer'                => 'userDataOnFooter',
        'width'                              => 'width',
    );

    /**
     * Invoke helper as function
     *
     * Proxies to {@link render()}.
     *
     * @param  GridInterface|null $grid
     * @return string|JqGrid
     */
    public function __invoke(GridInterface $grid = null)
    {
        if (!$grid) {
            return $this;
        }

        if(null !== $grid) {
            $this->setGrid($grid);
        }

        return $this->render();
    }

    /**
     * Render grid
     *
     * @throws Exception\UnexpectedValueException
     * @return string
     */
    public function render()
    {
        $grid = $this->getGrid();
        $view = $this->getView();

        if (!$grid instanceof GridInterface) {
            throw new Exception\UnexpectedValueException(sprintf(
                'Expected instance of LemoGrid\GridInterface; received "%s"',
                get_class($grid)
            ));
        }

        $grid->prepare();

        if($grid->getPlatform()->isRendered()) {
            $grid->renderData();
        } else {
            $grid->getPlatform()->setOptions($this->gridModifyAttributes($grid->getPlatform()->getOptions()));
        }

        try {
            $view->inlineScript()->appendScript($this->renderScript());
            $view->inlineScript()->appendScript($this->renderScriptAutoresize());

            return $this->renderHtml();
        } catch (\Exception $e) {
            ob_clean();
            trigger_error($e->getMessage(), E_USER_WARNING);

            return $e->getMessage();
        }
    }

    /**
     * Render HTML of grid
     *
     * @return string
     */
    public function renderHtml()
    {
        $grid = $this->getGrid();

        $html = array();
        $html[] = '<table id="' . $grid->getName() . '"></table>';
        $html[] = '<div id="' . $grid->getPlatform()->getOptions()->getPagerElementId() . '"></div>';

        return implode(PHP_EOL, $html);
    }

    /**
     * Render script of grid
     *
     * @return string
     */
    public function renderScript()
    {
        $grid = $this->getGrid();

        $colNames = array();
        foreach($grid->getColumns() as $column) {
            $label = $column->getAttributes()->getLabel();

            if (null !== ($translator = $this->getTranslator()) && !empty($label)) {
                $label = $translator->translate(
                    $label, $this->getTranslatorTextDomain()
                );
            }

            $colNames[] = $label;
        }

        $script[] = '    $(\'#' . $grid->getName() . '\').jqGrid({';
        $script[] = '        ' . $this->buildScript('grid', $grid->getPlatform()->getOptions()) . ', ' . PHP_EOL;
        $script[] = '        colNames: [\'' . implode('\', \'', $colNames) . '\'],';
        $script[] = '        colModel: [';

        $i = 1;
        $columns = $grid->getColumns();
        $columnsCount = count($columns);
        foreach($columns as $column) {
            $attributes = $this->columnModifyAttributes($column, $column->getAttributes());

            if($i != $columnsCount) { $delimiter = ','; } else { $delimiter = ''; }
            $script[] = '            {' . $this->buildScript('column', $attributes) . '}' . $delimiter;
            $i++;
        }

        $script[] = '        ]';
        $script[] = '    });';

        // Can render toolbar?
        if($grid->getPlatform()->getOptions()->getFilterToolbarEnabled()) {
            $script[] = '    $(\'#' . $grid->getName() . '\').jqGrid(' . $this->buildScriptAttributes('filterToolbar', $grid->getPlatform()->getOptions()->getFilterToolbar()) . ');' . PHP_EOL;
        }

        return implode(PHP_EOL, $script);
    }

    /**
     * Render script of grid
     *
     * @return string
     */
    public function renderScriptAutoresize()
    {
        $grid = $this->getGrid();

        $script = array();
        $script[] = '    $(window).bind(\'resize\', function() {';
        $script[] = '        $(\'#' . $grid->getName() . '\').setGridWidth($(\'#gbox_' . $grid->getName() . '\').parent().width());';
        $script[] = '    }).trigger(\'resize\');';

        return implode(PHP_EOL, $script);
    }

    /**
     * Render script of attributes
     *
     * @param  string $type
     * @param  AbstractOptions $attributes
     * @return string
     */
    protected function buildScript($type, AbstractOptions $attributes)
    {
        $script = array();

        // Convert attributes to array
        $attributes = $attributes->toArray();

        foreach($attributes as $key => $value) {
            if(null === $value) {
                continue;
            }

            if('grid' == $type) {
                if(!array_key_exists($key, $this->gridAttributes)) {
                    continue;
                }

                $key = $this->gridConvertAttributeName($key);
                $separator = ', ' . PHP_EOL;
            }
            if('column' == $type) {
                if(!array_key_exists($key, $this->columnAttributes)) {
                    continue;
                }

                $key = $this->columnConvertAttributeName($key);
                $separator = ', ';
            }

            $scriptRow = $this->buildScriptAttributes($key, $value);

            if(null !== $scriptRow) {
                $script[] = $scriptRow;
            }
        }

        return implode($separator, $script);
    }

    /**
     * @param  mixed $key
     * @param  mixed $value
     * @return int|string
     */
    protected function buildScriptAttributes($key, $value)
    {
        if(is_array($value)) {
            if(empty($value)) {
                return null;
            }

            $values = array();
            foreach($value as $k => $val) {
                $values[] = $this->buildScriptAttributes($k, $val);
            }

            if (in_array($key, array('filterToolbar'))) {
                $r = '\'' . $key . '\', {' . implode(', ', array_values($values)) . '}';
            } elseif (in_array($key, array('editoptions', 'formatoptions', 'searchoptions', 'treeicons'))) {
                $r = $key . ': {' . implode(', ', $values) . '}';
            } else {
                $r = $key . ': [' . implode(', ', $values) . ']';
            }
        } elseif (is_numeric($key)) {
            if(is_bool($value)) {
                if($value == true) {
                    $value = 'true';
                } else {
                    $value = 'false';
                }
            } elseif (is_numeric($value)) {
            } else {
                $value = '\'' . $value . '\'';
            }

            $r = $value;
        } elseif (is_numeric($value)) {
            $r = $key . ': ' . $value;
        } elseif (is_bool($value)) {
            if($value == true) {
                $value = 'true';
            } else {
                $value = 'false';
            }
            $r = $key . ': ' . $value;
        } else {
            $r = $key . ': \'' . $value . '\'';
        }

        return $r;
    }

    /**
     * Convert attribute name to jqGrid attribute name
     *
     * @param  string $name
     * @return string
     */
    protected function columnConvertAttributeName($name)
    {
        if(array_key_exists($name, $this->columnAttributes)) {
            $name = $this->columnAttributes[$name];
        }

        return strtolower($name);
    }

    /**
     * Add, update or remove some column attributes
     *
     * @param  ColumnInterface  $column
     * @param  ColumnAttributes $attributes
     * @return ColumnAttributes
     */
    protected function columnModifyAttributes(ColumnInterface $column, ColumnAttributes $attributes)
    {
        // If default value is not set, add default value from search param
        if(null == $attributes->getSearchOptions() && $this->getGrid()->getParam($column->getName())) {
            $attributes->setSearchOptions(array('defaultValue' => $this->getGrid()->getParam($column->getName())));
        }

        return $attributes;
    }

    /**
     * Convert attribute name to jqGrid attribute name
     *
     * @param  string $name
     * @return string
     */
    protected function gridConvertAttributeName($name)
    {
        if(array_key_exists($name, $this->gridAttributes)) {
            $name = $this->gridAttributes[$name];
        }

        return strtolower($name);
    }

    /**
     * Modify grid attributes before rendering
     *
     * @param  JqGridOptions $attributes
     * @return JqGridOptions
     */
    protected function gridModifyAttributes(JqGridOptions $attributes)
    {
        // Pager element ID
        if(null === $attributes->getPagerElementId()) {
            $attributes->setPagerElementId($this->getGrid()->getName() . '_pager');
        }

        $attributes->setSortName($this->getGrid()->getPlatform()->getSortColumn());
        $attributes->setSortOrder($this->getGrid()->getPlatform()->getSortDirect());

        // URL
        $url = $attributes->getUrl();
        if(empty($url)) {
            $url = parse_url($_SERVER['REQUEST_URI']);
        }

        $attributes->setUrl($this->getView()->serverUrl() . $url['path'] . '?_name=' . $this->getGrid()->getName());

        return $attributes;
    }
}
