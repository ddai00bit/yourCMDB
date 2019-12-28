<?php
/********************************************************************
* This file is part of yourCMDB.
*
* Copyright 2013-2019 Michael Batz
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
namespace yourCMDB\migrator;

use yourCMDB\config\CmdbConfig;
use yourCMDB\controller\ObjectController;
use \DateTime;

/**
* Migrator - migrates yourCMDB data to DATAGERRY
* @author Michael Batz <michael@yourcmdb.org>
*/
class Migrator
{
	//migrator config
    private $configObjectTypes;

    //DATAGERRY URL
    private $dgUrl;

    //DATAGERRY user
    private $dgUser;

    //DATAGERRY password
    private $dgPassword;

    //DATAGERRY authtoken
    private $dgToken;

    //created categories
    private $dgCategories;

    //created types
    private $dgTypes;

    //definitions of created types
    private $dgTypeDefs;

    //references to set
    private $dgTypeRefsToSet;

    //field type mapping (type yourCMDB -> type DATAGERRY)
    private $fieldTypeMap;

	function __construct()
    {
        //init DATAGERRY connection
        $this->dgUrl = "http://127.0.0.1:4000/rest";
        $this->dgUser = "admin";
        $this->dgPassword = "admin";
        $this->dgToken = "";

        //init temp variables for storing DG information
        $this->dgCategories = array();
        $this->dgTypes = array();
        $this->dgTypeDefs = array();
        $this->dgTypeRefsToSet = array();

        //setup field type mapping
        $this->fieldTypeMap = array(
            "text" => "text",
            "textarea" => "textarea",
            "password" => "password",
            "date" => "date",
            "boolean" => "checkbox",
            "dropdown" => "select",
            "objectref" => "ref"
        );

		//get configuration
		$config = CmdbConfig::create();
		$this->configObjectTypes = $config->getObjectTypeConfig();
	}

    /**
    * executes the migration
    */
    public function startMigration()
    {
        print("Migration yourCMDB -> DATAGERRY...\n\n");
        print("please enter connection data of your DATAGERRY setup\n");
        $this->dgUrl = readline("DG REST URL <e.g. http://127.0.0.1:4000/rest>: ");
        $this->dgUser = readline("DG admin user <e.g. admin>: ");
        $this->dgPassword = readline("DG admin password <e.g. admin>: ");
        
        $result = $this->dgLogin();
        if(!$result)
        {
            print("can not connect to DATAGERRY API");
            return;
        }
        $this->checkRequirements();
        print("run migration\n");
        print(" - create object type catgories...\n");
        $this->createCategories();
        print(" - create object types...\n");
        $this->createTypes();
        print(" - create references in type definitions...\n");
        $this->createTypeRefs();
        print(" - create objects...\n");
        $this->createObjects();
    }

    private function dgLogin()
    {
        $data = array(
            "user_name" => $this->dgUser,
            "password" => $this->dgPassword
        );
        $response = $this->sendData("/auth/login","POST", json_encode($data));
        if($response)
        {
            $response_decoded = json_decode($response);
            $this->dgToken = $response_decoded->token;
            return true;
        }
        return false;
    }

    private function checkRequirements()
    {
        //ToDo
        return true;
    }
    
    private function createCategories()
    {
        $categories = array_keys($this->configObjectTypes->getObjectTypeGroups());
        foreach($categories as $category)
        {
            $data = array(
                "name" => $this->dgGenerateName($category),
                "label" => $category,
                "icon" => "",
                "parent_id" => 0,
                "root" => false
            );
            $response = $this->sendData("/category/", "POST", json_encode($data));
            if($response)
            {
                $this->dgCategories[$category] = intval($response);
            }
        }
    }  

    private function createTypes()
    {
        $groups = $this->configObjectTypes->getObjectTypeGroups();
        $types = $this->configObjectTypes->getAllTypes();
        foreach($types as $type)
        {
            //check group of object type
            $category = "";
            foreach(array_keys($groups) as $group)
            {
                if(in_array($type, $groups[$group]))
                {
                    $category = $group;
                }
            }
            $categoryId = 0;
            if(isset($this->dgCategories[$category]))
            {
                $categoryId = $this->dgCategories[$category];
            } 

            //get object type data
            $fields = $this->configObjectTypes->getFields($type);
            $fieldGroups = $this->configObjectTypes->getFieldGroups($type);


            //generate json structure for DATAGERRY
            $data = array();
            $data["name"] = $type;
            $data["label"] = $type;
            $data["description"] = "";
            $data["version"] = "1.0.0";
            $data["status"] = null;
            $data["active"] = true;
            $data["clean_db"] = true;
            $data["access"] = array(
                "groups" => "",
                "users" => ""
            );
            //ToDo: set correct author ID
            $data["author_id"] = 1;
            $data["render_meta"] = array();
            $data["render_meta"]["icon"] = "fa fa-cube";

            //create sections
            $data["render_meta"]["sections"] = array();
            foreach($fieldGroups as $fieldGroup)
            {
                $sectionData = array();
                $sectionData["type"] = "section";
                $sectionData["name"] = $fieldGroup;
                $sectionData["label"] = $fieldGroup;
                $sectionData["fields"] = array_keys($this->configObjectTypes->getFieldGroupFields($type, $fieldGroup));
                $data["render_meta"]["sections"][] = $sectionData;
            }

            //create summary fields
            $data["render_meta"]["summary"] = array();
            $data["render_meta"]["summary"]["fields"] = array_keys($this->configObjectTypes->getSummaryFields($type));


            //ToDo: create external links
            $data["render_meta"]["external"] = array();


            //get object fields
            $data["fields"] = array();
            foreach(array_keys($fields) as $field)
            {
                $fieldName = $field;

                //handle field types
                $fieldType = $fields[$field];
                $fieldTypeOptions = null;
                $fieldTypeParts = preg_split("/-/", $fieldType);
                $fieldType = $fieldTypeParts[0];
                $fieldTypeMapped = "text";
                if(isset($this->fieldTypeMap[$fieldType]))
                {
                    $fieldTypeMapped = $this->fieldTypeMap[$fieldType];
                }
                $fieldLabel = $this->configObjectTypes->getFieldLabel($type, $field);

                //handle field type dropdown/select
                if($fieldType == "dropdown" && isset($fieldTypeParts[1]))
                {
                    $options = preg_split("/,/", $fieldTypeParts[1]);
                    $fieldTypeOptions = array();
                    foreach($options as $option)
                    {
                        $fieldTypeOptions[] = array(
                            "name" => $option,
                            "label" => $option
                        );
                    }

                }

                //handle field type objectref
                if($fieldType == "objectref" && isset($fieldTypeParts[1]))
                {
                    if(!isset($this->dgTypeRefsToSet[$type]))
                    {
                        $this->dgTypeRefsToSet[$type] = array();
                    }
                    $this->dgTypeRefsToSet[$type][$fieldName] = $fieldTypeParts[1];
                }

                //create field data
                $fieldData = array(
                    "type" => $fieldTypeMapped,
                    "name" => $field,
                    "label" => $fieldLabel
                );
                if($fieldTypeOptions)
                {
                    $fieldData["options"] = $fieldTypeOptions;
                }
                $data["fields"][] = $fieldData;
            }

            //set category
            $data["category_id"] = $categoryId;

            //set public ID
            $data["public_id"] = null;

            $this->dgTypeDefs[$type] = $data;
            $response = $this->sendData("/type/", "POST", json_encode($data));
            if($response)
            {
                $this->dgTypes[$type] = intval($response);
            }
        }
    }

    private function createTypeRefs()
    {
        foreach(array_keys($this->dgTypeRefsToSet) as $type)
        {
            //get type
            $typeId = $this->dgTypes[$type];
            $data = json_decode($this->getData("/type/$type"));
            foreach(array_keys($this->dgTypeRefsToSet[$type]) as $fieldname)
            {
                foreach($data->fields as $fieldDef)
                {
                    if($fieldDef->name == $fieldname)
                    {
                        $refTypeName = $this->dgTypeRefsToSet[$type][$fieldname];
                        $refTypeId = intval($this->dgTypes[$refTypeName]);
                        $fieldDef->ref_types = $refTypeId;
                    }
                }
            }
            $response = $this->sendData("/type/", "PUT", json_encode($data));
        }
    }

    private function createObjects()
    {
        $objectController = ObjectController::create();
        foreach($objectController->getAllObjectIds(null, 0, "migrator") as $objectId)
        {
            $cmdbObject = $objectController->getObject($objectId, "migrator");
            $cmdbObjectType = $cmdbObject->getType();
            $cmdbObjectState = $cmdbObject->getStatus();
            $cmdbObjectActive = true;
            if($cmdbObjectState == "N")
            {
                $cmdbObjectActive = false;
            }
            $data = array();
            $data["type_id"] = $this->dgTypes[$cmdbObjectType];
            $data["status"] = true;
            $data["version"] = "1.0.0";
            $data["author_id"] = 1;
            $data["active"] = $cmdbObjectActive;
            $data["fields"] = array();
            foreach($cmdbObject->getFields()->getKeys() as $fieldname)
            {
                $fieldvalue = $cmdbObject->getFieldvalue($fieldname);
                //ToDo: handle datatypes in a correct way
                $fielddata = array(
                    "name" => $fieldname,
                    "value" => $fieldvalue
                );
                $data["fields"][] = $fielddata;
            }
            //ToDo: public_id will not be set correctly
            $data["public_id"] = intval($objectId);

            $response = $this->sendData("/object/", "POST", json_encode($data));
        }
    }

    private function dgGenerateName($label)
    {
        $output = strtolower($label);
        $output = str_replace(" ", "-", $output);

        return $output;
    }

    private function getData($resource)
    {
        $curl = curl_init();
        $curlHeader = array();
        $curlHeader[] = "Content-Type: application/json";
        if($this->dgToken)
        {
            $curlHeader[] = "Authorization: $this->dgToken";
        }
        $curlOptions = array(
            CURLOPT_HTTPHEADER  => $curlHeader,
            CURLOPT_URL => "{$this->dgUrl}{$resource}",
            CURLOPT_RETURNTRANSFER  => true
        );
        curl_setopt_array($curl, $curlOptions);
        $curlResult = curl_exec($curl);
        if($curlResult == false)
        {
            $curlError = curl_error($curl);
            print("error connecting to DATAGERRY: $curlError");
            return false;
        }
        $curlHttpResponse = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if($curlHttpResponse > 399)
        {
            print("error connecting to DATAGERRY: HTTP/$curlHttpResponse");
            return false;
        }
        curl_close($curl);
        return $curlResult;
    }

    private function sendData($resource, $method, $data)
    {
        $curl = curl_init();
        $curlHeader = array();
        $curlHeader[] = "Content-Type: application/json";
        if($this->dgToken)
        {
            $curlHeader[] = "Authorization: $this->dgToken";
        }
        $curlOptions = array(
            CURLOPT_CUSTOMREQUEST => "$method",
            CURLOPT_POSTFIELDS => "$data",
            CURLOPT_HTTPHEADER  => $curlHeader,
            CURLOPT_URL => "{$this->dgUrl}{$resource}",
            CURLOPT_RETURNTRANSFER  => true
        );
        curl_setopt_array($curl, $curlOptions);
        $curlResult = curl_exec($curl);
        if($curlResult == false)
        {
            $curlError = curl_error($curl);
            print("error connecting to DATAGERRY: $curlError");
            return false;
        }
        $curlHttpResponse = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if($curlHttpResponse > 399)
        {
            print("error connecting to DATAGERRY: HTTP/$curlHttpResponse");
            return false;
        }
        curl_close($curl);
        return $curlResult;
    }

}
?>
