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
// | Author: Lorenzo Alberton <l dot alberton at quipo dot it>            |
// +----------------------------------------------------------------------+
//
// $Id$
//
/**
 * @package Translation2
 * @version $Id$
 */

/**
 * require Translation2_Container_mdb2 class
 */
require_once 'Translation2/Container/mdb2.php';

/**
 * Storage driver for storing/fetching data to/from a database
 *
 * This storage driver can use all databases which are supported
 * by the PEAR MDB2 abstraction layer to fetch data.
 *
 * @package  Translation2
 * @version  $Revision$
 */
class Translation2_Admin_Container_mdb2 extends Translation2_Container_mdb2
{

    // {{{ class vars


    // }}}
    // {{{

    /**
     * Fetch the table names from the db
     * @access private
     * @return mixed array on success, PEAR_Error on failure
     */
    function _fetchTableNames()
    {
        $this->db->loadModule('manager');
        return $this->db->manager->listTables();
    }

    // }}}
    // {{{ createNewLang()

    /**
     * Creates a new table to store the strings in this language.
     * If the table is shared with other langs, it is ALTERed to
     * hold strings in this lang too.
     *
     * @param array $langData
     * @return mixed true on success, PEAR_Error on failure
     */
    function createNewLang($langData)
    {
        $tables = $this->_fetchTableNames();
        if (PEAR::isError($tables)) {
            return $tables;
        }

        $lang_col = $this->_getLangCol($langData['lang_id']);

        if (in_array($langData['table_name'], $tables)) {
            //table exists
            $query = sprintf('ALTER TABLE %s ADD COLUMN %s TEXT',
                            $langData['table_name'],
                            $lang_col
            );
            ++$this->_queries;
            return $this->db->query($query);
        }

        //table does not exist
        $queries = array();
        $queries[] = sprintf('CREATE TABLE %s ( '
                             .'%s VARCHAR(50) default NULL, '
                             .'%s TEXT NOT NULL, '
                             .'%s TEXT )',
                             $langData['table_name'],
                             $this->options['string_page_id_col'],
                             $this->options['string_id_col'],
                             $lang_col
        );
        $queries[] = sprintf('CREATE UNIQUE INDEX %s_%s_index ON %s (%s)',
                             $langData['table_name'],
                             $this->options['string_id_col'],
                             $langData['table_name'],
                             $this->options['string_id_col']
        );
        $queries[] = sprintf('CREATE INDEX %s_%s_index ON %s (%s)',
                             $langData['table_name'],
                             $this->options['string_page_id_col'],
                             $langData['table_name'],
                             $this->options['string_page_id_col']
        );
        $queries[] = sprintf('CREATE INDEX %s_%s_index ON %s (%s)',
                             $langData['table_name'],
                             $this->options['string_id_col'],
                             $langData['table_name'],
                             $this->options['string_id_col']
        );
        foreach($queries as $query) {
            ++$this->_queries;
            $res = $this->db->query($query);
            if ($res == false) {
                return $res;
            }
        }
        return true;
    }

    // }}}
    // {{{ addLangToAvailList()

    /**
     * Creates a new entry in the langsAvail table.
     * If the table doesn't exist yet, it is created.
     *
     * @param array $langData array('lang_id'    => 'en',
     *                              'table_name' => 'i18n',
     *                              'name'       => 'english',
     *                              'meta'       => 'some meta info',
     *                              'error_text' => 'not available',
     *                              'encoding'   => 'iso-8859-1');
     * @return mixed true on success, PEAR_Error on failure
     */
    function addLangToAvailList($langData)
    {
        $tables = $this->_fetchTableNames();
        if (PEAR::isError($tables)) {
            return $tables;
        }

        if (!in_array($this->options['langs_avail_table'], $tables)) {
            $queries = array();
            $queries[] = sprintf('CREATE TABLE %s ('
                                .'%s VARCHAR(16), '
                                .'%s VARCHAR(200), '
                                .'%s TEXT, '
                                .'%s VARCHAR(250), '
                                .'%s VARCHAR(16) )',
                                $this->options['langs_avail_table'],
                                $this->options['lang_id_col'],
                                $this->options['lang_name_col'],
                                $this->options['lang_meta_col'],
                                $this->options['lang_errmsg_col'],
                                $this->options['lang_encoding_col']
            );
            $queries[] = sprintf('CREATE UNIQUE INDEX %s_%s_index ON %s (%s)',
                                $this->options['langs_avail_table'],
                                $this->options['lang_id_col'],
                                $this->options['langs_avail_table'],
                                $this->options['lang_id_col']
            );

            foreach ($queries as $query) {
                ++$this->_queries;
                $res = $this->db->query($query);
                if (PEAR::isError($res)) {
                    return $res;
                }
            }
        }

        $query = sprintf('INSERT INTO %s (%s, %s, %s, %s, %s) VALUES (%s, %s, %s, %s, %s)',
	                $this->options['langs_avail_table'],
                    $this->options['lang_id_col'],
                    $this->options['lang_name_col'],
                    $this->options['lang_meta_col'],
                    $this->options['lang_errmsg_col'],
                    $this->options['lang_encoding_col'],
                    $this->db->quote($langData['lang_id']),
                    $this->db->quote($langData['name']),
                    $this->db->quote($langData['meta']),
                    $this->db->quote($langData['error_text']),
                    $this->db->quote($langData['encoding'])
        );

        ++$this->_queries;
        $success = $this->db->query($query);
        $this->options['strings_tables'][$langData['lang_id']] = $langData['table_name'];
        return $success;
    }

    // }}}
    // {{{

    /**
     * Remove the lang from the langsAvail table and drop the strings table.
     * If the strings table holds other langs and $force==false, then
     * only the lang column is dropped. If $force==true the whole
     * table is dropped without any check
     *
     * @param string  $langID
     * @param boolean $force
     * @return mixed true on success, PEAR_Error on failure
     */
    function removeLang($langID, $force)
    {
        //remove from langsAvail
        $query = sprintf('DELETE FROM %s WHERE %s = %s',
            $this->options['langs_avail_table'],
            $this->options['lang_id_col'],
            $this->db->quote($langID, 'text')
        );
        ++$this->_queries;
        $res = $this->db->query($query);
        if (PEAR::isError($res)) {
            return $res;
        }

        $lang_table = $this->_getLangTable($langID);
        $langs = $this->_getLangsInTable($lang_table);
        if (count($langs) > 1 && !$force) {
            //drop only the column for this lang
            $query = sprintf('ALTER TABLE %s DROP COLUMN %s',
                $lang_table,
                $this->_getLangCol($langID)
            );
            ++$this->_queries;
            return $this->db->query($query);
        }

        //remove the whole table
        ++$this->_queries;
        return $this->db->query('DROP TABLE ' . $lang_table);
    }

    // }}}
    // {{{ add()

    /**
     * Add a new entry in the strings table.
     *
     * @param string $stringID
     * @param string $pageID
     * @param array  $stringArray Associative array with string translations.
     *               Sample format:  array('en' => 'sample', 'it' => 'esempio')
     * @return mixed true on success, PEAR_Error on failure
     */
    function add($stringID, $pageID, $stringArray)
    {
        $langs = array_intersect(
            array_keys($stringArray),
            $this->getLangs('ids')
        );

        if (!count($langs)) {
            //return error: no valid lang provided
            return true;
        }

        // Langs may be in different tables - we need to split up queries along
        // table lines, so we can keep DB traffic to a minimum.

        $unquoted_stringID = $stringID;
        $unquoted_pageID = $pageID;
        $stringID = $this->db->quote($stringID, 'text');
        $pageID = is_null($pageID) ? 'NULL' : $this->db->quote($pageID, 'text');
        // Loop over the tables we need to insert into.
        foreach ($this->_tableLangs($langs) as $table => $tableLangs) {
            //before INSERTing a new record, check if it exists already.
            $exists = $this->_recordExists($unquoted_stringID, $unquoted_pageID, $table);
            if (PEAR::isError($exists)) {
                return $exists;
            }
            if ($exists) {
                $this->update($unquoted_stringID, $unquoted_pageID,
                    $this->_filterStringsByTable($stringArray, $table));
                continue;
            }

            $tableCols = $this->_getLangCols($tableLangs);
            $langData = array();
            foreach ($tableLangs as $lang) {
                $langData[$lang] =& $this->db->quote($stringArray[$lang], 'text');
            }

            $query = sprintf('INSERT INTO %s (%s, %s, %s) VALUES (%s, %s, %s)',
                             $table,
                             $this->options['string_id_col'],
                             $this->options['string_page_id_col'],
                             implode(', ', $tableCols),
                             $stringID,
                             $pageID,
                             implode(', ', $langData)
            );
            ++$this->_queries;
            $res = $this->db->query($query);
            if (PEAR::isError($res)) {
                return $res;
            }
        }

        return true;
    }

    // }}}
    // {{{ update()

    /**
     * Update an existing entry in the strings table.
     *
     * @param string $stringID
     * @param string $pageID
     * @param array  $stringArray Associative array with string translations.
     *               Sample format:  array('en' => 'sample', 'it' => 'esempio')
     * @return mixed true on success, PEAR_Error on failure
     */
    function update($stringID, $pageID, $stringArray)
    {
        $langs = array_intersect(
            array_keys($stringArray),
            $this->getLangs('ids')
        );

        if (!count($langs)) {
            //return error: no valid lang provided
            return true;
        }

        $stringID = $this->db->quote($stringID, 'text');
        $pageID = is_null($pageID) ? ' IS NULL' : ' = ' . $this->db->quote($pageID, 'text');
        foreach ($this->_tableLangs($langs) as $table => $tableLangs) {
            $tableCols = $this->_getLangCols($tableLangs);
            $langData = array();

            unset($langSet);
            foreach ($tableLangs as $lang) {
                $langSet[] = $tableCols[$lang] . ' = ' .
                             $this->db->quote($stringArray[$lang], 'text');
            }

            $query = sprintf('UPDATE %s SET %s WHERE %s = %s AND %s %s',
                             $table,
                             implode(', ', $langSet),
                             $this->options['string_id_col'],
                             $stringID,
                             $this->options['string_page_id_col'],
                             $pageID
            );
            ++$this->_queries;
            $res = $this->db->query($query);
            if (PEAR::isError($res)) {
                return $res;
            }
        }
        return true;
    }

    // }}}
    // {{{ remove()

    /**
     * Remove an entry from the strings table.
     *
     * @param string $stringID
     * @param string $pageID
     * @return mixed true on success, PEAR_Error on failure
     */
    function remove($stringID, $pageID)
    {
        $tables = array_unique($this->_getLangTables());

        $stringID = $this->db->quote($stringID, 'text');
        // get the tables and skip the non existent ones
        $dbTables = $this->_fetchTableNames();
        foreach ($tables as $table) {
            if (!in_array($table, $dbTables)) {
                continue;
            }
            $query = sprintf('DELETE FROM %s WHERE %s = %s AND %s',
                             $table,
                             $this->options['string_id_col'],
                             $stringID,
                             $this->options['string_page_id_col']
            );
            if (is_null($pageID)) {
                $query .= ' IS NULL';
            } else {
                $query .= ' = ' . $this->db->quote($pageID, 'text');
            }

            ++$this->_queries;
            $res = $this->db->query($query);
            if (PEAR::isError($res)) {
                return $res;
            }
        }

        return true;
    }

    // }}}
    // {{{ getPageNames()
    
    /**
     * Get a list of all the pageIDs in any table.
     *
     * @return array
     */
    function getPageNames()
    {
        $pages = array();
        foreach ($this->_getLangTables() as $table) {
            $query = sprintf('SELECT DISTINCT %s FROM %s',
                 $this->options['string_page_id_col'],
                 $table
            );
            ++$this->_queries;
            $res = $this->db->queryCol($query);
            if (PEAR::isError($res)) {
                return $res;
            }
            $pages = array_merge($pages, $res);
        }
        return array_unique($pages);
    }
    
    // }}}
    // {{{ _tableLangs()

    /**
     * Get table -> language mapping
     *
     * The key of the array is the table that a language is stored in;
     * the value is an /array/ of languages stored in that table.
     *
     * @param   array  $langs  Languages to get mapping for
     * @return  array  Table -> language mapping
     * @access  private
     * @see     Translation2_Container_MDB2::_getLangTable()
     * @author  Ian Eure
     */
    function &_tableLangs($langs)
    {
        $tables = array();
        foreach ($langs as $lang) {
            $table = $this->_getLangTable($lang);
            $tables[$table][] = $lang;
        }
        return $tables;
    }

    // }}}
    // {{{ _getLangTables()

    /**
     * Get tables for languages
     *
     * This is like _getLangTable(), but it returns an array of the tables for
     * multiple languages.
     *
     * @param   array    $langs  Languages to get tables for
     * @return  array
     * @access  private
     * @author  Ian Eure
     */
    function &_getLangTables($langs = null)
    {
        $tables = array();
        $langs = !is_array($langs) ? $this->getLangs('ids') : $langs;
        foreach ($langs as $lang) {
            $tables[] = $this->_getLangTable($lang);
        }
        return array_unique($tables);
    }

    // }}}
    // {{{ _getLangCols()

    /**
     * Get table columns strings are stored in
     *
     * This is like _getLangCol(), except it returns an array which contains
     * the mapping for multiple languages.
     *
     * @param   array  $langs  Languages to get mapping for
     * @return  array  Language -> column mapping
     * @access  private
     * @see     Translation2_Container_DB::_getLangCol()
     * @author  Ian Eure
     */
    function &_getLangCols($langs)
    {
        $cols = array();
        foreach ($langs as $lang) {
            $cols[$lang] = $this->_getLangCol($lang);
        }
        return $cols;
    }

    // }}}
    // {{{ _recordExists)()

    /**
     * Check if there's already a record in the table with the
     * given (pageID, stringID) pair.
     *
     * @param string $stringID
     * @param string $pageID
     * @param string $table
     * @return boolean
     * @access private
     */
    function _recordExists($stringID, $pageID, $table)
    {
        $stringID = $this->db->quote($stringID, 'text');
        $pageID = is_null($pageID) ? ' IS NULL' : ' = ' . $this->db->quote($pageID, 'text');
        $query = sprintf('SELECT COUNT(*) FROM %s WHERE %s=%s AND %s%s',
                         $table,
                         $this->options['string_id_col'],
                         $stringID,
                         $this->options['string_page_id_col'],
                         $pageID
        );
        ++$this->_queries;
        $res = $this->db->queryOne($query);
        if (PEAR::isError($res)) {
            return $res;
        }
        return ($res > 0);
    }

    // }}}
    // {{{ _filterStringsByTable()

    /**
     * Get only the strings for the langs in the given table
     *
     * @param string $pageID
     * @param array  $stringArray Associative array with string translations.
     *               Sample format:  array('en' => 'sample', 'it' => 'esempio')
     * @access private
     */
    function &_filterStringsByTable($stringArray, $table)
    {
        $strings = array();
        foreach ($stringArray as $lang => $string) {
            if ($table == $this->_getLangTable($lang)) {
                $strings[$lang] = $string;
            }
        }
        return $strings;
    }

    // }}}
    // {{{ _getLangsInTable()
    
    /**
     * Get the languages sharing the given table
     *
     * @param string $table table name
     * @return array
     */
    function &_getLangsInTable($table)
    {
        $this->fetchLangs(); // force cache refresh
        $langsInTable = array();
        foreach (array_keys($this->langs) as $lang) {
            if ($table == $this->_getLangTable($lang)) {
                $langsInTable[] = $lang;
            }
        }
        return $langsInTable;
    }
    
    // }}}
}
?>