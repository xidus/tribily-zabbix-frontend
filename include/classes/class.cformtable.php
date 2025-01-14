<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
class CFormTable extends CForm{

 private $align;
 private $help;


 protected $top_items = array();
 protected $center_items = array();
 protected $bottom_items = array();

	public function __construct($title=null, $action=null, $method=null, $enctype=null, $form_variable=null){

		$this->top_items = array();
		$this->center_items = array();
		$this->bottom_items = array();
		$this->tableclass = 'formtable';

		if( null == $method ){
			$method = 'post';
		}

		if( null == $form_variable ){
			$form_variable = 'form';
		}

		parent::__construct($action,$method,$enctype);
		$this->setTitle($title);
		$this->setHelp();

		$this->addVar($form_variable, get_request($form_variable, 1));

		$this->bottom_items = new CCol(SPACE,'form_row_last');
			$this->bottom_items->setColSpan(2);
	}

	public function setAction($value){

		if(is_string($value))
			return parent::setAction($value);
		elseif(is_null($value))
			return parent::setAction($value);
		else
			return $this->error('Incorrect value for setAction ['.$value.']');
	}

	public function setName($value){
		if(!is_string($value)){
			return $this->error('Incorrect value for setAlign ['.$value.']');
		}
		$this->setAttribute('name',$value);
		$this->setAttribute('id',$value);
	return true;
	}

	public function setAlign($value){
		if(!is_string($value)){
			return $this->error('Incorrect value for setAlign ['.$value.']');
		}
		return $this->align = $value;
	}

	public function setTitle($value=null){
		if(is_null($value)){
			$this->title = null;
			return 0;
		}

		// $this->title = unpack_object($value);
		$this->title = $value;
	}

	public function setHelp($value=NULL){
		if(is_null($value)) {
			$this->help = new CHelp();
		}
		else if(is_object($value) && zbx_strtolower(get_class($value)) == 'chelp') {
			$this->help = $value;
		}
		else if(is_string($value)) {
			$this->help = new CHelp($value);
			if($this->getName()==NULL)
				$this->setName($value);
		}
		else {
			return $this->error('Incorrect value for setHelp ['.$value.']');
		}

	return 0;
	}

	public function addVar($name, $value){
		$this->addItemToTopRow(new CVar($name, $value));
	}

	public function addItemToTopRow($value){
		array_push($this->top_items, $value);
	}

	public function addRow($item1, $item2=NULL, $class=NULL){
		if(is_object($item1) && zbx_strtolower(get_class($item1)) == 'crow'){
		}
		else if(is_object($item1) && zbx_strtolower(get_class($item1)) == 'ctable'){
			$td = new CCol($item1,'form_row_c');
			$td->setColSpan(2);

			$item1 = new CRow($td);
		}
		else{
			$tmp = $item1;
			if(is_string($item1)){
				$item1 = nbsp($item1);
			}

			if(empty($item1)) $item1 = SPACE;
			if(empty($item2)) $item2 = SPACE;

			$item1 = new CRow(
							array(
								new CCol($item1,'form_row_l'),
								new CCol($item2,'form_row_r')
							),
							$class);
		}

		array_push($this->center_items, $item1);

	return $item1;
	}

	public function addSpanRow($value, $class=NULL){
		if(is_string($value))
			$item1=nbsp($value);

		if(is_null($value)) $value = SPACE;
		if(is_null($class)) $class = 'form_row_c';

		$col = new CCol($value,$class);
			$col->setColSpan(2);
		array_push($this->center_items,new CRow($col));
	}


	public function addItemToBottomRow($value){
		$this->bottom_items->addItem($value);
	}

	public function setTableClass($class){
		if(is_string($class)){
			$this->tableclass = $class;
		}
	}

	public function bodyToString(){
		parent::bodyToString();

		$tbl = new CTable(NULL,$this->tableclass);

		foreach($this->top_items as $item)	$tbl->addItem($item);

		//$tbl->setOddRowClass('form_odd_row');
		//$tbl->setEvenRowClass('form_even_row');

		$tbl->setCellSpacing(0);
		$tbl->setCellPadding(1);

		$tbl->setAlign($this->align);
// add first row
		if(!is_null($this->title)){
			$col = new CCol(NULL,'form_row_first');
			$col->setColSpan(2);

			if(isset($this->help)){
				$col->addItem($this->help);
			}

			if(isset($this->title)){
				$col->addItem($this->title);
			}

			$tbl->setHeader($col);
		}

// add last row
		$tbl->setFooter($this->bottom_items);
// add center rows
		foreach($this->center_items as $item){
			$tbl->addRow($item);
		}

	return $tbl->toString();
	}
}
?>
