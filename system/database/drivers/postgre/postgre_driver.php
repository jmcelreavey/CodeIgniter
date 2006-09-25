<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Code Igniter
 *
 * An open source application development framework for PHP 4.3.2 or newer
 *
 * @package		CodeIgniter
 * @author		Rick Ellis
 * @copyright	Copyright (c) 2006, pMachine, Inc.
 * @license		http://www.codeignitor.com/user_guide/license.html 
 * @link		http://www.codeigniter.com
 * @since		Version 1.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * Postgre Database Adapter Class
 *
 * Note: _DB is an extender class that the app controller
 * creates dynamically based on whether the active record
 * class is being used or not.
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		Rick Ellis
 * @link		http://www.codeigniter.com/user_guide/database/
 */
class CI_DB_postgre_driver extends CI_DB {

	/**
	 * Non-persistent database connection
	 *
	 * @access	private called by the base class
	 * @return	resource
	 */	
	function db_connect()
	{
		$port = ($this->port == '') ? '' : " port=".$this->port;
		
		return pg_connect("host=".$this->hostname.$port." dbname=".$this->database." user=".$this->username." password=".$this->password);
	}

	// --------------------------------------------------------------------

	/**
	 * Persistent database connection
	 *
	 * @access	private called by the base class
	 * @return	resource
	 */	
	function db_pconnect()
	{
		$port = ($this->port == '') ? '' : " port=".$this->port;

		return pg_pconnect("host=".$this->hostname.$port." dbname=".$this->database." user=".$this->username." password=".$this->password);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Select the database
	 *
	 * @access	private called by the base class
	 * @return	resource
	 */	
	function db_select()
	{
		// Not needed for Postgre so we'll return TRUE
		return TRUE;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Execute the query
	 *
	 * @access	private called by the base class
	 * @param	string	an SQL query
	 * @return	resource
	 */	
	function _execute($sql)
	{
		$sql = $this->_prep_query($sql);
		return @pg_query($this->conn_id, $sql);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Prep the query
	 *
	 * If needed, each database adapter can prep the query string
	 *
	 * @access	private called by execute()
	 * @param	string	an SQL query
	 * @return	string
	 */	
    function _prep_query($sql)
    {
		return $sql;
    }

	// --------------------------------------------------------------------

	/**
	 * Begin Transaction
	 * 
	 * @access	public
	 * @return	bool		 
	 */	
	function trans_begin($test_mode = FALSE)
	{
		if ( ! $this->trans_enabled)
		{
			return TRUE;
		}
		
		// When transactions are nested we only begin/commit/rollback the outermost ones
		if ($this->_trans_depth > 0)
		{
			return TRUE;
		}

		// Reset the transaction failure flag.
		// If the $test_mode flag is set to TRUE transactions will be rolled back 
		// even if the queries produce a successful result. 
		$this->_trans_failure = ($test_mode === TRUE) ? TRUE : FALSE;

		return @pg_exec($this->conn_id, "begin");
	}

	// --------------------------------------------------------------------

	/**
	 * Commit Transaction
	 * 
	 * @access	public
	 * @return	bool		 
	 */	
	function trans_commit()
	{
		if ( ! $this->trans_enabled)
		{
			return TRUE;
		}

		// When transactions are nested we only begin/commit/rollback the outermost ones
		if ($this->_trans_depth > 0)
		{
			return TRUE;
		}

		return @pg_exec($this->conn_id, "commit");
	}

	// --------------------------------------------------------------------

	/**
	 * Rollback Transaction
	 * 
	 * @access	public
	 * @return	bool		 
	 */	
	function trans_rollback()
	{
		if ( ! $this->trans_enabled)
		{
			return TRUE;
		}

		// When transactions are nested we only begin/commit/rollback the outermost ones
		if ($this->_trans_depth > 0)
		{
			return TRUE;
		}

		return @pg_exec($this->conn_id, "rollback");
	}

	// --------------------------------------------------------------------

	/**
	 * Escape String
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function escape_str($str)	
	{	
		return pg_escape_string($str);
	}
		
	// --------------------------------------------------------------------

	/**
	 * Affected Rows
	 *
	 * @access	public
	 * @return	integer
	 */
	function affected_rows()
	{
		return @pg_affected_rows($this->result_id);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Insert ID
	 *
	 * @access	public
	 * @return	integer
	 */
	function insert_id()
	{
		$v = pg_version($this->conn_id);
		$v = $v['server'];
		
		$table	= func_num_args() > 0 ? func_get_arg(0) : null;
		$column	= func_num_args() > 1 ? func_get_arg(1) : null;
		
		if ($table == null && $v >= '8.1')
		{
			$sql='SELECT LASTVAL() as ins_id';
		}
		elseif ($table != null && $column != null && $v >= '8.0')
		{
			$sql = sprintf("SELECT pg_get_serial_sequence('%s','%s') as seq", $table, $column);
			$query = $this->query($sql);
			$row = $query->row();
			$sql = sprintf("SELECT CURRVAL('%s') as ins_id", $row->seq);
		}
		elseif ($table != null)
		{
			// seq_name passed in table parameter
			$sql = sprintf("SELECT CURRVAL('%s') as ins_id", $table);
		}
		else
		{
			return pg_last_oid($this->result_id);
		}
		$query = $this->query($sql);
		$row = $query->row();
		return $row->ins_id;
	}

	// --------------------------------------------------------------------

	/**
	 * "Count All" query
	 *
	 * Generates a platform-specific query string that counts all records in
	 * the specified database
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function count_all($table = '')
	{
		if ($table == '')
			return '0';
	
		$query = $this->query('SELECT COUNT(*) AS numrows FROM "'.$this->dbprefix.$table.'"');
		
		if ($query->num_rows() == 0)
			return '0';

		$row = $query->row();
		return $row->numrows;
	}
	
	// --------------------------------------------------------------------

	/**
	 * The error message string
	 *
	 * @access	private
	 * @return	string
	 */
	function _error_message()
	{
		return pg_last_error($this->conn_id);
	}
	
	// --------------------------------------------------------------------

	/**
	 * The error message number
	 *
	 * @access	private
	 * @return	integer
	 */
	function _error_number()
	{
		return '';
	}
	
	// --------------------------------------------------------------------

	/**
	 * Escape Table Name
	 *
	 * This function adds backticks if the table name has a period
	 * in it. Some DBs will get cranky unless periods are escaped.
	 *
	 * @access	private
	 * @param	string	the table name
	 * @return	string
	 */
	function _escape_table($table)
	{
		if (stristr($table, '.'))
		{
			$table = '"'.preg_replace("/\./", '"."', $table).'"';
		}
		
		return $table;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Insert statement
	 *
	 * Generates a platform-specific insert string from the supplied data
	 *
	 * @access	public
	 * @param	string	the table name
	 * @param	array	the insert keys
	 * @param	array	the insert values
	 * @return	string
	 */
	function _insert($table, $keys, $values)
	{	
		return "INSERT INTO ".$this->_escape_table($table)." (".implode(', ', $keys).") VALUES (".implode(', ', $values).")";
	}
	
	// --------------------------------------------------------------------

	/**
	 * Update statement
	 *
	 * Generates a platform-specific update string from the supplied data
	 *
	 * @access	public
	 * @param	string	the table name
	 * @param	array	the update data
	 * @param	array	the where clause
	 * @return	string
	 */
	function _update($table, $values, $where)
	{
		foreach($values as $key => $val)
		{
			$valstr[] = $key." = ".$val;
		}
	
		return "UPDATE ".$this->_escape_table($table)." SET ".implode(', ', $valstr)." WHERE ".implode(" ", $where);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Delete statement
	 *
	 * Generates a platform-specific delete string from the supplied data
	 *
	 * @access	public
	 * @param	string	the table name
	 * @param	array	the where clause
	 * @return	string
	 */	
	function _delete($table, $where)
	{
		return "DELETE FROM ".$this->_escape_table($table)." WHERE ".implode(" ", $where);
	}

	// --------------------------------------------------------------------

	/**
	 * Limit string
	 *
	 * Generates a platform-specific LIMIT clause
	 *
	 * @access	public
	 * @param	string	the sql query string
	 * @param	integer	the number of rows to limit the query to
	 * @param	integer	the offset value
	 * @return	string
	 */
	function _limit($sql, $limit, $offset)
	{	
		$sql .= "LIMIT ".$limit;
	
		if ($offset > 0)
		{
			$sql .= " OFFSET ".$offset;
		}
		
		return $sql;
	}

	// --------------------------------------------------------------------

	/**
	 * Close DB Connection
	 *
	 * @access	public
	 * @param	resource
	 * @return	void
	 */
	function _close($conn_id)
	{
		pg_close($conn_id);
	}


}

?>