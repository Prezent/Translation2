<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2004 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 3.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/3_0.txt.                                  |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author:  Alan Knowles <alan@akbkhome.com>                            |
// +----------------------------------------------------------------------+
//
// $Id$
//
/**
* @package Translation2
* @version $Id$
*/

/**
* require Translation2_Container class and DB_DataObjects
*/
require_once 'Translation2/Container.php';
require_once 'DB/DataObject.php';

/**
 * Storage driver for fetching data from a simple dataobject
 *
 * Database Structure:
 *
 *  // meta data etc. not supported yet...
 *  create table translations (
 *     id int(11) auto_increment not null primary key,
 *     string_id int(11),
 *     page varchar(128),
 *     lang varchar(10),
 *     translation text
 *     );
 *
 * table : translations
 *  id       // not null primary key autoincrement..
 *  string_id      // translation id
 *  page     // indexed varchar eg. (mytemplate.html)
 *  lang     // index varchar (eg. en|fr|.....)
 *  translation  // the translated value in language lang.
 *
 *
 *
 *
 * This storage driver can use all databases which are supported
 * by the PEAR DB abstraction layer to fetch data.
 *
 * @package  Translation2
 * @version  $Revision$
 */
class Translation2_Container_dataobjectsimple extends Translation2_Container
{

    // {{{ class vars

    /**
     * Additional options for the storage container
     * @var array
     */
    var $options = array(
        'table' => 'translations',
    );

    // }}}
    // {{{ Constructor

    /**
     * Constructor of the container class
     *
     * Initate connection to the database via PEAR::DB
     *
     * @param  string Connection data or DB object
     * @return object Returns an error object if something went wrong
     */
    function Translation2_Container_dataobjectsimple($table=null)
    {
        $this->options['table'] = $table ? $table : $this->options['table'];
    }

    // }}}
    // {{{ fetchLangs()

    /**
     * Fetch the available langs if they're not cached yet.
     */
    function fetchLangs()
    {
        $do = DB_DataObject::factory($this->options['table']);
        $do->selectAdd();
        $do->selectAdd('distinct lang');
        $do->find();

        $ret = array();
        while ($do->fetch()) {
            $l = $do->lang;
            $ret[$l] = array(
                'id'         => $l,
                'name'       => $l,
                'meta'       => '',
                'error_text' => '',
            );
        }
        $this->langs =  $ret;
    }

    // }}}
    // {{{ getPage()

    /**
     * Returns an array of the strings in the selected page
     *
     * @param string $pageID
     * @param string $langID
     * @return array
     */
    function getPage($page=null, $lang=null)
    {
        if (is_null($langID)) {
            $lang= $this->currentLang['id'];
        }

        $do = DB_DataObject::factory($this->options['table']);
        $do->lang = $lang;
        $do->page = $page;

        $do->find();
        $strings = array();
        while ($do->fetch()) {
            $strings[$do->string_id] = $do->translation;
        }

        return $strings;
    }

    // }}}
    // {{{ getOne()

    /**
     * Get a single item from the container, without caching the whole page
     *
     * @param string $stringID
     * @param string $pageID
     * @param string $langID
     * @return string
     */
    function getOne($string, $page=null, $lang=null)
    {
        $lang = $lang ? $lang : $this->currentLang['id'];
        // get the string id
        $do = DB_DataObject::factory($this->options['table']);
        $do->lang = '-';
        $do->page = $page;
        $do->translation = $string;
        // we dont have the base language translation..
        if (!$do->find(true)) {
            return '';
        }
        $stringID = $do->string_id;

        $do = DB_DataObject::factory($this->options['table']);
        $do->lang = $lang;
        $do->page = $page;
        $do->string_id = $stringID;
        //print_r($do);
        $do->selectAdd();
        $do->selectAdd('translation');
        if (!$do->find(true)) {
            return '';
        }
        return $do->translation;

    }

    // }}}
    // {{{ getStringID()

    /**
     * Get the stringID for the given string
     *
     * @param string $stringID
     * @param string $pageID
     * @return string
     */
    function getStringID($string, $page=null)
    {
        // get the enlgish version..

        $do = DB_DataObject::factory($this->options['table']);
        $do->lang = $this->currentLang['id'];
        $do->page = $page;
        $do->translation = $string;
        if ($do->find(true)) {
            return '';
        }
        return $do->string_id;
    }

    // }}}
}
?>