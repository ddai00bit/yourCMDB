<?php
/********************************************************************
* This file is part of yourCMDB.
*
* Copyright 2013-2016 Michael Batz
*
*
* yourCMDB is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* yourCMDB is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with yourCMDB.  If not, see <http://www.gnu.org/licenses/>.
*
*********************************************************************/
namespace yourCMDB\exporter;

/**
* Export API - source for an export task
* @author Michael Batz <michael@yourcmdb.org>
*/
class ExportSource
{
	//type of objects for export
	private $objecttype;

	//status of objects
	private $status;

	private $fieldname;

	private $fieldvalue;

	function __construct($objecttype, $status, $fieldname=null, $fieldvalue=null)
	{
		$this->objecttype = $objecttype;
		$this->status = $status;
		$this->fieldname = $fieldname;
		$this->fieldvalue = $fieldvalue;
	}

	public function getObjectType()
	{
		return $this->objecttype;
	}

	public function getStatus()
	{
		return $this->status;
	}

	public function getFieldname()
	{
		return $this->fieldname;
	}

	public function getFieldvalue()
	{
		return $this->fieldvalue;
	}
}
?>
