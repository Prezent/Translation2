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
// | Authors: Lorenzo Alberton <l dot alberton at quipo dot it>           |
// |          Sergey Korotkov <sergey@pushok.com>                         |
// +----------------------------------------------------------------------+
//
// $Id$
//
/**
 * @package Translation2
 * @version $Id$
 */
/**
 * Load Translation2 decorator base class
 */
require_once 'Translation2/Decorator.php';

/**
 * Decorator to transalte encoding of storage to any pointed in options
 * You can set the charset to use (the default being 'ISO-8859-1'):
 * <code>
 * $tr->setOptions(array('charset' => 'UTF-8'));
 * </code>
 * @see http://www.php.net/htmlentities for a list of available charsets.
 * @package Translation2
 */
class Translation2_Decorator_Iconv extends Translation2_Decorator
{
    // {{{ class vars

    /**
     * @var string
     * @access private
     */
    var $charset = 'ISO-8859-1';
    
    /**
     * @var array
     * @access private
     */
    var $lang_charsets;

    /**
     * _prepare
     * @access private
     */
    function _getCharset($langID = null)
    {
        if (!is_array($this->lang_charsets)) {
            $this->lang_charsets = array();
            foreach ($this->translation2->getLangs('encodings') as $langID => $encoding) {
                $this->lang_charsets[$langID] = $encoding;
            }
        }
        if (!is_null($langID)) {
            return $this->lang_charsets[$langID];
        }
        return $this->lang['encoding'];
    }

    // }}}
    // {{{ get()
    
    /**
     * Get translated string
     *
     * replace special chars with the matching html entities
     *
     * @param string $stringID
     * @param string $pageID
     * @param string $langID
     * @param string $defaultText Text to display when the string is empty
     * @return string
     */
    function get($stringID, $pageID=TRANSLATION2_DEFAULT_PAGEID, $langID=null, $defaultText=null)
    {
        $str = $this->translation2->get($stringID, $pageID, $langID, $defaultText);
        
        if (PEAR::isError($str) || empty($str)) {
            return $str;
        }

        return iconv($this->_getCharset($langID), $this->charset, $str);
    }

    // }}}
    // {{{ getPage()

    /**
     * Same as getRawPage, but apply transformations when needed
     *
     * @param string $pageID
     * @param string $langID
     * @return array
     */
    function getPage($pageID=TRANSLATION2_DEFAULT_PAGEID, $langID=null)
    {
        $data = $this->translation2->getPage($pageID, $langID);
        
        $incharset = $this->_getCharset($langID);
        
        foreach (array_keys($data) as $k) {
            if (!empty($data[$k])) {
                $data[$k] = iconv($$incharset, $this->charset, $data[$k]);
            }
        }
        return $data;
    }

    // }}}
}
?>