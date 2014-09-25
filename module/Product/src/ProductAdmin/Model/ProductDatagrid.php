<?php 

/**
 * Copyright (c) 2014 Shine Software.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * * Redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in
 * the documentation and/or other materials provided with the
 * distribution.
 *
 * * Neither the names of the copyright holders nor the names of the
 * contributors may be used to endorse or promote products derived
 * from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package Product
 * @subpackage Model
 * @author Michelangelo Turillo <mturillo@shinesoftware.com>
 * @copyright 2014 Michelangelo Turillo.
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link http://shinesoftware.com
 * @version @@PACKAGE_VERSION@@
 */

namespace ProductAdmin\Model;

use ZfcDatagrid;
use ZfcDatagrid\Column;
use ZfcDatagrid\Column\Type;
use ZfcDatagrid\Column\Style;
use ZfcDatagrid\Column\Formatter;
use ZfcDatagrid\Filter;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\TableGateway;

class ProductDatagrid {
	
	/**
	 *
	 * @var \ZfcDatagrid\Datagrid
	 */
	protected $grid;
	
	/**
	 *
	 * @var \Zend\Db\Adapter\Adapter
	 */
	protected $adapter;
	
	/**
	 *
	 * @var \Product\Service\ProductAttributeService
	 */
	protected $attributes;
	/**
	 *
	 * @var \Zend\Db\TableGateway\TableGatewaye
	 */
	protected $tableGateway;
	
	/**
	 *
	 * @var SettingsService
	 */
	protected $settings;
	
	/**
	 * Datagrid Constructor
	 * 
	 * @param \Zend\Db\Adapter\Adapter $dbAdapter
	 * @param \ZfcDatagrid\Datagrid $datagrid
	 * @param \Base\Service\SettingsServiceInterface $settings
	 * @param \Product\Service\ProductAttributeService $attributes
	 */
	public function __construct(\Zend\Db\Adapter\Adapter $dbAdapter, 
	                            \ZfcDatagrid\Datagrid $datagrid, 
	                            \Base\Service\SettingsServiceInterface $settings,
	                            TableGateway $productService,
	                            \Product\Service\ProductAttributeService $attributes
	        )
	{
		$this->adapter = $dbAdapter;
		$this->grid = $datagrid;
		$this->settings = $settings;
		$this->attributes = $attributes;
		$this->tableGateway = $productService;
	}
	
	/**
	 *
	 * @return \ZfcDatagrid\Datagrid
	 */
	public function getGrid()
	{
		return $this->grid;
	}
	
	/**
	 * Product list
	 *
	 * @return \ZfcDatagrid\Datagrid
	 */
	public function getDatagrid()
	{
	    $eavProduct = new \Product\Model\EavProduct($this->tableGateway);
	    $records = array();
	    $customAttributes = array();
	   
		$grid = $this->getGrid();
		$grid->setId('productGrid');
		
		$dbAdapter = $this->adapter;
		$select = new Select();
		$select->from(array ('p' => 'product'));

		// execute the query 
		$sql = new \Zend\Db\Sql\Sql($this->adapter);
		$stmt = $sql->prepareStatementForSqlObject($select);
		$results = $stmt->execute();

		// execute the main query
		$records = $this->tableGateway->select($select);

		// load the attributes from the preferences
		$columnsAttributesIdx = $this->settings->getValueByParameter('product', 'attributes');
		
		if(!empty($columnsAttributesIdx)){
		    
		    // get from the database the custom attributes set as product preferences ($columnsAttributesIdx)
		    $selectedAttributes = $this->attributes->findbyIdx(json_decode($columnsAttributesIdx, true));
		    $selectedAttributes->buffer();
		    
		    // Get the selected product attribute values ONLY
		    $attributes = $eavProduct->loadAttributes($results, $selectedAttributes);
		    $attributesValues = $attributes->toArray();
		    
		    // loop the selected product attribute records
		    foreach ($attributesValues as $recordId => $attributeValue){
		        
		        // loop the record selected values
    		    foreach ($attributeValue as $id => $value){
    		        
    		        // get the attribute information
    		        $theAttribute = $eavProduct->getAttribute($id);

    		        // create a temporary array of data to merge with the main datagrid array
    		        $customAttributes[$recordId][$theAttribute->getName()] = $value;

    		        // Create a custom column on the grid
    	            $col = new Column\Select($theAttribute->getName());
    	            $col->setLabel(_($theAttribute->getLabel()));
    	            $grid->addColumn($col);    		        

    		    }
		    }
		    
		    // Merge the temporary array with the main datagrid array
		    foreach ($records as $record){
		        $result[] = array_merge($record->getArrayCopy(), $customAttributes[$record->getId()]);
		    }
		    
		}else{
		    $result[] = $record->getArrayCopy();
		}
		
		
		$RecordsPerPage = $this->settings->getValueByParameter('product', 'recordsperpage');
		
		$grid->setDefaultItemsPerPage($RecordsPerPage);
		$grid->setDataSource($result);
		
		$colId = new Column\Select('id');
		$colId->setLabel('Id');
		$colId->setIdentity();
		$grid->addColumn($colId);
		 
		$colType = new Type\DateTime('Y-m-d H:i:s', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
		$colType->setSourceTimezone('Europe/Rome');
		$colType->setOutputTimezone('UTC');
		$colType->setLocale('it_IT');
		
		
		$col = new Column\Select('createdat');
		$col->setType($colType);
		$col->setLabel(_('Created At'));
		$col->setWidth(15);
		$grid->addColumn($col);
		
		// Add actions to the grid
		$showaction = new Column\Action\Button();
		$showaction->setAttribute('href', "/admin/product/edit/" . $showaction->getColumnValuePlaceholder(new Column\Select('id')));
		$showaction->setAttribute('class', 'btn btn-xs btn-success');
		$showaction->setLabel(_('edit'));
		
		$delaction = new Column\Action\Button();
		$delaction->setAttribute('href', '/admin/product/delete/' . $delaction->getRowIdPlaceholder());
		$delaction->setAttribute('onclick', "return confirm('Are you sure?')");
		$delaction->setAttribute('class', 'btn btn-xs btn-danger');
		$delaction->setLabel(_('delete'));
		
		
		
		$col = new Column\Action();
		$col->addAction($showaction);
		$col->addAction($delaction);
		$grid->addColumn($col);
		
		$grid->setToolbarTemplate('');
		
		return $grid;
	}

}

?>