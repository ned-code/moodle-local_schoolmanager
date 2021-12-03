<?php
/**
 * @package    local_schoolmanager
 * @category   NED
 * @copyright  2021 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_schoolmanager;

defined('MOODLE_INTERNAL') || die();

/**
 * Class shared_lib
 *
 * @package local_schoolmanager
 */
class shared_lib extends \local_ned_controller\shared\base_class {
    use \local_ned_controller\shared\base_trait;
}

shared_lib::init();
