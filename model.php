<?php

class Model {

	/**
	* db
	* conexao db
	*
	* @author Eduardo Matias 
	*/
	public $db = null;

	/**
	* lastId
	* ultimo id salvo (save/update/insert)
	*
	* @author Eduardo Matias 
	*/
	public $lastId = null;
	
	/**
	* resultSave
	* resultado da acao save
	*
	* @author Eduardo Matias 
	*/
	public $resultSave = null;
	
	/**
	* attributes
	* atributos da classe de modelo
	*
	* @author Eduardo Matias 
	*/
	private $attributes = array();
	
	/**
	* attributesSet
	* atributos que foram setados (setAttribute/setAttributes)
	*
	* @author Eduardo Matias 
	*/
	private $attributesSet = array();
	
	/**
	* attributesType
	* tipo dos atributos no banco de dados
	*
	* @author Eduardo Matias 
	*/
	private $attributesType = array();
  
	
	public function __construct($db) {
		
		date_default_timezone_set('America/Sao_Paulo');
		ini_set('default_charset', 'UTF-8');
		
		// conexao db
		$this->db = $db;
		
		// cria atributos do modelo de dados
		$this->attributesModel();
	}

	
	/**
	* find
	* realiza busca na tabela do modelo
	*
	* @author Eduardo Matias 
	* @param $where string clausula where do sql
	* @return string
	*/
    public function find($where = "") {
        $sql = "SELECT * FROM " . $this::tableSchema() . "." . $this::tableName() . (!$where ? "" : " WHERE " . $where);
        return $this->db->queryAssoc($sql);
	}
	
	
	/**
	* findOne
	* realiza busca de um registro (id) na tabela do modelo
	*
	* @author Eduardo Matias 
	* @param $id string clausula where do sql
	* @return instance
	*/
    public function findOne($id) {
		$pkName = strtoupper($this::primaryKey());
		$pkValue = $id;
        $sql = "SELECT * FROM " . $this::tableSchema() . "." . $this::tableName() . " WHERE " . $pkName . '=?';
        $r = $this->db->queryAssoc($sql, array($pkValue));
		$newInstance = null;
		if (array_key_exists(0, $r)) {
			$class = get_class($this);
			$newInstance = new $class($this->db);
			$newInstance->setAttributes($r[0]);
			$newInstance->attributesSet = array($pkName => $pkValue);
		}
		return $newInstance;
	}
	
	
	/**
	* getCombo
	* realiza busca na tabela do modelo
	*
	* @author Eduardo Matias 
	* @param $where
	* @return array/json
	*/
    public function getCombo($table, $value, $label, $where = "", $json = true) {
        $sql = "SELECT " . $value . " AS ID, " . $label . " AS TEXTO FROM " . $this::tableSchema() . "." . $table . (!$where ? "" : " WHERE " . $where);
        $result = $this->db->queryAssoc($sql);
        return ($result ? ($json ? json_encode($result) : $result) : null);
	}
	
	
	/**
	* save
	* se tiver preenchido o primary key($this::primaryKey()) realiza o update caso contrario insert
	* 
	* @author Eduardo Matias 
	* @return bool
	*/
    public function save($validate = true) {
		try {
			
			$pkName = $this::primaryKey();
			
			// acao
			$action = (array_key_exists($pkName, $this->attributesSet)) ? 'update' : 'insert';
			
			// valida atributos do modelo
			$valido = ($validate) ? $this->validate($action) : true;
			if($valido !== true){
				$return = array('status' => false, 'return' => $valido);
				
			} else {
				$exec = (($action == 'update')) ? $this->$action($this->attributesSet[$pkName]) : $this->$action();
				if ($exec !== true) {
					// throw new Exception($exec);
					throw new Exception('PDO: Erro inesperado, tente novamente.');
				}
				$return = array('status' => true, 'return' => array_merge($this->attributes, $this->attributesSet));
				// clear attributes
				$this->attributesModel();
			}
			
		} catch (Exception $ex) {
			$return = array('status' => false, 'return' => $ex->getMessage());
		}
		$this->resultSave = $return;
		return $return['status'];
	}
	
	
	/**
	* insert
	* 
	* @author Eduardo Matias 
	* @return bool/string(Exception) 
	*/
    public function insert() {
		try {
			if ($this->attributesSet) {
				$colunas = array_keys($this->attributesSet);
				$valores = array_values($this->attributesSet);
				$colunasInsert = array();
				foreach ($colunas as $c) {
					$colunasInsert[] = $this->formatAttrSqlBind($c);
				}
				$SQL = "INSERT INTO " . $this::tableSchema() . "." . $this::tableName() . "(" . implode(',', $colunas) . ")VALUES(" . implode(',', $colunasInsert) . ")";
				$query = $this->db->prepare($SQL);
				foreach ($colunas as $k => $c) {
					$query->bindParam(":" . $c, $valores[$k], PDO::PARAM_STR, strlen($valores[$k]));
				}			
				if (!@$query->execute()) {
					throw new Exception($query->errorInfo());
				}
			}
			$this->lastId = $query->lastInsertId();
			$return = true;
		} catch (Exception $ex) {
			$return = $ex->getMessage();
		}
		return $return;
	}
	
	/**
	* update
	* 
	* @author Eduardo Matias 
	* @param $pk valor da primary key
	* @return bool/string(Exception) 
	*/
    public function update($pk) {
		try {
			if ($this->attributesSet) {
				$colunas = array_keys($this->attributesSet);
				$valores = array_values($this->attributesSet);
				$colunasUpdate = array();
				foreach ($colunas as $c) {
					$colunasUpdate[] = $c . "=" . $this->formatAttrSqlBind($c);
				}
				$SQL = "UPDATE " . $this::tableSchema() . "." . $this::tableName() . " SET " . implode(',', $colunasUpdate) . " WHERE " . $this::primaryKey() . "=" . $pk;
				$query = $this->db->prepare($SQL);
				foreach ($colunas as $k => $c) {
					$query->bindParam(":" . $c, $valores[$k], PDO::PARAM_STR, strlen($valores[$k]));
				}
				if (!@$query->execute()) {
					throw new Exception($query->errorInfo());
				}
			}
			$this->lastId = $pk;
			$return = true;
		} catch (Exception $ex) {
			$return = $ex->getMessage();
		}
		return $return;
	}
	
	
	/**
	* formatAttrSqlBind
	* formata sql acordo com o tipo do atributo para o bindParam
	*
	* @author Eduardo Matias 
	* @return string
	*/
    public function formatAttrSqlBind($attr) {
		switch ($this->attributesType[$attr]) {
			case 'TIMESTAMP(6)':
				$a = "TO_TIMESTAMP(:" . $attr . ",'DD/MM/YYYY HH24:MI')";
			break;
			default:
				$a = ":" . $attr;
			break;
		}
		return $a;
	}
	
	/**
	* attributesModel
	* cria os atributos da classe de modelo
	*
	* @author Eduardo Matias 
	* @return void
	*/
    public function attributesModel() {
        $sql = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '" . $this::tableName() . "' AND TABLE_SCHEMA = '" . $this::tableSchema() . "'";
		$colunas = $this->db->queryAssoc($sql);
		foreach ($colunas as $v) {
			$c = strtoupper($v['COLUMN_NAME']);
			$this->{$c} = '';
			$this->attributes[$c] = '';
			$this->attributesType[$c] = $v['DATA_TYPE'];
		}
		$this->attributesSet = array();
    }
	
	/**
	* getAttributes
	* Obtem os atributos da classe de modelo
	*
	* @author Eduardo Matias 
	* @return array
	*/
	public function getAttributes() {
		return $this->attributes;
	}

	/**
	* getAttributesSet
	* Obtem os atributos setados da classe de modelo
	*
	* @author Eduardo Matias 
	* @return array
	*/
	public function getAttributesSet() {
		return $this->attributesSet;
	}

	/**
	* setAttributes
	* Altera os atributos da classe de modelo
	*
	* @author Eduardo Matias 
	* @param $attr array (key: nome do atributo, value: valor que sera atribuido)
	* @return void
	*/
	public function setAttributes($attr) {
		if(is_array($attr)){
			foreach ($attr as $a => $v) {
				$a = strtoupper($a);
				if(property_exists($this, $a)) {
					$this->$a = $v;
					$this->attributes[$a] = $v;
					$this->attributesSet[$a] = $v;
				}
			}
		}
	}

	/**
	* setAttribute
	* Altera o atributo da classe de modelo
	*
	* @author Eduardo Matias 
	* @param $attr string nome do atributo
	* @param $value string valor que sera atribuido
	* @return void
	*/
	public function setAttribute($attr, $value) {
		$a = strtoupper($attr);
		if(property_exists($this, $attr)) {
			$this->$attr = $value;
			$this->attributes[$attr] = $value;
			$this->attributesSet[$attr] = $value;
		}
	}
	
	/**
	* getAttributesType
	* Obtem os tipos dos atributos de classe setados no modelo
	*
	* @author Eduardo Matias 
	* @return null/array attributesType
	*/
	public function getAttributesType() {
		return $this->attributesType;	
	}
	
	/**
	* attributeLabel
	* Implementar no modelo
	*
	* @author Eduardo Matias 
	* @return array
	*/
    public static function attributeLabel() {
		return array();
	}
	
	/**
	* rules
	* [validateName, [colunas]] - implementar no modelo
	*
	* @author Eduardo Matias 
	* @return array
	*/
    public function rules() {
		return array();
	}
	
	/**
	* validate
	* Valida atributos setados no modelo
	*
	* @author Eduardo Matias 
	* @return bool/array error
	*/
	public function validate($action) {
		
		$validate = new Validate($this->attributeLabel(), $this->attributesSet, $action);
		$error = array();
		$errorHtml = '';
		$errorAttr = array();
		
		foreach ($this->rules() as $r) {
			$fn = $r[0];
			$attr = $r[1];
			
			// verifica se o velidate existe
			if(!method_exists($validate, $fn)) {
				continue;
			}
			
			if(is_array($attr)){
				foreach ($attr as $a) {
					$v = $validate->$fn($a, $r);
					if ($v !== true) {
						$error[] = array($a => $v);
						$errorAttr[$a] = $a;
						$errorHtml .= "&bull;  " . $v . "<br />";
					}
				}
			} else{
				$v = $validate->$fn($attr, $r);
				if ($v !== true) {
					$error[] = array($attr => $v);
					$errorAttr[$attr] = $attr;
					$errorHtml .= "&bull;  " . $v . "<br />";
				}
			}
		}
		
		// formata erro se existir
		if($error) {
			$return = array('error'=>$error, 'errorHtml'=> $errorHtml, 'attributes' => array_values($errorAttr));
		} else {
			$return = true;
		}
		
		return $return;
	}
  
}


class Validate {
	
	public $attributeLabel;
	public $attributes;
	// update/insert
	public $action;
	
	public function __construct($attributeLabel, $attributes, $action) {
		$this->attributeLabel = $attributeLabel;
		$this->attributes = $attributes;
		$this->action = $action;
	}
	
	public function message ($msg, $attr, $options) {
		$message = (array_key_exists('message', $options)) ? $options['message'] : $msg;
		$message = str_replace('{attribute}', (array_key_exists($attr, $this->attributeLabel) ? $this->attributeLabel[$attr] : $attr), $message);
		$message = str_replace('{value}', (array_key_exists($attr, $this->attributes) ? $this->attributes[$attr] : ''), $message);
		return  $message;
	}
	
	public function required ($attr, $options) {
		if ((array_key_exists($attr, $this->attributes) && $this->attributes[$attr] != '') || ($this->action == 'update' && !array_key_exists($attr, $this->attributes))) {
			$return = true;
		} else {
			$return = $this->message('O campo "{attribute}" é obrigatório.', $attr, $options);
		}
		return $return;
	}
	
	public function datetimeBR ($attr, $options) {
		$return = true;
		if (array_key_exists($attr, $this->attributes) && $this->attributes[$attr] != '') {
			// test: formato da data
			if (@preg_match('/^[0-9]{2}/[0-9]{2}/[0-9]{4} [0-9]{2}:[0-9]{2}{2}$/', $this->attributes[$attr])) {
				$return = $this->message('A data no campo "{attribute}" não é válida.', $attr, $options);
				
			// test: data menor que (teste acontece no primeiro parametro do teste)
			} else if(array_key_exists('<', $options) && !empty($options['<'][0]) && !empty($options['<'][1]) && $options['<'][0] == $attr){
				$attr1 = $options['<'][0];
				$attr2 = $options['<'][1];
				$data_1 = DateTime::createFromFormat('d/m/Y H:i', $this->attributes[$attr1]);
				$data_2 = DateTime::createFromFormat('d/m/Y H:i', $this->attributes[$attr2]);
				if($data_1 >= $data_2) {
					$return = 'A data "' . (array_key_exists($attr1, $this->attributeLabel) ? $this->attributeLabel[$attr1] : $attr1) . '" ';
					$return .= 'tem que ser menor que a data "' . (array_key_exists($attr2, $this->attributeLabel) ? $this->attributeLabel[$attr2] : $attr2) . '".';					
				}
			}
		}
		return $return;
	}
	
}
?>