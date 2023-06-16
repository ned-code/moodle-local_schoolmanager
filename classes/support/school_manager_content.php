<?php

/**
 * @package    local_schoolmanager
 * @subpackage support
 * @copyright  2020 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager\support;

/**
 * Content for school_manager_render
 */
class school_manager_content extends \stdClass{
    /** @var \renderer_base $output */
    public $output;
    public $messages = [];
    public $schools = [];
    public $crews = [];
    public $forms = [];
    public $links = [];
    public $manage = false;
    public $tables = [];
    public $buttons = [];
    public $school_name = '';


    /**
     * @param object|null $var - data to import
     */
    public function __construct($var=null){
        if (!is_null($var)){
            $this->import($var);
        }
    }

    /**
     * @param string $name
     *
     * @return null
     */
    public function __get($name){
        return null;
    }

    /**
     * Import $obj data into $this
     *
     * @param $obj
     */
    public function import($obj){
        foreach ($obj as $key => $item){
            $this->$key = $item;
        }
    }

    /**
     * Export $this data as \stdClass object
     *
     * @return \stdClass
     */
    public function export(){
        $res = new \stdClass();
        foreach ($this as $key => $item){
            $res->$key = $item;
        }

        return $res;
    }
}
