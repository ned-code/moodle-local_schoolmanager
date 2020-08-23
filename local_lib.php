<?php
/**
 * local lib
 *
 * @package    local_schoolmanager
 * @subpackage NED
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager;
defined('MOODLE_INTERNAL') || die();
/** @var \stdClass $CFG */

const PLUGIN = 'schoolmanager';
const PLUGIN_TYPE = 'local';
const PLUGIN_NAME = PLUGIN_TYPE . '_' . PLUGIN;
const PLUGIN_URL = '/' . PLUGIN_TYPE . '/' . PLUGIN;
const PLUGIN_CAPABILITY = PLUGIN_TYPE.'/' . PLUGIN . ':';
const PLUGIN_PATH = __DIR__;
const DIRROOT = __DIR__ . '/../..';

/**
 * moodle get_string for this plugin
 *
 * @param      $identifier
 * @param null $params
 * @param null $plugin
 *
 * @return string
 */
function str($identifier, $params=null, $plugin=null){
    $plugin = is_null($plugin) ? PLUGIN_NAME : $plugin;
    if ($params && is_array($params)){
        $a = [];
        foreach ($params as $key => $val){
            if (is_int($key)){
                $a['{' . $key . '}'] = $val;
            } else {
                $a[$key] = $val;
            }
        }
    } else {
        $a = $params;
    }
    return get_string($identifier, $plugin, $a);
}

/**
 * moodle get_string for this plugin, or $def if string doesn't exist, or $identifier if def is null
 *
 * @param      $identifier
 * @param null $def
 * @param null $params
 * @param null $plugin
 *
 * @return string|null
 */
function check_str($identifier, $def=null, $params=null, $plugin=null){
    $plugin = is_null($plugin) ? PLUGIN_NAME : $plugin;
    if (!empty($identifier) and get_string_manager()->string_exists($identifier, $plugin)) {
        return get_string($identifier, $plugin, $params);
    } elseif (!is_null($def)){
        return $def;
    }

    return $identifier;
};

/**
 * @param string $class
 *
 * @param string $content
 * @param string $title
 *
 * @return string
 */
function fa($class='', $content='', $title='', $attr=[]){
    $attr = array_merge(['class' => 'icon fa ' . $class, 'aria-hidden' => 'true'], $attr);
    if (!empty($title)){
        $attr['title'] = $title;
    }
    return \html_writer::tag('i', $content, $attr);
}

/**
 * @param string|\moodle_url|array  $url_params - if it's array, that used [$url_text='', $params=null, $anchor=null]
 * @param string $text
 * @param string $class
 * @param array  $attr
 *
 * @return string
 */
function link($url_params='', $text='', $class='', $attr=[]){
    if ($url_params instanceof \moodle_url) {
        $m_url = $url_params;
    } else {
        if (is_string($url_params)){
            list($t_url, $params, $anchor) = [$url_params, null, null];
        } else {
            list($t_url, $params, $anchor) = $url_params + ['', null, null];
        }

        $m_url = new \moodle_url($t_url, $params, $anchor);
    }
    if (!empty($text) and get_string_manager()->string_exists($text, PLUGIN_NAME)) {
        $text = str($text);
    }
    $attr['class'] = (isset($attr['class'])) ? $attr['class'] : '';
    $attr['class'].= $class;

    return \html_writer::link($m_url, $text, $attr);
}

/**
 * @param null   $cells
 * @param string $class
 * @param null   $attr
 *
 * @return \html_table_row
 */
function row($cells=null, $class='', $attr=null){
    $row = new \html_table_row($cells);
    $row->attributes['class'] = $class;
    if ($attr){
        $row->attributes = array_merge($row->attributes, $attr);
    }
    return $row;
}

/**
 * @param null   $text
 * @param string $class
 * @param null   $attr
 *
 * @return \html_table_cell
 */
function cell($text=null, $class='', $attr=null){
    $cell = new \html_table_cell($text);
    $cell->attributes['class'] = $class;
    if ($attr){
        $cell->attributes = array_merge($cell->attributes, $attr);
    }
    return $cell;
}

/**
 * Return $key, it exists in $obj, $def otherwise
 *  if $def not set, return first key from $obj
 *  use $return_null if wish to get null as $def value
 *
 * @param      $obj
 * @param      $key
 * @param null $def
 * @param bool $return_null
 *
 * @return int|string|null
 */
function isset_key($obj, $key, $def=null, $return_null=false){
    if (is_object($obj)){
        $obj = (array)$obj;
    } elseif(!is_array($obj)){
        return $def;
    }
    if (empty($obj)){
        return $def;
    }
    reset($obj);
    $def = (is_null($def) && !$return_null) ? key($obj) : $def;
    $key = isset($obj[$key]) ? $key : $def;
    return $key;
}

/**
 * Return $val, it exists in $list, $def otherwise
 *  if $def not set, return first val from $list
 *  use $return_null if wish to get null as $def value
 *
 * @param array $list
 * @param       $val
 * @param null  $def
 * @param bool  $return_null
 *
 * @return mixed|null
 */
function isset_in_list($list, $val, $def=null, $return_null=false){
    if(!is_array($list) || empty($list)){
        return $def;
    }
    if (in_array($val, $list)){
        return $val;
    } elseif (is_null($def)){
        if ($return_null){
            return null;
        } else {
            return reset($list);
        }
    } else {
        return $def;
    }
}
