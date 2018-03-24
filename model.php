<?php

class Model
{

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
     * attributesOld
     * atributos da classe de modelo - valor original (findOne)
     *
     * @author Eduardo Matias 
     */
    private $attributesOld = array();

    /**
     * attributesSet
     * atributos que foram setados (setAttribute/setAttributes)
     *
     * @author Eduardo Matias 
     */
    private $attributesSet = array();

    /**
     * attributesType
     * tipo dos atributos
     *
     * @author Eduardo Matias 
     */
    private $attributesType = array();

    /**
     * attributesLength
     * quantidade de caracteres dos atributos
     *
     * @author Eduardo Matias 
     */
    private $attributesLength = array();

    /**
     * attributesNull
     * permite null nos atributos
     *
     * @author Eduardo Matias 
     */
    private $attributesNull = array();

    /**
     * attributesUnique
     * atributos unique
     *
     * @author Eduardo Matias 
     */
    private $attributesUnique = array();

    public function __construct($db)
    {

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
     * @param $order string campos order by do sql
     * @param $limit string quantidade de linhas de retorno do sql
     * @return string
     */
    public function find($where = "", $order = null, $limit = null)
    {
        $sql = "SELECT * FROM " . $this::tableSchema() . "." . $this::tableName() . (!$where ? "" : " WHERE " . $where) . (!$order ? "" : " ORDER BY " . $order) . (!$limit ? "" : " LIMIT 0," . (int) $limit);
        return $this->db->queryAssoc($sql);
    }

    /**
     * findOne
     * realiza busca de um registro (id) na tabela do modelo
     *
     * @author Eduardo Matias 
     * @param $pk array[key - nome do attr, value - valor a ser comparado] clausula where do sql
     * @return instance
     */
    public function findOne($pkParam)
    {
        $pkTable = $this::primaryKey();
        if (!is_array($pkParam)) {
            $pkParam = array($pkTable[0] => $pkParam);
            //exit('findOne($param): informe uma array - ["key - nome da pk" => "value - valor a ser comparado"] ');
        }
        $pkSQL = $pkValue = $attributesSet = array();
        foreach ($pkParam as $k => $p) {
            $k = strtoupper($k);
            if (!in_array($k, $pkTable)) {
                exit('findOne($param): o attr informado nao é PK do modelo (' . $k . ')');
            }
            $pkSQL[] = $k . "=?";
            $pkValue[] = $p;
            $attributesSet[$k] = $p;
        }
        $pkName = implode(' AND ', $pkSQL);
        $sql = "SELECT * FROM " . $this::tableSchema() . "." . $this::tableName() . " WHERE " . $pkName;
        $r = $this->db->queryAssoc($sql, $pkValue);
        $newInstance = null;
        if (array_key_exists(0, $r)) {
            $class = get_class($this);
            $newInstance = new $class($this->db);
            $newInstance->setAttributes($r[0]);
            $newInstance->attributesOld = $r[0];
            $newInstance->attributesSet = $attributesSet;
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
    public function getCombo($table, $value, $label, $where = "", $json = true)
    {
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
    public function save($validate = true)
    {
        try {

            $pkName = $this::primaryKey();

            // acao			
            $pkTable = $this::primaryKey();
            $action = 'update';
            $camposPkUpdate = array();
            foreach ($pkTable as $pk) {
                $pk = strtoupper($pk);
                if (empty($this->attributesSet[$pk])) {
                    $action = 'insert';
                    break;
                }
                $camposPkUpdate[$pk] = $this->attributesSet[$pk];
            }

            // valida atributos do modelo
            $valido = ($validate) ? $this->validate($action) : true;
            if ($valido !== true) {
                $return = array('status' => false, 'return' => $valido);
            } else {

                $exec = (($action == 'update')) ? $this->$action($camposPkUpdate) : $this->$action();
                if ($exec !== true) {
                    // throw new Exception($exec);
                    throw new Exception('PDO: Erro inesperado, tente novamente.');
                }
                // add id inserido/alterado
                $this->setAttributes((is_array($this->lastId) ? $this->lastId : array($pkName[0] => $this->lastId)));
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
    public function insert()
    {
        try {
            if ($this->attributesSet) {
                $pkTable = $this::primaryKey();
                foreach ($pkTable as $pkName) {
                    if (array_key_exists($pkName, $this->attributesSet)) {
                        unset($this->attributesSet[$pkName]);
                    }
                }
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
                    $e = $query->errorInfo();
                    throw new Exception($e[2]);
                }
                $this->lastId = $this->db->lastInsertId();
            } else {
                $this->lastId = null;
            }
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
    public function update($pk)
    {
        try {
            if ($this->attributesSet) {
                if ($this->differentValueUpdate()) {
                    $pkWhere = array();
                    foreach ($pk as $k => $p) {
                        $pkWhere[] = $k . " = '" . $p . "'";
                    }
                    $colunas = array_keys($this->attributesSet);
                    $valores = array_values($this->attributesSet);
                    $colunasUpdate = array();
                    foreach ($colunas as $c) {
                        $colunasUpdate[] = $c . "=" . $this->formatAttrSqlBind($c);
                    }
                    $SQL = "UPDATE " . $this::tableSchema() . "." . $this::tableName() . " SET " . implode(',', $colunasUpdate) . " WHERE " . implode(' AND ', $pkWhere);
                    $query = $this->db->prepare($SQL);
                    foreach ($colunas as $k => $c) {
                        $query->bindParam(":" . $c, $valores[$k], PDO::PARAM_STR, strlen($valores[$k]));
                    }
                    if (!@$query->execute()) {
                        $e = $query->errorInfo();
                        throw new Exception($e[2]);
                    }
                }
            }
            $this->lastId = $pk;
            $return = true;
        } catch (Exception $ex) {
            $return = $ex->getMessage();
        }
        return $return;
    }
    
    private function differentValueUpdate()
    {
        $return = false;
        $pkTable = $this::primaryKey();
        foreach ($this->attributesSet as $key => $value) {
            if($this->attributesOld[$key] != $value) {
                $return = true;
            } else if (!in_array($key, $pkTable)) {
                unset($this->attributesSet[$key]);
            }
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
    public function formatAttrSqlBind($attr)
    {
        switch ($this->attributesType[$attr]) {
            case 'TIMESTAMP(6)':
                $a = "TO_TIMESTAMP(:" . $attr . ",'DD/MM/YYYY HH24:MI')";
                break;
            case 'DATE':
                $a = "STR_TO_DATE(:" . $attr . ",'%d/%m/%Y')";
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
    public function attributesModel()
    {
        $this->attributesSet = array();
        $this->attributesUnique = array();
        $sql = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, IS_NULLABLE, COLUMN_KEY FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '" . $this::tableName() . "' AND TABLE_SCHEMA = '" . $this::tableSchema() . "'";
        $colunas = $this->db->queryAssoc($sql);
        foreach ($colunas as $v) {
            $c = strtoupper($v['COLUMN_NAME']);
            $this->{$c} = '';
            $this->attributes[$c] = '';
            $this->attributesType[$c] = strtoupper($v['DATA_TYPE']);
            $this->attributesLength[$c] = ($v['CHARACTER_MAXIMUM_LENGTH']) ? : $v['NUMERIC_PRECISION'];
            $this->attributesNull[$c] = ($v['IS_NULLABLE'] != 'NO');
            if ($v['COLUMN_KEY'] == 'UNI') {
                $this->attributesUnique[] = $c;
            }
        }
    }

    /**
     * getAttributes
     * Obtem os atributos da classe de modelo
     *
     * @author Eduardo Matias 
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * getAttributesOld
     * Obtem os atributos com os valores "originais" (findOne) da classe de modelo
     *
     * @author Eduardo Matias 
     * @return array
     */
    public function getAttributesOld()
    {
        return $this->attributesOld;
    }

    /**
     * getAttributesSet
     * Obtem os atributos setados da classe de modelo
     *
     * @author Eduardo Matias 
     * @return array
     */
    public function getAttributesSet()
    {
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
    public function setAttributes($attr)
    {
        if (is_array($attr)) {
            foreach ($attr as $a => $v) {
                $a = strtoupper($a);
                if (property_exists($this, $a)) {
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
    public function setAttribute($attr, $value)
    {
        $attr = strtoupper($attr);
        if (property_exists($this, $attr)) {
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
    public function getAttributesType()
    {
        return $this->attributesType;
    }

    /**
     * attributeLabel
     * Implementar no modelo
     *
     * @author Eduardo Matias 
     * @return array
     */
    public static function attributeLabel()
    {
        return array();
    }

    /**
     * rules
     * [validateName, [colunas]] - implementar no modelo
     *
     * @author Eduardo Matias 
     * @return array
     */
    public function rules()
    {
        return array();
    }

    /**
     * msgErrorValidate
     * Valida atributos setados no modelo
     *
     * @author Eduardo Matias 
     * @param $action string "update" or "insert"
     * @return bool/array error
     */
    private function msgErrorValidate($tipo, $attr = null, $value = null)
    {
        $attributeLabel = $this->attributeLabel();
        $attr = isset($attributeLabel[$attr]) ? $attributeLabel[$attr] : $attr;
        $msg = array(
            'NULL' => 'O campo "' . $attr . '" é obrigatório.',
            'NUMBER' => 'O campo "' . $attr . '" não é um numero.',
            'LENGTH_DIG' => 'O campo "' . $attr . '" não pode ter mais que ' . $value . ' dígito(s).',
            'LENGTH_STR' => 'O campo "' . $attr . '" não pode ter mais que ' . $value . ' caractere(s).',
            'UNIQUE' => 'O campo "' . $attr . '" (' . $value . ') já foi cadastrado na base de dados.'
        );
        return $msg[$tipo];
    }

    /**
     * validate
     * Valida atributos setados no modelo
     *
     * @author Eduardo Matias 
     * @param $action string "update" or "insert"
     * @return bool/array error
     */
    public function validate($action)
    {
        $validate = new Validate($this->attributeLabel(), $this->attributesSet, $this->attributesNull, $action);
        $primaryKey = $this::primaryKey();
        $e = array();
        $error = array();
        $errorAttr = array();
        $errorHtml = '';
        // type and size test of attribute 
        $validateTypeSize = $this->validateTypeSize();
        if ($validateTypeSize !== true) {
            $error = $validateTypeSize['error'];
            $errorAttr = $validateTypeSize['attributes'];
            $errorHtml = $validateTypeSize['errorHtml'];
        }
        // validate - is not null (para insert olha todos os attrs, para update verifica apenas os attr alterados)
        $attributesValidateNull = ($action == 'insert') ? $this->attributes : $this->attributesSet;
        foreach ($attributesValidateNull as $k => $v) {
            if (!$this->attributesNull[$k] && empty($v) && !in_array($k, $primaryKey)) {
                $erroStr = $this->msgErrorValidate('NULL', $k);
                $e[] = array($k => $erroStr);
            }
        }
        // validate - unique
        $attributesValidateUnique = $this->attributesUnique;
        foreach ($attributesValidateUnique as $k) {
            if (array_key_exists($k, $this->attributesSet)) {
                $v = $this->attributesSet[$k];
                $whereId = array();
                if ($action == 'update') {
                    foreach ($primaryKey as $pkName) {
                        if (array_key_exists($pkName, $this->attributesSet)) {
                            $whereId[] = $pkName . "!=" . $this->attributesSet[$pkName];
                        }
                    }
                }
                $whereId = !empty($whereId) ? ' AND ' . implode(' AND ', $whereId) : '';
                $buscaUnique = $this->find($k . "='" . $v . "'" . $whereId);
                if ($buscaUnique) {
                    $erroStr = $this->msgErrorValidate('UNIQUE', $k, $v);
                    $e[] = array($k => $erroStr);
                }
            }
        }
        // validate rules
        foreach ($this->rules() as $r) {
            $fn = $r[0];
            $attr = $r[1];
            // test - velidate exist
            if (!method_exists($validate, $fn)) {
                continue;
            }
            // test - attr array
            if (!is_array($attr)) {
                $attr = array($attr);
            }
            // loop nos attr para validar
            foreach ($attr as $a) {
                $v = $validate->$fn($a, $r);
                if ($v !== true) {
                    $e[] = array($a => $v);
                }
            }
        }
        // formata erros se existir
        if (!empty($e)) {
            foreach ($e as $v) {
                $attr = array_keys($v);
                $attr = $attr[0];
                $error[] = $v;
                $errorAttr[] = $attr;
                $errorHtml .= "&bull;  " . $v[$attr] . "<br />";
            }
        }
        if (!empty($error)) {
            $return = array('error' => $error, 'attributes' => $errorAttr, 'errorHtml' => $errorHtml);
        } else {
            $return = true;
        }
        return $return;
    }

    /**
     * validateTypeSize
     * Valida o tipo e o tamanho dos atributos setados no modelo
     *
     * @author Eduardo Matias 
     * @return bool/array error
     */
    private function validateTypeSize()
    {
        $attributesSet = $this->attributesSet;
        $attributesType = $this->attributesType;
        $attributesLength = $this->attributesLength;
        $error = array();
        $errorAttr = array();
        $errorHtml = '';
        foreach ($attributesSet as $k => $v) {
            $e = array();
            $attributesTypeK = $attributesType[$k];
            $attributesLengthK = $attributesLength[$k];
            // type and size
            switch ($attributesTypeK) {
                case 'NUMBER':
                case 'INT':
                    if (!is_numeric($v)) {
                        $e[] = $this->msgErrorValidate('NUMBER', $k);
                    }
                    if (strlen($v) > $attributesLengthK) {
                        $e[] = $this->msgErrorValidate('LENGTH_DIG', $k, $attributesLengthK);
                    }
                    break;
                case 'VARCHAR2':
                case 'VARCHAR':
                    if (strlen($v) > $attributesLengthK) {
                        $e[] = $this->msgErrorValidate('LENGTH_STR', $k, $attributesLengthK);
                    }
                    break;
            }
            if (!empty($e)) {
                foreach ($e as $kk => $vv) {
                    $error[] = array($k => $vv);
                    $errorAttr[] = $k;
                    $errorHtml .= "&bull;  " . $vv . "<br />";
                }
            }
        }
        if (!empty($error)) {
            $return = array('error' => $error, 'attributes' => $errorAttr, 'errorHtml' => $errorHtml);
        } else {
            $return = true;
        }
        return $return;
    }

}

class Validate
{

    public $attributeLabel;
    public $attributes;
    public $attributesNull;
    // update/insert
    public $action;

    public function __construct($attributeLabel, $attributes, $attributesNull, $action)
    {
        $this->attributeLabel = $attributeLabel;
        $this->attributes = $attributes;
        $this->attributesNull = $attributesNull;
        $this->action = $action;
    }

    public function message($msg, $attr, $options)
    {
        $message = (array_key_exists('message', $options)) ? $options['message'] : $msg;
        $message = str_replace('{attribute}', (array_key_exists($attr, $this->attributeLabel) ? $this->attributeLabel[$attr] : $attr), $message);
        $message = str_replace('{value}', (array_key_exists($attr, $this->attributes) ? $this->attributes[$attr] : ''), $message);
        return $message;
    }

    public function required($attr, $options)
    {
        if (!($this->attributesNull[$attr]) || (array_key_exists($attr, $this->attributes) && $this->attributes[$attr] != '') || ($this->action == 'update' && !array_key_exists($attr, $this->attributes))) {
            $return = true;
        } else {
            $return = $this->message('O campo "{attribute}" é obrigatório.', $attr, $options);
        }
        return $return;
    }

    public function dateBR($attr, $options)
    {
        $return = true;
        if (array_key_exists($attr, $this->attributes) && $this->attributes[$attr] != '') {
            // test: formato da data
            if (@preg_match('/^[0-9]{2}/[0-9]{2}/[0-9]{4}$/', $this->attributes[$attr])) {
                $return = $this->message('A data no campo "{attribute}" não é válida.', $attr, $options);

                // test: data menor que (teste acontece no primeiro parametro do teste)
            } else if (array_key_exists('<', $options) && !empty($options['<'][0]) && !empty($options['<'][1]) && $options['<'][0] == $attr) {
                $attr1 = $options['<'][0];
                $attr2 = $options['<'][1];
                $data_1 = DateTime::createFromFormat('d/m/Y', $this->attributes[$attr1]);
                $data_2 = DateTime::createFromFormat('d/m/Y', $this->attributes[$attr2]);
                if ($data_1 >= $data_2) {
                    $return = 'A data "' . (array_key_exists($attr1, $this->attributeLabel) ? $this->attributeLabel[$attr1] : $attr1) . '" ';
                    $return .= 'tem que ser menor que a data "' . (array_key_exists($attr2, $this->attributeLabel) ? $this->attributeLabel[$attr2] : $attr2) . '".';
                }
            }
        }
        return $return;
    }

    public function datetimeBR($attr, $options)
    {
        $return = true;
        if (array_key_exists($attr, $this->attributes) && $this->attributes[$attr] != '') {
            // test: formato da data
            if (@preg_match('/^[0-9]{2}/[0-9]{2}/[0-9]{4} [0-9]{2}:[0-9]{2}{2}$/', $this->attributes[$attr])) {
                $return = $this->message('A data no campo "{attribute}" não é válida.', $attr, $options);

                // test: data menor que (teste acontece no primeiro parametro do teste)
            } else if (array_key_exists('<', $options) && !empty($options['<'][0]) && !empty($options['<'][1]) && $options['<'][0] == $attr) {
                $attr1 = $options['<'][0];
                $attr2 = $options['<'][1];
                $data_1 = DateTime::createFromFormat('d/m/Y H:i', $this->attributes[$attr1]);
                $data_2 = DateTime::createFromFormat('d/m/Y H:i', $this->attributes[$attr2]);
                if ($data_1 >= $data_2) {
                    $return = 'A data "' . (array_key_exists($attr1, $this->attributeLabel) ? $this->attributeLabel[$attr1] : $attr1) . '" ';
                    $return .= 'tem que ser menor que a data "' . (array_key_exists($attr2, $this->attributeLabel) ? $this->attributeLabel[$attr2] : $attr2) . '".';
                }
            }
        }
        return $return;
    }

    public function integer($attr, $options)
    {
        $return = true;
        if (array_key_exists($attr, $this->attributes) && $this->attributes[$attr] != '') {
            // test: valor inteiro
            if (@preg_match('/[^0-9]/', $this->attributes[$attr])) {
                $return = $this->message('O campo "{attribute}" deve ser inteiro.', $attr, $options);
            }
        }
        return $return;
    }

    public function email($attr, $options)
    {
        $return = true;
        if (array_key_exists($attr, $this->attributes) && $this->attributes[$attr] != '') {
            // test: email
            if (@preg_match('/^[^0-9][a-zA-Z0-9_]+([.][a-zA-Z0-9_]+)*[@][a-zA-Z0-9_]+([.][a-zA-Z0-9_]+)*[.][a-zA-Z]{2,4}$/', $this->attributes[$attr])) {
                $return = $this->message('O e-mail no campo "{attribute}" não é válido.', $attr, $options);
            }
        }
        return $return;
    }

}

?>