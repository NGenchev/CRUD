<?php
/**
	protected $dbh;
	
	function __construct(){
		$this->dbh = (new DB())->connect();
	}
 */
class ModelCRUD
{
	protected $dbh;

	public $table;
	public $prefix;
	public $fields;
	public $id;

	public $total_found;
	public $limit;

	## Initializating database properties (table, prefix, uniqueField)
	function __construct($table, $prefix)
	{
		$this->table = $table;
		$this->prefix = $prefix . "_";

		$this->dbh = (new DB())->connect();
	}

	## Basic method for request DB via PDO

	private function stmt($query, $params = array(), $ret = 0)
	{
		try 
		{
			var_dump($query);
			$executor = $this->dbh->prepare($query);
			$executor->execute();
			if($ret == 1) ## Return object
				if($executor->rowCount() == 1)
					return $executor->fetch(PDO::FETCH_OBJ);
				else
					return $executor->fetchAll(PDO::FETCH_OBJ);
			else ## Return count of affected rows
				return $executor->rowCount();
		} 
		catch (Exception $e) 
		{
			return $e->getMessage();
		}
	}

	// Working with WHERE statements
	private function prepParams($fields = array())
	{
		// #Actions# //
		switch($fields['settings']['action'])
		{
			case "pagination":
				$sql = " WHERE ";
				$count = count($fields)-1;

				for($i = 1; $i <= $count; $i++)
				  if($i%2!=0)
				  {
				  	$sql .= "`" . $fields[$i]['name'] . "` ". $fields[$i]['operator'] ." '";
					$sql .= $fields[$i]['value'] . "'";	
				  }
				  else
					$sql .= is_string($fields[$i]) ? (" " . $fields[$i] . " ") : "";

				$sql .= " LIMIT " . ( ( $fields['settings']['page'] - 1 ) * $fields['settings']['limit'] ) . ", " . $fields['settings']['limit'];
				$this->limit = $fields['settings']['limit'];
				break;
			default:
				break;
		}

		return $sql;
	}

	// run your own SQL 
	public function SQLExec($sql)
	{
		return self::stmt($sql, 1);
	}

	## CRUD Methods (create, read, update, delete)

	public function create($vals = array())
	{	
		$query = "INSERT INTO `". $this->table ."` (";
		$queryQueue = "";
			
		$queryParams = array_combine(self::getFields(), $vals);

		foreach($queryParams as $field=>$val)
		{
			$query 		.= "`". $field ."`, ";
			$queryQueue .= "'". $val ."', ";
		}

		// remove last coma and space from the query
		$query 		= substr($query, 0, -2);
		$queryQueue = substr($queryQueue, 0, -2);

		$query .= ") VALUES (" . $queryQueue . ")";

		if($this->stmt($query) == 1)
			return array(
				"error" => 0,
				"message" => "Успешно записахте запис в базата от данни!"
			);
		else
			return array(
				"error" => 1,
				"message" => $this->stmt($query)
			);
	}

	public function update($vals = array())
	{	
		$query_Params = array_combine(self::getFields(), $vals);

		$query = "UPDATE `". $this->table ."` SET ";
			
		foreach($query_Params as $field=>$val)
		{
			$query 	.= "`". $field . "` = '" . $val ."', ";
		}

		// remove last coma and space from the query
		$query 	= substr($query, 0, -2) . " WHERE " . $this->prefix . "id = " . self::getId();

		if($this->stmt($query))
			return array(
				"error" => 0,
				"message" => "Успешно обновихте запис в базата от данни!"
			);
		else
			return array(
				"error" => 1,
				"message" => "Несъществуващ или вече променен обект!"
			);
	}

	public function read($param = null)
	{
		$query = "SELECT SQL_CALC_FOUND_ROWS * FROM " . $this->table;

		if(is_array($param))
			$query .= self::prepParams($param);
 
		$return_object = self::stmt($query, 1);

		$getTotal = "SELECT FOUND_ROWS() as total_found";
		$this->total_found = (int)self::stmt($getTotal, 1)->total_found;
		return $return_object;
	}

	public function delete($vals = array())
	{	
		$query = "DELETE FROM " . $this->table . " WHERE " . $this->prefix . "id = " . self::getId();

		if($this->stmt($query) == 1)
			return array(
				"error" => 0,
				"message" => "Успешно изтрихте запис в базата от данни!"
			);
		else
			return array(
				"error" => 1,
				"message" => "Несъществуващ обект!"
			);
	}

	### Method for deactivating ###

	public function deactivate($vals = array())
	{	
		$query = "UPDATE `". $this->table ."` SET ";
			
		foreach($vals as $val)
		{
			$query 	.= "`". $val . "` = '" . parent::encrypt_data($val) ."', ";
		}

		// remove last coma and space from the query
		$query 	.= $this->prefix . "active = 0 WHERE " . $this->prefix . "id = " . self::getId();

		if($this->stmt($query) == 1)
			return array(
				"error" => 0,
				"message" => "Успешно деактивирахте запис в базата от данни!"
			);
		else
			return array(
				"error" => 1,
				"message" => "Несъществуващ или вече деактивиран обект!"
			);
	}

	### Set Methods ###

	public function setFields($fields = array())
	{
		foreach($fields as $field)
		{
			$this->fields[] = $this->prefix . $field;
		}

		return $this;
	}

	public function setId($id)
	{
		$this->id = $id;

		return $this;
	}

	### Get Methods ###

	public function getFields()
	{
		return $this->fields;
	}	

	public function getId()
	{
		return $this->id;
	}
	
	### Destructor

	function __destruct()
	{
		$this->dbh = NULL;
	}
}