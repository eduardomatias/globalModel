<?php

class Conexao extends PDO {

	const serve = 'localhost';
	const port = '3306';
	const dbname = 'globalmodel';
	const user = 'root';
	const pass = '';

	public function __construct() {
		$this->conexao();
	}
  
	private function conexao() {
		try {
			parent::__construct('mysql:dbname=' . self::dbname . ';host=' . self::serve . ';port=' . self::port, self::user, self::pass);
		} catch (PDOException $e) {
			die('Não foi possível conectar: ' . $e->getMessage());
		}
	}
	
	public function queryAssoc($sql, $bind = array()) {
		$sth = $this->prepare($sql);
		$sth->execute(is_array($bind) ? $bind : array());
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}

}

?>