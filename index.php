<?php

	error_reporting(E_ALL);
	ini_set('display_errors',1);
	oci_internal_debug(1);

	include_once 'conexao.php';
	include_once 'model.php';

	
	class Empresa extends Model {
		
		/**
		* tableSchema
		*
		* @author Eduardo Matias 
		* @return string
		*/
		public static function tableSchema() {
			return 'meuestoque';
		}
		
		/**
		* tableName
		*
		* @author Eduardo Matias 
		* @return string
		*/
		public static function tableName() {
			return 'tbl01_empresa';
		}
		
		/**
		* primaryKey
		*
		* @author Eduardo Matias 
		* @return string
		*/
		public static function primaryKey() {
			return array('TBL01_ID');
		}
		
		/**
		* attributeLabel
		*
		* @author Eduardo Matias 
		* @return array
		*/
		public static function attributeLabel() {
			return array(
				'TBL01_ID' => 'ID',
				'TBL01_NOME' => 'NOME',
				'TBL01_CNPJ' => 'CNPJ/CPF',
				'TBL01_SENHA' => 'SENHA',
				'TBL01_ATIVO' => 'ATIVO'
			);
		}
		
		/**
		* rules
		* [validateName, [colunas]]
		*
		* @author Eduardo Matias 
		* @return array
		* @important 
		*		nao e necessario criar a regra "required" 
		*		quando na tabela do modelo o campo for obrigatorio (is not null)
		*/
		public function rules() {
			return array(
				array(
					'required', 
					array(
						'TBL01_NOME',
						'TBL01_CNPJ',
						'TBL01_SENHA',
						'TBL01_ATIVO'
					)
				)
			);
		}
	}
	
	
	$db = new Conexao();
	$model = new Empresa($db);
	echo 'MODEL';
	var_dump($model);
	echo '<hr />';
	
	
	
	// select no modelo
	echo 'FIND';
	$rModel = $model->find();
	foreach ($rModel as $v) {
		var_dump($v);
	}
	echo '<hr />';
	
	
	
	// select
	echo 'SQL - ATIVOS = 1';
	$r = $db->queryAssoc('SELECT * FROM meuestoque.tbl01_empresa WHERE TBL01_ATIVO=?', array(1));
	foreach ($r as $v) {
		var_dump($v);
	}
	echo '<hr />';
	
	
	
	
	echo 'GETCOMBO';
	$combo = $model->getCombo('tbl01_empresa', 'TBL01_ID', 'TBL01_NOME');
	var_dump($combo);
	echo '<hr />';
	
	
	
	
	echo 'SAVE <br />';
	$arrayAttr = array(
		// 'TBL01_ID' => '6',
		'TBL01_NOME' => 'Nome da empresa',
		'TBL01_CNPJ' => '92312332138195',
		'TBL01_SENHA' => 'SENHA_VALIDA_32_CARACTERES______',
		'TBL01_ATIVO' => '1'
	);
	$model->setAttributes($arrayAttr);
	$model->save();
	var_dump($model);
	echo '<hr />';
	

	
	$id = 6;
	echo 'FINDONE - INSTANCE: ID: ' . $id;
	if(($empresa_1 = $model->findOne($id))) {	
		var_dump($empresa_1);
		
		$arrayAttr['TBL01_SENHA'] = 'SENHA_GRANDE_40_CARACTERES______________';
		$arrayAttr['TBL01_SENHA'] = 'SENHA_VALIDA_32_CARACTERES______';
		$empresa_1->setAttributes($arrayAttr);
		
		echo '<br /><br />GETATTRIBUTESSET';
		var_dump($empresa_1->getAttributesSet());
		
		echo '<br /><br />GETATTRIBUTES';
		var_dump($empresa_1->getAttributes());
		
		echo '<br /><br />GETATTRIBUTESOLD';
		var_dump($empresa_1->getAttributesOld());
		
		echo '<br /><br />SAVE';
		$empresa_1->save();
		var_dump($empresa_1);
		
	}
	
	
?>








