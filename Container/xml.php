<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Storage driver for fetching data from a XML file
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Internationalization
 * @package    Translation2
 * @author     Lorenzo Alberton <l dot alberton at quipo dot it>
 * @author     Olivier Guilyardi <olivier at samalyse dot com>
 * @copyright  2004-2005 Lorenzo Alberton, Olivier Guilyardi
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/Translation2
 */

/**
 * require Translation2_Container class
 */
require_once 'Translation2/Container.php';
/**
 * require XML_Unserializer class
 */
require_once 'XML/Unserializer.php';
/**
 * Document Type Definition
 */
define ('TRANSLATION2_DTD',
    "<!ELEMENT translation2 (languages,pages)>\n" .
    "<!ELEMENT languages (lang*)>\n" .
    "<!ELEMENT lang (name?,meta?,error_text?,encoding?)>\n" .
    "<!ATTLIST lang id ID #REQUIRED>\n" .
    "<!ELEMENT name (#PCDATA)>\n" .
    "<!ELEMENT meta (#PCDATA)>\n" .
    "<!ELEMENT error_text (#PCDATA)>\n" .
    "<!ELEMENT encoding (#PCDATA)>\n" .
    "<!ELEMENT pages (page*)>\n" .
    "<!ELEMENT page (string*)>\n" .
    "<!ATTLIST page key CDATA #REQUIRED>\n" .
    "<!ELEMENT string (tr*)>\n" .
    "<!ATTLIST string key CDATA #REQUIRED>\n" .
    "<!ELEMENT tr (#PCDATA)>\n" .
    "<!ATTLIST tr lang IDREF #REQUIRED>\n"
);

/**
 * Storage driver for fetching data from a XML file
 *
 * Example file :
 *
 * <?xml version="1.0" encoding="iso-8859-1"?>
 * <translation2>
 *     <languages>
 *         <lang id='fr_FR'>
 *             <name> English </name>
 *             <meta> Custom meta data</meta>
 *             <error_text> Non disponible en fran�ais </error_text>
 *             <encoding> iso-8859-1 </encoding>
 *         </lang>
 *         <!-- some more <lang>...</lang> -->
 *     </languages>
 *     <pages>
 *         <page key='pets'>
 *             <string key='cat'>
 *                 <tr lang='fr_FR'> Chat </tr>
 *                 <!-- some more <tr>...</tr> -->
 *             </string>
 *             <!-- some more <string>...</string> -->
 *         </page>
 *         <!-- some more <page>...</page> -->
 *     </pages>
 * </translation2>
 *
 * @category   Internationalization
 * @package    Translation2
 * @author     Lorenzo Alberton <l dot alberton at quipo dot it>
 * @author     Olivier Guilyardi <olivier at samalyse dot com>
 * @copyright  2004-2005 Lorenzo Alberton, Olivier Guilyardi
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @link       http://pear.php.net/package/Translation2
 */
class Translation2_Container_xml extends Translation2_Container
{
    // {{{ class vars

    /**
     * Unserialized XML data 
     * @var object
     */
    var $_data = null;

    /**
     * XML file name
     * @var string
     */
    var $_filename;
    
    // }}}
    // {{{ init

    /**
     * Initialize the container 
     *
     * @param  string  $filename Path to the XML file
     * @return boolean|PEAR_Error object if something went wrong
     */
    function init($options)
    {
        $this->_filename = $options['filename'];
        unset($options['filename']);
        $this->_setDefaultOptions();
        $this->_parseOptions($options);

        return $this->_loadFile();
    }

    // }}}
    // {{{ _loadFile()
    
    /**
     * Load an XML file into memory, and eventually decode the strings from UTF-8
     *
     * @access private
     * @return boolean|PEAR_Error
     */
    function _loadFile()
    {
        $keyAttr = array (
            'lang'   => 'id',
            'page'   => 'key',
            'string' => 'key',
            'tr'     => 'lang'
        );
        if (!$fp = @fopen($this->_filename, 'r')) {
            return new PEAR_Error ("Can\'t read from the XML source: {$this->_filename}");
        }
        @flock($fp, LOCK_SH);
        $unserializer = &new XML_Unserializer (array('keyAttribute' => $keyAttr));
        if (PEAR::isError($status = $unserializer->unserialize($this->_filename, true))) {
            fclose($fp);
            return $status;
        }
        fclose($fp);

        // unserialize data
        $this->_data = $unserializer->getUnserializedData();
        $this->fixEmptySets($this->_data);
        $this->_fixDuplicateEntries();

        // Handle default language settings.
        // This allows, for example, to rapidly write the meta data as:
        //
        // <lang key="fr"/>
        // <lang key="en"/>

        $defaults = array(
            'name'       => '',
            'meta'       => '',
            'error_text' => '',
            'encoding'   => 'iso-8859-1'
        );

        foreach ($this->_data['languages'] as $lang_id => $settings) {
            if (empty($settings)) {
                $this->_data['languages'][$lang_id] = $defaults;
            } else {
                $this->_data['languages'][$lang_id] =
                    array_merge($defaults, $this->_data['languages'][$lang_id]);
            }
        }

        // convert lang metadata from UTF-8
        if (PEAR::isError($e = $this->_convertLangEncodings('from_xml', $this->_data))) {
            return $e;
        }

        // convert encodings of the translated strings from xml (somehow heavy)
        return $this->_convertEncodings('from_xml', $this->_data);
    }
    
    // }}}
    // {{{ _convertEncodings()

    /** 
     * Convert strings to/from XML unique charset (UTF-8)
     *
     * @param string ['from_xml' | 'to_xml']
     * @param array  $data  Data buffer to operate on
     * @return boolean|PEAR_Error
     */
    function _convertEncodings($direction, &$data)
    {
        if ($direction == 'from_xml') {
            $source_encoding = 'UTF-8';
        } else {
            $target_encoding = 'UTF-8';
        }
        
        foreach ($data['pages'] as $page_id => $page_content) {
            foreach ($page_content as $str_id => $translations) {
                foreach ($translations as $lang => $str) {
                    if ($direction == 'from_xml') {
                        $target_encoding =
                            strtoupper($data['languages'][$lang]['encoding']);
                    } else {
                        $source_encoding =
                            strtoupper($data['languages'][$lang]['encoding']);
                    }
                    if ($target_encoding != $source_encoding) {
                        $res = iconv ($source_encoding, $target_encoding, $str);
                        if ($res === false) {
                            $msg = 'Encoding conversion error ' .
                                   "(source encoding: $source_encoding, ".
                                   "target encoding: $target_encoding, ".
                                   "processed string: \"$str\"";
                            return $this->raiseError($msg,
                                    TRANSLATION2_ERROR_ENCODING_CONVERSION,
                                    PEAR_ERROR_RETURN,
                                    E_USER_WARNING);
                        }
                        $data['pages'][$page_id][$str_id][$lang] = $res;
                    }
                }
            }
        }
        return true;
    }
         
    // }}}
    // {{{ _convertLangEncodings()

    /**
     * Convert lang data to/from XML unique charset (UTF-8)
     *
     * @param string $direction   ['from_xml' | 'to_xml']
     * @param array  $data        Data buffer to operate on
     * @return boolean|PEAR_Error
     */
    function _convertLangEncodings($direction, &$data)
    {
        static $fields = array('name', 'meta', 'error_text');

        if ($direction == 'from_xml') {
            $source_encoding = 'UTF-8';
        } else {
            $target_encoding = 'UTF-8';
        }
        
        foreach ($data['languages'] as $lang_id => $lang) {
            if ($direction == 'from_xml') {
                $target_encoding = strtoupper($lang['encoding']);
            } else {
                $source_encoding = strtoupper($lang['encoding']);
            }
            //foreach (array_keys($lang) as $field) {
            foreach ($fields as $field) {
                if ($target_encoding != $source_encoding && !empty($lang[$field])) {
                    $res = iconv ($source_encoding, $target_encoding, $lang[$field]);
                    if ($res === false) {
                        $msg = 'Encoding conversion error ' .
                               "(source encoding: $source_encoding, ".
                               "target encoding: $target_encoding, ".
                               "processed string: \"$lang[$field]\"";
                        return $this->raiseError($msg,
                                TRANSLATION2_ERROR_ENCODING_CONVERSION,
                                PEAR_ERROR_RETURN,
                                E_USER_WARNING);
                    }
                    $data['languages'][$lang_id][$field] = $res;
                }
            }
        }
        return true;
    }

    // }}}
    // {{{ _fixDuplicateEntries()
    
    /**
     * Remove duplicate entries from the xml data
     */
    function _fixDuplicateEntries()
    {
        foreach($this->_data['pages'] as $pagename => $pagedata) {
            foreach($pagedata as $stringname => $stringvalues) {
                if (is_array(array_pop($stringvalues))) {
                    $this->_data['pages'][$pagename][$stringname] =
                        call_user_func_array(array($this, '_merge'), $stringvalues);
                }
            }
        }
    }
    
    // }}}
    // {{{ fixEmptySets()

    /**
     * Turn empty strings returned by XML_Unserializer into empty arrays
     *
     * Note : this method is public because called statically by the t2xmlchk.php
     * script. It is not meant to be called by user-space code.
     *
     * @access public
     * @static
     */
    function fixEmptySets(&$data)
    {
        if (is_string($data['languages']) and trim($data['languages']) == '') {
            $data['languages'] = array();
        }
        if (is_string($data['pages']) and trim($data['pages']) == '') {
            $data['pages'] = array();
        } else {
            foreach ($data['pages'] as $pageName => $strings) {
                //if (is_string($strings) and trim($strings) == '') {
                if (is_string($strings)) {
                    $data['pages'][$pageName] = array();
                } else {
                    foreach ($strings as $stringName => $translations) {
                        if (is_string($translations) and trim($translations) == '') {
                            $data['pages'][$pageName][$stringName] = array();
                        }
                    }
                }
            }
        }
    }

    // }}}
    // {{{ _merge()

    /**
     * Wrapper for array_merge()
     * @param array reference
     */
    function _merge()
    {
        $return = array();
        foreach (func_get_args() as $arg) {
            $return = array_merge($return, $arg);
        }
        return $return;
    }
    
    // }}}
    // {{{ _setDefaultOptions()

    /**
     * Set some default options
     *
     * @access private
     * @return void
     */
    function _setDefaultOptions()
    {
        //save changes on shutdown or in real time?
        $this->options['save_on_shutdown']  = true;
    }

    // }}}
    // {{{ fetchLangs()

    /**
     * Fetch the available langs
     */
    function fetchLangs()
    {
        $res = array();
        foreach ($this->_data['languages'] as $id => $spec) {
            $spec['id'] = $id;
            $res[$id] = $spec;
        }
        $this->langs = $res;
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
    function getPage($pageID = null, $langID = null)
    {
        $langID = $this->_getLangID($langID);
        $pageID = (is_null($pageID)) ? '#NULL'  : $pageID;
        $pageID = (empty($pageID))   ? '#EMPTY' : $pageID;

        $result = array();
        foreach ($this->_data['pages'][$pageID] as $str_id => $translations) {
            $result[$str_id]  = isset($translations[$langID]) 
                                ? $translations[$langID] 
                                : null;
        }
        
        return $result;
    }

    // }}}
    // {{{ getOne()

    /**
     * Get a single item from the container
     *
     * @param string $stringID
     * @param string $pageID
     * @param string $langID
     * @return string
     */
    function getOne($stringID, $pageID = null, $langID = null)
    {
        $langID = $this->_getLangID($langID);
        $pageID = (is_null($pageID)) ? '#NULL' : $pageID;                         

        return isset($this->_data['pages'][$pageID][$stringID][$langID])
               ? $this->_data['pages'][$pageID][$stringID][$langID]
               : null;
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
    function getStringID($string, $pageID = null)
    {
        $pageID = (is_null($pageID)) ? '#NULL' : $pageID;                        
        
        foreach ($this->_data['pages'][$pageID] as $str_id => $translations) {
            if (array_search($string,$translations) !== false) {
                return $str_id;
            }
        }

        return '';
    }
    
    // }}}
}
?>