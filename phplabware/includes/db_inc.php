<?php
  
// db_inc.php -  functions interfacing with the database
// db_inc.php - author: Nico Stuurman <nicost@soureforge.net>
  /***************************************************************************
  * This script contain functions interfacing with the database              *
  * Although they are geared towards phplabware, they might be more generally*
  *   useful.                                                                *
  *                                                                          *
  *                                                                          *
  * Copyright (c) 2001 by Nico Stuurman                                      *
  * ------------------------------------------------------------------------ *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/       

class tableinfo {
   var $short;
   var $realname;
   var $label;
   var $desname;
   var $queryname;
   var $pagename;
   var $id;

   // hackers way of overloading
   function tableinfo ($db,$realname=false,$id=false) {
      global $HTTP_GET_VARS;

      if ($id)
         $r=$db->Execute("SELECT id,shortname,tablename,real_tablename,table_desc_name,label FROM tableoftables WHERE id='$id'");
      elseif ($realname)
         $r=$db->Execute("SELECT id,shortname,tablename,real_tablename,table_desc_name,label FROM tableoftables WHERE real_tablename='$realname'");
      else
         $r=$db->Execute("SELECT id,shortname,tablename,real_tablename,table_desc_name,label FROM tableoftables WHERE tablename='$HTTP_GET_VARS[tablename]'");
      $this->id=$r->fields['id'];
      $this->short=$r->fields['shortname'];
      $this->realname=$r->fields['real_tablename'];
      $this->name=$r->fields['tablename'];
      $this->label=$r->fields['label'];
      $this->desname=$r->fields['table_desc_name'];
      $this->fields=comma_array_SQL($db,$this->desname,columnname);
   }
}


if (!function_exists('array_key_exists')) {
   function array_key_exists($find,$array){
      while ((list($key,$value)=each($array)) && !$test) {
         if ($key==$find)
            $test=true;
      }
      return $test;
   }
}

//////////////////////////////////////////////////////
////
// !Analogue of adodb GetMenu2, but display referenced values (using getvalues)
function GetValuesMenu ($db,$selectname,$selected,$tablename,$columnname,$whereclause=false,$jscript=false) {
   global $max_menu_length;

   $tableinfo=new tableinfo($db,$tablename);
   $r=$db->Execute("SELECT id FROM $tablename $whereclause");
   while ($r && !$r->EOF) {
      $values[]=getvalues($db,$tableinfo,$columnname,'id',$r->fields[0]);
      $r->MoveNext();
   }
   $text="<select name='$selectname' $jscript>\n";
   $text.="<option value=''></option>\n";
   foreach ($values as $value) {
      $v["{$value[0]['recordid']}"]=$value[0]['text'];
   }
   asort($v);
   while (list ($key,$val)=each($v)) {
      if ($key==$selected)
         $selecttext='selected';
      else
         $selecttext=false;
      $text.="<option $selecttext value='$key'>$val</option>\n";
   }
   $text.="</select>\n";
   return $text;
}


//////////////////////////////////////////////////////
////
// !SQL where search that returns a comma delimited string
function comma_array_SQL_where($db,$tablein,$column,$searchfield,$searchval)
{
   $rs = $db->Execute("select $column from $tablein where $searchfield='$searchval' order by sortkey");

   if ($rs) {
      while (!$rs->EOF) {
         $tempa[]=$rs->fields[0];
         $rs->MoveNext();
      }
   }
   return join(",",$tempa);
}

/////////////////////////////////////////////////////
////
// !SQL search (entrire column) that returns a comma delimited string
function comma_array_SQL($db,$tablein,$column,$where=false) 
{
   $rs = $db->Execute("select $column from $tablein $where order by sortkey");
   if ($rs) {
      while (!$rs->EOF) {
         $tempa[]=$rs->fields[0];
         $rs->MoveNext();
      }
   }
   if ($tempa)
      return join(",",$tempa);
}

////
// !Helper function for sortstring
// returns a string with format tablename.columnname, reflecting the data source for the specified column
function origin_column ($db,$tableinfo,$key){
   $r=$db->Execute("SELECT associated_table,associated_column FROM $tableinfo->desname WHERE columnname='$key'");
   if (!$r->fields[0])
      return $tableinfo->realname.'.'.$key;
   else {
      $rtable=$db->Execute("SELECT real_tablename,table_desc_name,id FROM tableoftables WHERE id={$r->fields[0]}");
      $rtkey=$db->Execute("SELECT columnname,datatype,type FROM {$rtable->fields[1]} WHERE id='{$r->fields[1]}'");
       //  $tablecolumnvalues[$rtdesc->fields[0]]=$columnvalues[$column];
      $asstableinfo=new tableinfo($db,false,$rtable->fields[2]);
      return origin_column($db,$asstableinfo,$rtkey->fields[0]);
   }
}


////
// !Update sortdirarray and returns formatted sortdirstring
function sortstring($db,$tableinfo,&$sortdirarray,$sortup,$sortdown) {
   if ($sortup && $sortup<>" ") {
      if (is_array($sortdirarray) && array_key_exists($sortup,$sortdirarray)) {
         if ($sortdirarray[$sortup]=='asc')
            unset($sortdirarray[$sortup]);
         else
            $sortdirarray[$sortup]='asc';
      }
      elseif (!is_array($sortdirarray) || !array_key_exists($sortup,$sortdirarray))
         $sortdirarray[$sortup]='asc';
   } 
   if ($sortdown && $sortdown <>" ") {
      if (is_array($sortdirarray) && array_key_exists($sortdown,$sortdirarray)) {
         if ($sortdirarray[$sortdown]=='desc')
            unset($sortdirarray[$sortdown]);
         else
            $sortdirarray[$sortdown]="desc";
      }
      elseif (!is_array($sortdirarray) || !array_key_exists($sortdown,$sortdirarray))
         $sortdirarray[$sortdown]="desc";
   }

   if ($sortdirarray) {
      foreach($sortdirarray as $key => $value) {
         if ($sortstring)
            $sortstring .= ", ";
         // if the column '$key' is of type table, we'llhave to dig deeper to find the table.column description of the underlying data
         $table_column=origin_column ($db,$tableinfo,$key);
         $sortstring .= "$table_column $value";
      }
   }
   return $sortstring;
}

////
// !Displays header of 'general' table
function tableheader ($sortdirarray,$nowfield) {
//print_r($nowfield);
   $columnname=$nowfield['name'];
   $columnlabel=$nowfield['label'];
   echo "<th><table align='center' width='100%'><td align='left'>";
   // the sort buttons don't work for associated tables, so do not show them for associated tables
//   if ($nowfield['datatype']!='table') {
      if ($sortdirarray[$columnname]=='asc')
        $sortupicon='icons/sortup_active.png';
      else
         $sortupicon='icons/sortup.png';
     echo "<input type='image' name='sortup_$columnname' value='$columnlabel' src='$sortupicon' alt='Sort Up'>";
//   }
   echo "</td><th align='center'>$columnlabel</th><td align='right'>";
//   if ($nowfield['datatype']!='table') {
      if ($sortdirarray[$columnname]=='desc')
         $sortdownicon='icons/sortdown_active.png';
      else
         $sortdownicon='icons/sortdown.png';
      echo "<input type='image' name='sortdown_$columnname' value='$columnlabel' src='$sortdownicon' alt='Sort Down'>";
//   }
   echo "</td></tr></table></th>\n";
}

////
// !Inserts $fields with $fieldvalues into $table
// Returns the id of inserted record on succes, false otherwise.
// $fields is a comma separated list with all column names
// Fieldvalues must be an associative array containing all the $fields to be added.
// Fields named 'date' are automatically filled with a Unix timestamp
function add ($db,$table,$fields,$fieldvalues,$USER,$tableid) {
   if (!may_write($db,$tableid,false,$USER)) {
      echo "<h3>You are not allowed to do this.<br>";
      return false;
   }
   // test if upload already took place through variable magic
   if ($fieldvalues["magic"])
      if ($test=get_cell($db,$table,"id","magic",$fieldvalues["magic"])) {
         echo "<h3 align='center'>That record was already uploaded.</h3>\n";
         return -1;
      }
   include('includes/defines_inc.php');
   if (!($USER['permissions'] & $WRITE) )
      return false;
   // generate the new ID
   $id=$db->GenID($table.'_id_seq');
   if ($id) {
      $columns='id';
      $values="$id";
      $column=strtok($fields,',');
      while ($column) {
         if (!($column=='id')) {
            $columns.=",$column";
            // set userid
            if ($column=='ownerid') {
               // a plugin can overwrite the ownerid, this can be handy when you want to assign all entries in a table to a particular user
               if (function_exists('plugin_setowner'))
                  $fieldvalues['ownerid']=plugin_setowner($db);
               else
                  $fieldvalues['ownerid']=$USER['id'];
            }
            // set default access rights, 
            elseif (in_array($column, array('gr','gw','er','ew')))
               $fieldvalues[$column]=get_access($fieldvalues,$column);

            // set timestamp
            if ($column=='date') {
               $date=(time());
               $values.=",$date";
            }
            else {
               if (isset($fieldvalues[$column]) && 
                        !(is_array($fieldvalues[$column])) && 
                        strlen($fieldvalues[$column])>0)
	          $values.=",'$fieldvalues[$column]'";
               else
                  $values.=",NULL";
            }
         }
	 $column=strtok(",");
      }
      // add trusted users entered on the form
      if (is_array($fieldvalues["trust_read"]))
         foreach ($fieldvalues["trust_read"] as $userid)
            $db->Execute("INSERT INTO trust VALUES ('$tableid','$id','$userid','r')");
      if (is_array($fieldvalues["trust_write"]))
         foreach ($fieldvalues["trust_write"] as $userid)
            $db->Execute("INSERT INTO trust VALUES ('$tableid','$id','$userid','w')");
      $query="INSERT INTO $table ($columns) VALUES ($values)";
      if ($db->Execute($query))
         return $id;
      else {
         echo "<h3>Database error.  Contact your system administrator.</h3>\n";
      }
   }
}

////
// !For multiple choice pulldowns.  
// Deletes entries in key_table for a give record,and then re-inserts the ones present in the array
function update_mpulldown ($db,$key_table,$recordid,$valueArray) {
   $db->Execute ("DELETE FROM $key_table WHERE recordid=$recordid");
   if (is_array($valueArray)) {
      while (list($key,$value)=each($valueArray)) {
         $db->Execute ("INSERT INTO $key_table VALUES ($recordid,$value)");
      }
   }
}


////
// !Modifies $fields in $table with values $fieldvalues where id=$id
// Returns true on succes, false on failure
// Fieldvalues must be an associative array containing all the $fields to be added.
// If a field is not present in $fieldvalues, it will be set to NULL.  
// The entry 'id' in $fields will be ignored.
// Fields lastmodby and lastmoddate will be automatically set
function modify ($db,$table,$fields,$fieldvalues,$id,$USER,$tableid) {
   if (!may_write($db,$tableid,$id,$USER))
      return false;
   // delete all entries in trust related to this record first
   $db->Execute("DELETE FROM trust WHERE tableid='$tableid' and recordid='$id'");
   // then add back trusted users entered on the form
   if (is_array($fieldvalues['trust_read']))
      foreach ($fieldvalues['trust_read'] as $userid)
         $db->Execute("INSERT INTO trust VALUES ('$tableid','$id','$userid','r')");
   if (is_array($fieldvalues['trust_write']))
      foreach ($fieldvalues['trust_write'] as $userid)
         $db->Execute("INSERT INTO trust VALUES ('$tableid','$id','$userid','w')");

   $query="UPDATE $table SET ";
   $column=strtok($fields,',');
   while ($column) {
      if (! ($column=='id' || $column=='date' || $column=='ownerid' || is_array($fieldvalues[$column]) ) ) {
         $test=true;
         //if ($column=="access")
         //   $fieldvalues["access"]=get_access($fieldvalues);
         if (in_array($column, array('gr','gw','er','ew')))
            $fieldvalues[$column]=get_access($fieldvalues,$column);
         if ($column=='lastmodby')
            $fieldvalues['lastmodby']=$USER['id'];
         if ($column=='lastmoddate')
            $fieldvalues['lastmoddate']=time();
         if (isset($fieldvalues[$column]) && (strlen($fieldvalues[$column])>0))
            $query.="$column='$fieldvalues[$column]',";
         else
            $query.="$column=NULL,";
      }
      $column=strtok(',');
   }
   $query[strrpos($query,',')]=' ';

   if ($test) {
      $query.=" WHERE id='$id'";
      $result=$db->Execute($query);
      if ($result)
         return true;
   }
}


////
// !Deletes the entry with id=$id
// Returns true on succes, false on failure
// Checks whether the delete is allowed
// This is very generic, it is likely that you will need to do more cleanup
function delete ($db, $tableid, $id, $USER, $filesonly=false) {

   $table=get_cell($db,"tableoftables","real_tablename","id",$tableid);
   if (!may_write($db,$tableid,$id,$USER))
      return false;

   // check for associated files
   $r=$db->Execute("SELECT id FROM files 
                    WHERE tablesfk=$tableid AND ftableid=$id");
   while ($r && !$r->EOF) {
      delete_file ($db,$r->fields("id"),$USER); 
      $r->MoveNext();
   }
   // and now delete for real
   if (!$filesonly) {
      if ($db->Execute("DELETE FROM $table WHERE id=$id"))
         return true;
      else
         return false;
   }
   else
      return true;
}

////
// !Generates thumbnails and extracts information from 2-D image files
function process_image($db,$fileid,$bigsize) 
{
   global $USER, $system_settings;
   
   if (!$fileid)
      return false;
   $imagefile=file_path ($db,$fileid);
   $bigthumb=$system_settings['thumbnaildir']."/big/$fileid.jpg";
   $smallthumb=$system_settings['thumbnaildir']."/small/$fileid.jpg";
   $smallsize=$system_settings['smallthumbsize'];
   $convert=$system_settings['convert'];

   // make big thumbnail and get image info
   $command = "$convert -verbose -sample ".$bigsize."x".$bigsize." $action \"$imagefile\" jpg:$bigthumb";
   exec($command, $result_str_arr, $status);

   // make small thumbnail
   $command = "$convert -sample ".$smallsize."x".$smallsize." $action \"$imagefile\" jpg:$smallthumb";
   `$command`;

   // get size, mime, and type from image file.  
   // Try exif function, if that fails use convert 
   $sizearray=getimagesize($imagefile);
   $width=$sizearray[0];
   if ($width) {
      $height=$sizearray[1];
      $mime=$sizearray['mime'];
      switch ($sizearray[2]) {
         case 1: $filename_extension='GIF'; break;
         case 2: $filename_extension='JPG'; break;
         case 3: $filename_extension='PNG'; break;
         case 4: $filename_extension='SWF'; break;
         case 5: $filename_extension='PSD'; break;
         case 6: $filename_extension='BMP'; break;
         case 7: $filename_extension='TIFF'; break;
         case 8: $filename_extension='TIFF'; break;
         case 9: $filename_extension='JPC'; break;
         case 10: $filename_extension='JP2'; break;
         case 11: $filename_extension='JPX'; break;
         case 12: $filename_extension='JB2'; break;
         case 13: $filename_extension='SWC'; break;
         case 14: $filename_extension='IFF'; break;
      }
   }
   else {
      // get filetype and size in pixels from convert. Take first token after filesize.  Don't know if it always works.
      // appparently convert yields:
      // original filename, dimensions, Class, (optional) colordepht, size (in kb), filetype, ???, ???
      $convertresult[0] = strtok ($result_str_arr[0]," ");
      $test = false;
      for ($i=1; $i<7; $i++) {
         $convertresult[$i] = strtok (" ");
         if ($i == 1) 
            $pixels = $convertresult[$i];
         if ($test) {
            $filename_extension = $convertresult[$i];
            $test = false; 
         }
         if (substr ($convertresult[$i], -2) == 'kb')
            $test = true;
      }
      // extract pixel dimensions, this fails when there are spaces in the filename
      $width = (int) strtok ($pixels, 'x+= >');
      $height = (int) strtok ('x+= >');
   }

   if($mime) 
      $db->Execute("UPDATE files SET mime='$mime' WHERE id=$fileid");
   $r=$db->Execute("SELECT id FROM images WHERE id=$fileid");

   if (!$r->fields["id"]) 
      $query="INSERT INTO images (id,x_size,y_size,xbt_size,ybt_size,xst_size,yst_size,type) VALUES ('$fileid', '$width', '$height', '$bigsize', '$bigsize', '$smallsize', '$smallsize', '$filename_extension')";
   else 
      $query="UPDATE images SET x_size='$width',y_size='$height',xbt_size='$bigsize',ybt_size='$bigsize',xst_size='$smallsize',yst_size='$smallsize',type='$filename_extension' WHERE id=$fileid";
   $db->Execute($query);
   
}

////
// !Upload files and enters then into table files
// files should be called file[] in HTTP_POST_FILES
// filetitle in HTTP_POST_VARS will be inserted in the title field of table files
// returns id of last uploaded file upon succes, false otherwise
function upload_files ($db,$tableid,$id,$columnid,$columnname,$USER,$system_settings)
{
   global $HTTP_POST_FILES,$HTTP_POST_VARS,$system_settings;

   $table=get_cell($db,'tableoftables','tablename','id',$tableid);
   $real_tablename=get_cell($db,'tableoftables','real_tablename','id',$tableid);

   if (!($db && $table && $id)) {
      echo "Error in code: $db, $table, or $id is not defined.<br>";
      return false;
   }
   if (!may_write($db,$tableid,$id,$USER)) {
      echo "You do not have permission to write to table $table.<br>";
      return false;
   }
   if (isset($HTTP_POST_FILES["$columnname"]['name'][0]) && !$filedir=$system_settings['filedir']) {
      echo "<h3><i>Filedir</i> was not set.  The file was not uploaded. Please contact your system administrator</h3>";
      return false;
   }
   for ($i=0;$i<sizeof($HTTP_POST_FILES["$columnname"]['name']);$i++) {
      if (!$fileid=$db->GenID("files_id_seq"))
         return false;
      $originalname=$HTTP_POST_FILES["$columnname"]['name'][$i];
      $mime=$HTTP_POST_FILES["$columnname"]['type'][$i];
      // sometimes mime types are not set properly, let's try to fix those
      if (substr($originalname,-4,4)=='.pdf')
         $mime='application/pdf';
      // work around php bug??  
      $mime=strtok ($mime,";");
      $filestype=substr(strrchr($mime,'/'),1);
      $size=$HTTP_POST_FILES["$columnname"]['size'][$i];
      $title=$HTTP_POST_VARS['filetitle'][$i];
      if (!$title)
         $title='NULL'; 
      else
         $title="'$title'";
      $type=$HTTP_POST_VARS['filetype'][$i];
      // this works asof php 4.02
      if (move_uploaded_file($HTTP_POST_FILES["$columnname"]['tmp_name'][$i],"$filedir/$fileid"."_"."$originalname")) {
         $query="INSERT INTO files (id,filename,mime,size,title,tablesfk,ftableid,ftablecolumnid,type) VALUES ($fileid,'$originalname','$mime','$size',$title,'$tableid',$id,'$columnid','$filestype')";
	 $db->Execute($query);
      }
      else
         $fileid=false;
   }
   return $fileid;
}


////
// !returns an array with id,name,title,size, and hyperlink to all
// files associated with the given record
function get_files ($db,$table,$id,$columnid,$format=1,$thumbtype='small') {
   $tableid=get_cell($db,'tableoftables','id','tablename',$table);
   $r=$db->Execute("SELECT id,filename,title,mime,type,size FROM files WHERE tablesfk=$tableid AND ftableid=$id AND ftablecolumnid='$columnid'");
   if ($r && !$r->EOF) {
      $i=0;
      $sid=SID;
      while (!$r->EOF) {
         $filesid=$files[$i]['id']=$r->fields('id');
         $filesname=$files[$i]['name']=$r->fields('filename');
         $filestitle=$files[$i]['title']=$r->fields('title');
         $mime=$files[$i]['mime']=$r->fields('mime');
         $filestype=$files[$i]['type']=$r->fields('type');
         $filesize=$files[$i]['size']=nice_bytes($r->fields('size'));
         // if this is an image, we'll send the thumbnail
         $rb=$db->Execute("SELECT id FROM images WHERE id='$filesid'");
         if ($rb->fields(0)) {
            $text="<img src=showfile.php?id=$filesid&type=$thumbtype&$sid>";
         } 
	 elseif ($format==1) {
            if (strlen($filestitle) > 0)
               $text=$filestitle;
            else
                $text=$filesname;
         }
	 elseif ($format==2)
	    $text="file_$i";
	 else
	    $text=$filesname;
         //$text.="<br>\n";
         $icon="icons/$filestype.jpg";
         if (@is_readable($icon))
            $text="<img src='$icon'>";
         $files[$i]['link']="<a href='showfile.php?id=$filesid&$sid'>$text</a>\n";
         $r->MoveNext();
         $i++;
      }
   return $files;
   }
}


////
// !Returns path to the file
function file_path ($db,$fileid) {
   global $system_settings;
   $filename=get_cell($db,'files','filename','id',$fileid);
   return $system_settings['filedir']."/$fileid"."_$filename";
}


////
// !Deletes all file associated with this record,column and table
function delete_column_file($db,$tableid,$columnid,$recordid,$USER) {

   $r=$db->Execute("SELECT id FROM files 
                    WHERE tablesfk=$tableid AND ftableid=$recordid AND ftablecolumnid=$columnid");
   while ($r && !$r->EOF) {
      delete_file ($db,$r->fields('id'),$USER); 
      $r->MoveNext();
   }
}


////
// !Deletes file identified with id.
// Checks 'mother table' whether this is allowed
// Also deletes entries in index table for this file
// Returns name of deleted file on succes
function delete_file ($db,$fileid,$USER) {
   global $system_settings;

   $tableid=get_cell($db,'files','tablesfk','id',$fileid);
   $tabledesc=get_cell($db,'tableoftables','table_desc_name','id',$tableid);
   $ftableid=get_cell($db,'files','ftableid','id',$fileid);
   $columnid=get_cell($db,'files','ftablecolumnid','id',$fileid);
   $associated_table=get_cell($db,$tabledesc,'associated_table','id',$columnid);
   $filename=get_cell($db,'files','filename','id',$fileid);
   if (!may_write($db,$tableid,$ftableid,$USER))
      return false;
   @unlink($system_settings['filedir']."/$fileid"."_$filename");   
   // even if unlink fails we should really remove the entry from the database:
   $db->Execute("DELETE FROM files WHERE id=$fileid");
   // if this was an image:
   $db->Execute("DELETE FROM images WHERE id=$fileid");
   // remove indexing of file content
   $db->Execute ("DELETE FROM $associated_table WHERE fileid=$fileid");
   return $filename;
}

////
// !Returns a 2D array with id and full name of all users
// called by show_access
function user_array ($db) {
   $r=$db->Execute("SELECT id,firstname,lastname FROM users ORDER BY lastname");
   while (!$r->EOF){
      $ua[$i]["id"]=$r->fields['id'];
      if ($r->fields['firstname'])
         $ua[$i]['name']=$r->fields['firstname']." ".$r->fields['lastname'];
      else
         $ua[$i]['name']=$r->fields['lastname'];
      $i++;
      $r->MoveNext();
   }
   return $ua;
}
 
////
// !Prints a table with access rights
// input is string as 'rw-rw-rw-'
// names are same as used in get_access
function show_access ($db,$tableid,$id,$USER,$global_settings) {
   global $client;
   $table=get_cell($db,'tableoftables','real_tablename','id',$tableid);
   if ($id) {
      $ra=$db->Execute("SELECT gr,gw,er,ew,ownerid FROM $table WHERE id='$id'");
      if ($ra) {
         $gr=$ra->fields[0];
         $gw=$ra->fields[1];
         $er=$ra->fields[2];
         $ew=$ra->fields[3];
         $ownerid=$ra->fields[4];
      }
      // $access=get_cell($db,$table,"access","id",$id);
      // $ownerid=get_cell($db,$table,"ownerid","id",$id);
      $groupid=get_cell($db,'users','groupid','id',$ownerid);
      $group=get_cell($db,'groups','name','id',$groupid);
      $rur=$db->Execute("SELECT trusteduserid FROM trust WHERE tableid='$tableid' AND recordid='$id' AND rw='r'");
      while (!$rur->EOF) {
         $ur[]=$rur->fields('trusteduserid');
         $rur->MoveNext();
      }
      $ruw=$db->Execute("SELECT trusteduserid FROM trust WHERE tableid='$tableid' AND recordid='$id' AND rw='w'");
      while (!$ruw->EOF) {
         $uw[]=$ruw->fields('trusteduserid');
         $ruw->MoveNext();
      }
   }
   else {
      $access=$global_settings['access'];
      // translate the $access string into our new format
      if ($access{3}=='r')
         $gr=1;
      if ($access{4}=='w')
         $gw=1;
      if ($access{6}=='r')
         $er=1;
      if ($access{7}=='w')
         $ew=1;
      $group=get_cell($db,'groups','name','id',$USER['groupid']);
   }
   $user_array=user_array($db);
   echo "<table border=0>\n";
   echo "<tr><th>Access:</th><th>$group</th><th>Everyone</th><th>and also</th></tr>\n";
   echo "<tr><th>Read</th>\n";
   if ($gr) $sel="checked"; else $sel=false;
   echo "<td><input type='checkbox' $sel name='grr' value='&nbsp;'></td>\n";
   if ($er) $sel="checked"; else $sel=false;
   echo "<td><input type='checkbox' $sel name='evr' value='&nbsp;'></td>\n";
   // multiple select box for trusted users.  Opera does not like 1 as size
   if ($client->browser=="Opera" || $client->browser=="Internet Explorer")
      $size=2;
   else
       $size=2;
   echo "<td>\n<select multiple size='$size' name='trust_read[]'>\n";
   echo "<option>nobody else</option>\n";
   foreach ($user_array as $user) {
     if (@in_array($user["id"],$ur))
         $selected="selected";
      else
         $selected=false;
     echo "<option $selected value=".$user["id"].">".$user["name"]."</option>\n";
   }
   echo "</select></td>\n";
   echo "</tr>\n";
   echo "<tr><th>Write</th>\n";
   if ($gw) $sel="checked"; else $sel=false;
   echo "<td><input type='checkbox' $sel name='grw' value='&nbsp;'></td>\n";
   if ($ew) $sel="checked"; else $sel=false;
   echo "<td><input type='checkbox' $sel name='evw' value='&nbsp;'></td>\n";
   echo "<td>\n<select multiple size='$size' name='trust_write[]'>\n";
   echo "<option>nobody else</option>\n";
   foreach ($user_array as $user) {
     if (@in_array($user["id"],$uw))
         $selected="selected";
      else
         $selected=false;
      echo "<option $selected value=".$user["id"].">".$user["name"]."</option>\n";
   }
   echo "</select></td>\n";
   echo "</tr>\n";
   echo "</table>\n";
}


////
// !Returns a formatted access strings given an associative array
// with 'grr','evr','grw','evw' as keys
function get_access ($fieldvalues,$column) {
   global $system_settings;
   $gr=0; $gw=0; $er=0; $ew=0;

   if (!$fieldvalues) {
      $system_settings["access"];
      // translate the $access string into our new format
      if ($access{3}=='r')
         $gr=1;
      if ($access{4}=='w')
         $gw=1;
      if ($access{6}=='r')
         $er=1;
      if ($access{7}=='w')
         $ew=1;
      return ${$column};
   }
   if ($fieldvalues['grr']) 
      $gr=1;
   if ($fieldvalues['evr']) 
      $er=1;
   if ($fieldvalues['grw']) 
      $gw=1;
   if ($fieldvalues['evw']) 
      $ew=1;

   return ${$column};
}


////
// !Returns an SQL SELECT statement with ids of records the user may see
// Since it uses subqueries it does not work with MySQL
function may_read_SQL_subselect ($db,$table,$tableid,$USER,$clause=false) {
   include_once ('includes/defines_inc.php');
   $query="SELECT id FROM $table ";
   // don't know why, but variables defined in defines_in.php are not know here
   // bug in my php version?
   $SUPER=64;

   if ($USER['permissions'] & $SUPER) {
      if ($clause)
         $query .= "WHERE $clause";
   }
   else {
      $grouplist=$USER['group_list'];
      $userid=$USER['id'];
      $query .= ' WHERE ';
      if ($clause) 
         $query .= " $clause AND ";
      // owner
      $query .= "( (ownerid=$userid) ";
      // group (quote gr='1', otherwise index willnot be used)
      $query .= "OR (CAST( (SELECT groupid FROM users WHERE users.id=$table.ownerid) AS int) IN ($grouplist) AND gr='1') ";
      // world
      $query .= "OR (er='1')";
      // and also
      $query .= "OR id IN (SELECT recordid FROM trust WHERE tableid='$tableid' AND trusteduserid='$userid' AND rw='r')";
      $query .=")";
   }
   return $query;
}

////
// !returns a comma-separated list of quoted values from a SQL search
// helper function for may_read_SQL
function make_SQL_ids ($r,$ids,$field='id') {
   if (!$r || $r->EOF)
      return substr ($ids,0,-1);
   $id=$r->fields[$field];
   $ids .="$id";
   $r->MoveNext();
   $column_count=1;
   while (!$r->EOF) {
      $id=$r->fields[$field];
      if ($id)
         $ids .=",$id";
      $r->MoveNext();
      $column_count+=1;
   }
   return ($ids);
}


////
// !Returns an array with ids of records the user may see in SQL format
// Works with MySQL but not with early postgres 7 versions (current ones should
// work)
function may_read_SQL_JOIN ($db,$table,$USER) {
   include ('includes/defines_inc.php');
   if (!($USER['permissions'] & $SUPER)) {
      $query="SELECT id FROM $table ";
      $usergroup=$USER['groupid'];
      $group_list=$USER['group_list'];
      $userid=$USER['id'];
      $query .= " WHERE ";
      // owner and everyone
      $query .= "( (ownerid=$userid) ";
      $query .= "OR (er='1')";
      $query .=")";
      $r=$db->CacheExecute(2,$query);
      if ($r) {
         $ids=make_SQL_ids($r,$ids);
      }
      // group
      $query="SELECT $table.id FROM $table LEFT JOIN users ON $table.ownerid=users.id WHERE users.groupid IN ($group_list) AND ($table.gr='1')";
      $r=$db->CacheExecute(2,$query);
   }
   else {     // superuser
      $query="SELECT id FROM $table ";
      $r=$db->CacheExecute(2,$query);
   }
   if ($ids)
      $ids.=",";
   if ($r)
      return make_SQL_ids($r,$ids);
}


////
// !Generates an SQL query asking for the records that mey be seen by this users
// Generates a left join for mysql, subselect for postgres
function may_read_SQL ($db,$tableinfo,$USER,$temptable='tempa') {
   global $db_type;
   if ($db_type=='mysql') {
      $list=may_read_SQL_JOIN ($db,$tableinfo->realname,$USER);
      if (!$list)
         $list='-1';
      $result['sql']= " id IN ($list) ";
      $result['numrows']=substr_count($list,',');
   }
   else {
      //return may_read_SQL_subselect ($db,$table,$tableid,$USER);
      $r=$db->Execute(may_read_SQL_subselect ($db,$tableinfo->realname,$tableinfo->id,$USER,false));
      $result['numrows']=$r->NumRows();
      make_temp_table($db,$temptable,$r); 
      $result['sql'] = " ($tableinfo->realname.id = $temptable.uniqueid) ";
   }
   return $result;
}

////
// Generates a temporary table from given recordset
function make_temp_table ($db,$temptable,$r) {
   global $system_settings;
   $rc=$db->Execute("CREATE TEMPORARY TABLE $temptable (
                     uniqueid int UNIQUE NOT NULL)");
   if ($rc) {
      $r->MoveFirst();
      while (!$r->EOF) {
         $string .= $r->fields["id"]."\n";
         $r->MoveNext();
      }
   }
   // INSERT is too slow.  COPY instead from a file.  postgres only!
   $tmpfile=tempnam($system_settings['tmppsql'],'tmptable');
   $fp=fopen($tmpfile,'w');
   fwrite($fp,$string);
   fflush($fp);
   chmod ($tmpfile,0644);
   $rd=$db->Execute ("COPY $temptable FROM '$tmpfile'"); 
   $rc=$db->Execute("ALTER TABLE $temptable ADD PRIMARY KEY (uniqueid)");
   fclose ($fp);
   unlink($tmpfile);
}

////
// !determines whether or not the user may read this record
function may_read ($db,$tableinfo,$id,$USER) {
   $list=may_read_SQL($db,$tableinfo,$USER);
   $query="SELECT id FROM $tableinfo->realname WHERE ".$list['sql'];
   $r=$db->Execute($query);
   if (!$r)
      return false;
   if ($r->EOF)
      return false;
   else
      return true;
}

////
// !checks if this user may write/modify/delete these data
function may_write ($db,$tableid,$id,$USER) {
   include ('includes/defines_inc.php');
   
   $table=get_cell($db,'tableoftables','real_tablename','id',$tableid);
   if ($USER['permissions'] & $SUPER)
      return true;
   if ( ($USER['permissions'] & $WRITE) && (!$id))
      return true;
   $ownerid=get_cell($db,$table,'ownerid','id',$id);
   $ownergroup=get_cell($db,'users','groupid','id',$ownerid);
   if ($USER['permissions'] & $ADMIN) {
      if ($USER['groupid']==$ownergroup)
         return true;
   }
   if ( ($USER['permissions'] & $WRITE) && $id) {
      $userid=$USER['id'];
      // 'user' write access
      if ($r=$db->Execute("SELECT * FROM $table WHERE id=$id AND
            ownerid=$userid")) 
         if (!$r->EOF)
            return true;
      // 'group' write access
      if ($r=$db->Execute("SELECT * FROM $table WHERE id=$id AND gw='1'"))
         if (!$r->EOF && in_array($ownergroup, $USER['group_array']))
            return true;
      // 'world' write access
      if ($r=$db->Execute("SELECT * FROM $table WHERE id=$id AND ew='1'") )
         if (!$r->EOF) 
            return true;
      // 'and also' write access
      if ($r=$db->Execute("SELECT * FROM trust WHERE trusteduserid='$userid'
              AND tableid='$tableid' AND recordid='$id' AND rw='w'"))
         if (!$r->EOF) 
            return true;
   }
}

////
// !returns an comma-separated list of quoted values from a SQL search
// derived from make_SQL_ids but can be called from anywhere 
function make_SQL_csf ($r,$ids,$field="id",&$column_count) {
   if (!$r || $r->EOF)
      return false;
   $r->MoveFirst();
   while (!$id && !$r->EOF) {
      $id=$r->fields[$field];
      $ids .="$id";
      $r->MoveNext();
   }
   $column_count=1;
   unset ($id);
   while (!$r->EOF) {
      $id=$r->fields[$field];
      if ($id) {
         $ids .=",$id";
         $column_count+=1;
      }
      $r->MoveNext();
   }
   return ($ids);
}
////
// !helperfunction for numerictoSQL
function typevalue ($value,$type) {
   if ($type=='int') {
      return (int)$value;
   }
   elseif ($type=='float') {
      return (float)$value;
   }
   return false; 
}

////
// !interprets numerical search terms into an SQL statement
// implements ranges (i.e. 1-6), and lists (1,2,3) and combinations thereof
// < and > can also be used
function numerictoSQL (&$tableinfo,$searchterm,$column,$type,$and) {
   $commalist=explode(',',$searchterm);
   for ($i=0;$i<sizeof($commalist);$i++) {
      $rangelist=explode('-',$commalist[$i]);
      if (sizeof($rangelist)==2) {
         sort($rangelist);
         $value1=typevalue($rangelist[0],$type);
         $value2=typevalue($rangelist[1],$type);
         if ($i>0) {
            $sql.='OR ';
         }
         $sql.="({$tableinfo->realname}.$column>=$value1 AND {$tableinfo->realname}.$column<=$value2) ";
      }
      elseif (sizeof($rangelist)==1) {
         if ($commalist[$i]{0}=='<' || $commalist[$i]{0}=='>') {
            $token=$commalist[$i]{0};
            $commalist[$i]=substr($commalist[$i],1);
         }
         $value=typevalue ($commalist[$i],$type);
         if ($i>0) {
            $sql.='OR ';
         }
         if (!$token)
            $token='=';
         $sql.="({$tableinfo->realname}.$column$token$value) ";
      }
   }
   return "$and ($sql) ";
}



////
// !Converts a (textual) date range into a meaningfull SQL statement
// Start and end are separated with '-'
// dates can be in current local (12/24/03), or textual (today, last month,etcc.)
// Alternatively, a single date can be preceded with a '<' or '>'
// I am not sure how strtotime deals with US versus European standards...

function datetoSQL ($searchterm,$column,$and) {
   $timerange=explode('-',$searchterm);
   if (sizeof($timerange) < 2) {
      // we have only one searchterm
      if ($timerange[0]{0} == '>') {
         $time=strtotime(substr($timerange[0],1));
         if ($time>0)
            $sql .= "($column>=$time) ";
      }
      elseif ($timerange[0]{0} == '<') {
         $time=strtotime(substr($timerange[0],1));
         if ($time>0)
            $sql .= "($column<=$time) ";
      }
      else { // we give a search range of one day
         $searchrange=86400;
         $starttime=strtotime($timerange[0]);
         $endtime=$starttime+$searchrange;
            $sql .= "($column>=$starttime AND $column<=$endtime) ";
      }

   }
   if (sizeof($timerange) == 2) {
      $starttime=strtotime($timerange[0]);
      $endtime=strtotime($timerange[1]);
      if ($starttime > 0 && $endtime > 0)
         $sql .= "($column>=$starttime AND $column<=$endtime) ";
   }
   return "$and ($sql) ";
}


/*
////
// !Helper function for search
// Interprets fields the right way
function searchhelp ($db,$tableinfo,$column,&$columnvalues,$query,$wcappend,$and) {
   if ($column=='ownerid') {
      $query[1]=true;
      $r=$db->Execute("SELECT id FROM ".$tableinfo->realname." WHERE ownerid=$columnvalues[$column]");
      $list=make_SQL_ids($r,false);
      if ($list) 
         $query[0].= "$and id IN ($list) ";
   }
   else {
      $query[1]=true;
      // since all tables now have desc. tables,we can check for int/floats
      // should probably do this more upstream for performance gain
      $rc=$db->Execute("SELECT type,datatype,associated_table,key_table,associated_column,associated_local_key FROM ".$tableinfo->desname." WHERE columnname='$column'");
      if ($rc->fields[1]=='file' && $rc->fields[2]) {
         $rw=$db->Execute("SELECT id FROM words WHERE word LIKE '".strtolower($columnvalues[$column])."%'");
         if ($rw && $rw->fields[0]) {
            $rh=$db->Execute("SELECT recordid FROM ".$rc->fields[2]." WHERE wordid='".$rw->fields[0]."'");
            if ($rh && $rh->fields[0]) {
                while (!$rh->EOF) {
                   $rhtemp[]=$rh->fields[0];
                   $rh->MoveNext();
                }
                $ids=join (',',$rhtemp);
                $query[0].="$and id IN ($ids) ";
             }
	     // if we come up empty handed, the SQL search should too:
	     else $query[0].="$and id=0 ";
         }
	 else $query[0].="$and id=0 ";
      }
      elseif ($rc->fields[1]=='table') {
         $rtableoftables=$db->Execute("SELECT real_tablename,table_desc_name,id FROM tableoftables WHERE id={$rc->fields['associated_table']}");
         $rtdesc=$db->Execute("SELECT columnname,datatype,type FROM {$rtableoftables->fields[1]} WHERE id='{$rc->fields['associated_column']}'");
         $tablecolumnvalues[$rtdesc->fields[0]]=$columnvalues[$column];
         $asstableinfo=new tableinfo($db,false,$rtableoftables->fields[2]);
         $table_where=searchhelp($db,$asstableinfo,$rtdesc->fields[0],&$tablecolumnvalues,false,$wcappend,false);
         $rtable=$db->Execute("SELECT id FROM {$rtableoftables->fields[0]} WHERE {$table_where[0]}");
         if ($rtable && $rtable->fields[0]) {
             while (!$rtable->EOF) {
                $rhtemp[]=$rtable->fields[0];
                $rtable->MoveNext();
             }
             $ids=join (',',$rhtemp);
             if ($rc->fields['associated_local_key']) {
                $rasslk=$db->Execute("SELECT columnname FROM {$tableinfo->desname} WHERE id={$rc->fields['associated_local_key']}");
                $query[0].="$and {$rasslk->fields[0]} IN ($ids) ";
             }
             else
                $query[0].="$and $column IN ($ids) ";
         }
         // no search results so give an impossible clause
         else
            $query[0].="$and $column='-1' ";
      }
      // there are some (old) cases where pulldowns are of type text...
      elseif ($rc->fields[1]=='pulldown') {
         $columnvalues[$column]=(int)$columnvalues[$column];
         if ($columnvalues["$column"]==-1)
            $query[0].="$and ($column='' OR $column IS NULL) ";
         else
            $query[0].="$and $column='$columnvalues[$column]' ";
      }
      elseif ($rc->fields[1]=='mpulldown') {
         // emulate a logical AND between values selected in a mpulldown
         unset ($id_list);
         // keep the code to deal with single selects and multiple selects
         if (is_array($columnvalues)) {
            unset($id_list);
            $j=0;
            // read in values from types tables and arrange in groups
            foreach($columnvalues[$column] as $typeid) {
               $rl=$db->Execute("SELECT recordid FROM {$rc->fields[3]} WHERE typeid=$typeid");
               while ($rl && !$rl->EOF) {
                  $id_list[$j].=$rl->fields[0].',';
                  $rl->MoveNext();
               }
               $id_list[$j]=substr($id_list[$j],0,-1);
               // if nothing is found we'll pass an impossible id value
               if (strlen($id_list[$j]) <1)
                  $id_list[$j]='-1';
               $j++;
            }
         }
         else {  // for 'single' selects
            $rmp=$db->Execute("SELECT recordid FROM {$rc->fields[3]} WHERE typeid='{$columnvalues[$column]}'");
            if ($rmp) {
               $id_list=$rmp->fields[0];
               $rmp->MoveNext();
               while (!$rmp->EOF) {
                  $id_list.=",{$rmp->fields[0]}";
                  $rmp->MoveNext();
               }
            }
         }
         // pass the multiple lists to the main query
         if (is_array($id_list)) {
            foreach ($id_list as $list) {
               if (!$listfound) {
                  $query[0].="$and id IN ($list) ";
                  $listfound=true;
               }
               else
                  $query[0].="AND id IN ($list) ";
            }
            // we should not be able to get here:
            if (!$listfound)
               $query[0].="$and id IN (-1) ";
         }
         elseif ($id_list) // for 'single' selects
            $query[0].="$and id IN ($id_list) ";
         else // nothing found, make sure we do not crash the search statement
            $query[0].="$and id IN (-1) ";
            
      }
      elseif ($rc->fields[1]=='date') {
         $query[0].= datetoSQL($columnvalues[$column],$column,$and);
      }
      elseif (substr($rc->fields[0],0,3)=='int') {
         $query[0].=numerictoSQL ($columnvalues[$column],$column,"int",$and); 
      }
      elseif (substr($rc->fields[0],0,5)=='float') {
         $query[0].=numerictoSQL ($columnvalues[$column],$column,"float",$and); 
      }
      else {
         $columnvalues[$column]=trim($columnvalues[$column]);
         $columnvalue=$columnvalues[$column];
         $columnvalue=str_replace('*','%',$columnvalue);
         if ($wcappend)
            $columnvalue="%$columnvalue%";
         //else
         //   $columnvalue="% $columnvalue %";
         $query[0].="$and UPPER($column) LIKE UPPER('$columnvalue') ";
      }
   }
   return $query;
}

////
// !Returns an SQL search statement
// The whereclause should NOT start with WHERE
// The whereclause should contain the output of may_read_SQL and
// can also be used for sorting
function search ($db,$tableinfo,$fields,&$fieldvalues,$whereclause=false,$wcappend=true) {
   $columnvalues=$fieldvalues;
   $query[0]="SELECT $fields FROM ".$tableinfo->realname." WHERE ";
   $query[1]=$query[2]=false;
   $column=strtok($fields,',');
   while ($column && !$columnvalues[$column])
      $column=strtok (',');
   if ($column && $columnvalues[$column]) {
      $query[1]=true;
      $query=searchhelp ($db,$tableinfo,$column,$columnvalues,$query,$wcappend,false);
   }
   $column=strtok (',');
   while ($column) { 
      if ($column && $columnvalues[$column]) {
         $query=searchhelp ($db,$tableinfo,$column,$columnvalues,$query,$wcappend,"AND");
      }
      $column=strtok (',');
   }
   if ($whereclause)
      if ($query[1])
         $query[0] .= "AND $whereclause";
      else
         $query[0] .= $whereclause;
   if (function_exists('plugin_search'))
      $query[0]=plugin_search($query[0],$columnvalues,$query[1]);
   return $query[0];
}
*/

////
// !Helper function for search
// Interprets fields the right way
function searchhelp ($db,$tableinfo,$column,&$columnvalues,$query,$wcappend,$and) {
   // first get the specs on this column
   $rc=$db->Execute("SELECT type,datatype,associated_table,key_table,associated_column,associated_local_key FROM ".$tableinfo->desname." WHERE columnname='$column'");
   // get the left joins in the sql statement right
   if ($rc->fields[1]=='table') {
      $rtableoftables=$db->Execute("SELECT real_tablename,table_desc_name,id FROM tableoftables WHERE id={$rc->fields['associated_table']}");
      $rtdesc=$db->Execute("SELECT columnname,datatype,type FROM {$rtableoftables->fields[1]} WHERE id='{$rc->fields['associated_column']}'");
      $tablecolumnvalues[$rtdesc->fields[0]]=$columnvalues[$column];
      $asstableinfo=new tableinfo($db,false,$rtableoftables->fields[2]);
      // find the key and the column it relates to
      if ($rc->fields['associated_local_key']) {
         $rasslk=$db->Execute("SELECT columnname,associated_column FROM {$tableinfo->desname} WHERE id={$rc->fields['associated_local_key']}");
         $associated_local_key=$rasslk->fields[0];
         $rtdesc2=$db->Execute("SELECT columnname,datatype,type FROM {$asstableinfo->desname} WHERE id={$rasslk->fields[1]}");
         $foreign_key=$rtdesc2->fields[0];
      }
      else {
         $associated_local_key=$column;
         $foreign_key=$rtdesc->fields[0];
      }
      // check whether we already have this table as a join:
      // postgres does not like the same join twice, this also dictates that we can not use multiple foreign keys in a table
      if (!strstr($query[1],$asstableinfo->realname))
         $query[1].= "LEFT JOIN {$asstableinfo->realname} ON {$tableinfo->realname}.$associated_local_key={$asstableinfo->realname}.$foreign_key ";
      // for nested structure: recursively call searchhelp, this will also yield the real sort or search statement we are inetersted in and will make all nested joints
      $table_where=searchhelp($db,$asstableinfo,$rtdesc->fields[0],&$tablecolumnvalues,false,$wcappend,false);
      $query[1].=$table_where[1];
      if ($table_where[2])
         $query[2].=$and.' '.$table_where[2];
   }
   //consider other columns only when there is a search value.  These will only contribute to the WHERE part, not to the FROM part
   if ($columnvalues[$column]) {
      $query[5]=true;
      if ($column=='ownerid') {
         $query[2].= "$and ownerid={$columnvalues[$column]} ";
      }
      else {
         $query[5]=true;
         // since all tables now have desc. tables,we can check for int/floats
         // should probably do this more upstream for performance gain
         if ($rc->fields[1]=='file' && $rc->fields[2]) {
            $rw=$db->Execute("SELECT id FROM words WHERE word LIKE '".strtolower($columnvalues[$column])."%'");
            if ($rw && $rw->fields[0]) {
               $query[1].="LEFT JOIN {$rc->fields[2]} ON {$tableinfo->realname}.id={$rc->fields[2]}.recordid ";
               $query[2].="{$rc->fields[2]}.wordid='{$rw->fields[0]}' ";
            }
	    else $query[2].="$and id=0 ";
         }
         if ($rc->fields[1]=='table') {
         }
         // there are some (old) cases where pulldowns are of type text...
         elseif ($rc->fields[1]=='pulldown') {
            $columnvalues[$column]=(int)$columnvalues[$column];
            if ($columnvalues["$column"]==-1)
               $query[2].="$and ({$tableinfo->realname}.$column='' OR {$tableinfo->realname}.$column IS NULL) ";
            else
               $query[2].="$and {$tableinfo->realname}.$column='$columnvalues[$column]' ";
         }
         elseif ($rc->fields[1]=='mpulldown') {
            // emulate a logical AND between values selected in a mpulldown
            unset ($id_list);
            // keep the code to deal with single selects and multiple selects
            if (is_array($columnvalues)) {
               unset($id_list);
               $j=0;
               // read in values from types tables and arrange in groups
               foreach($columnvalues[$column] as $typeid) {
                  $rl=$db->Execute("SELECT recordid FROM {$rc->fields[3]} WHERE typeid=$typeid");
                  while ($rl && !$rl->EOF) {
                     $id_list[$j].=$rl->fields[0].',';
                     $rl->MoveNext();
                  }
                  $id_list[$j]=substr($id_list[$j],0,-1);
                  // if nothing is found we'll pass an impossible id value
                  if (strlen($id_list[$j]) <1)
                     $id_list[$j]='-1';
                  $j++;
               }
            }
            else {  // for 'single' selects
               $rmp=$db->Execute("SELECT recordid FROM {$rc->fields[3]} WHERE typeid='{$columnvalues[$column]}'");
               if ($rmp) {
                  $id_list=$rmp->fields[0];
                  $rmp->MoveNext();
                  while (!$rmp->EOF) {
                     $id_list.=",{$rmp->fields[0]}";
                     $rmp->MoveNext();
                  }
              }
            }
            // pass the multiple lists to the main query
            if (is_array($id_list)) {
               foreach ($id_list as $list) {
                  if (!$listfound) {
                     $query[2].="$and {$tableinfo->realname}.id IN ($list) ";
                     $listfound=true;
                  }
                  else
                     $query[2].="AND {$tableinfo->realname}.id IN ($list) ";
               }
               // we should not be able to get here:
               if (!$listfound)
                  $query[2].="$and {$tableinfo->realname}.id IN (-1) ";
            }
            elseif ($id_list) // for 'single' selects
               $query[2].="$and {$tableinfo->realname}.id IN ($id_list) ";
            else // nothing found, make sure we do not crash the search statement
               $query[2].="$and {$tableinfo->realname}.id IN (-1) ";
               
         }
         elseif ($rc->fields[1]=='date') {
            $query[2].= datetoSQL($columnvalues[$column],$column,$and);
         }
         elseif (substr($rc->fields[0],0,3)=='int') {
            $query[2].=numerictoSQL ($tableinfo,$columnvalues[$column],$column,'int',$and); 
         }
         elseif (substr($rc->fields[0],0,5)=='float') {
            $query[2].=numerictoSQL ($tableinfo,$columnvalues[$column],$column,'float',$and); 
         }
         else {
            $columnvalues[$column]=trim($columnvalues[$column]);
            $columnvalue=$columnvalues[$column];
            $columnvalue=str_replace('*','%',$columnvalue);
            if ($wcappend)
               $columnvalue="%$columnvalue%";
            //else
            //   $columnvalue="% $columnvalue %";
            $query[2].="$and UPPER({$tableinfo->realname}.$column) LIKE UPPER('$columnvalue') ";
         }
      }
   }
   return $query;
}

////
// !Returns an SQL search statement
// The whereclause should NOT start with WHERE
// The whereclause should contain the output of may_read_SQL and
// can also be used for sorting
function search ($db,$tableinfo,$fields,&$fieldvalues,$whereclause=false,$wcappend=true) {
//echo "where: $whereclause.<br>";
   $columnvalues=$fieldvalues;
   // change fields into a SQL string that works with multiple tables
   $fieldsarray=explode(',',$fields);
   foreach ($fieldsarray as $field) 
      $fieldsSQLstring.=$tableinfo->realname.".$field AS $field, ";
   $fieldsSQLstring=substr($fieldsSQLstring,0,-2);
   // SELECT part
   $query[0]="SELECT $fieldsSQLstring "; //FROM ".$tableinfo->realname." WHERE ";
   // FROM part
   $query[1]='FROM '.$tableinfo->realname.' ';
   // WHERE part
   $query[2]='WHERE ';
   // flag telling whether WHERE field already contains a statement
   $query[5]=false;
   $column=strtok($fields,',');
   while ($column) { 
      if ($query[5])
         $query=searchhelp ($db,$tableinfo,$column,$columnvalues,$query,$wcappend,"AND");
      else
         $query=searchhelp ($db,$tableinfo,$column,$columnvalues,$query,$wcappend,$and);
      $column=strtok (',');
   }
/*
   while ($column && !$columnvalues[$column])
      $column=strtok (',');
   if ($column && $columnvalues[$column]) {
      $query[5]=true;
      $query=searchhelp ($db,$tableinfo,$column,$columnvalues,$query,$wcappend,false);
   }
   $column=strtok (',');
   while ($column) { 
      if ($column && $columnvalues[$column]) {
         $query=searchhelp ($db,$tableinfo,$column,$columnvalues,$query,$wcappend,"AND");
      }
      $column=strtok (',');
   }
*/
   if ($whereclause)
      if ($query[5])
         $query[2] .= "AND $whereclause";
      else
         $query[2] .= $whereclause;
   if (function_exists('plugin_search'))
      $query[0]=plugin_search($query[0],$columnvalues,$query[1]);
   $result=$query[0].$query[1].$query[2];
   //echo "$result.<br>";
   return $result;
}


////
// ! sets AtFirstPage and AtLastPage
function first_last_page (&$r,&$current_page,$r_p_p,$numrows) {
   // protect against pushing the reload button while at the last page
   if ( (($current_page-1) * $r_p_p) >= $numrows)
      $current_page -=1;
   // if we are still outof range, this must be a new search statement and we can go to page 1
   if ( (($current_page-1) * $r_p_p) >= $numrows)
      $current_page =1;

   if ($current_page < 2)
      $r->AtFirstPage=true;
   else
      $r->AtFirstPage=false;
   if ( ($current_page * $r_p_p) >= $numrows)
      $r->AtLastPage=true;
   else
      $r->AtLastPage=false;
}

////
// !Displays the next and previous buttons
// $r is the result of a $db->Execute query used to display the table with records
// When $paging is true, the records per page field will also be displayed
// $num_p_r holds the (global) records per page variable
function next_previous_buttons($r,$paging=false,$num_p_r=false,$numrows=false,$pagenr=false,$db=false,$tableinfo=false) {
   echo "<table border=0 width=100%>\n<tr width=100%>\n<td align='left'>";
   if (function_exists($r->AtFirstPage))
      $r->AtFirstPage=$r->AtFirstPage();
   if ($r && !$r->AtFirstPage)
      echo "<input type=\"submit\" name=\"previous\" value=\"Previous\"></td>\n";
   else
      if ($paging)
         echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>\n";
      else
         echo "&nbsp;</td>\n";
   if ($db && $tableinfo)
      show_reports($db,$tableinfo);
   if ($paging) {
      if ($numrows>0) {
         echo "<td align='center'>$numrows Records found. ";
         if ($pagenr) {
            $start=($pagenr-1)*$num_p_r+1;
            $end=$pagenr*$num_p_r;
            if ($end > $numrows)
               $end=$numrows;
            echo "Showing $start through $end. ";
         }
         echo "</td>\n";
      }
      else
         echo "<td align='center'>No records found. </td>\n";
      echo "<td align='center'>\n";
      echo "<input type='text' name='num_p_r'value='$num_p_r' size=3>&nbsp;";
      echo "Records per page</td>\n";
   }
   echo "<td align='right'>";

   // add direct links to pages
   if ($pagenr) {
      $startp=$pagenr-5;
      if ($startp<1)
         $startp=1;
      $endp=$startp+9;
      if ($numrows) {
         if ($numrows < ($endp*$num_p_r)) {
            $endp= ceil($numrows/$num_p_r);
         }
      }

      if ($endp > 1) {
         echo "Goto page: ";
         echo "<input type='hidden' name='{$tableinfo->pagename}' value='0'>\n";     
         for ($i=$startp; $i<=$endp; $i++) {
            if ($pagenr==$i)
               echo "<b>$i </b>";
            else
            // try using links with javascript converting it into post variables
               echo "<a href='javascript:document.g_form.{$tableinfo->pagename}.value=\"$i\"; document.g_form.searchj.value=\"Search\"; document.g_form.submit()'>$i </a>";
         }
      }
   }
     
   if (function_exists($r->AtLastPage))
      $r->AtLastPage=$r->AtLastPage();
   if ($r && !$r->AtLastPage)
      echo "<input type=\"submit\" name=\"next\" value=\"Next\"></td>\n";
   else
      if ($paging)
         echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
      else
         echo "&nbsp;";
   echo "</td>\n</tr>\n";
   echo "</table>\n";
}

////
// !Returns the variable $num_p_r holding the # of records per page
// check user settings and POST_VARS
// Write the value back to the user defaults
// When no value is found, default to 10
function paging ($num_p_r,&$USER) {
   global $HTTP_POST_VARS;
   if (!$num_p_r)
      $num_p_r=$USER['settings']['num_p_r'];
   if (isset($HTTP_POST_VARS['num_p_r']))
      $num_p_r=$HTTP_POST_VARS['num_p_r'];
   if (!isset($num_p_r))
     $num_p_r=10;
   $USER['settings']['num_p_r']=$num_p_r;
   return $num_p_r;
}

////
// !Returns current page
// current page is table specific, therefore
// The variable name is formed using the short name for the table
function current_page($curr_page, $sname, $num_p_r, $numrows) {
   global $HTTP_POST_VARS, $HTTP_SESSION_VARS;
   $varname=$sname.'_curr_page';
   ${$varname}=$curr_page;

   if (!isset($$varname))
      ${$varname}=$HTTP_SESSION_VARS[$varname];
   // if the current page is out of bound, we'll reset it to 1
   if (${$varname} > ($numrows/$num_p_r))
      ${$varname}=1;
   // the page number can be set directly or by clicking the next/previous buttons (see function next_previous_buttons)
   if ($HTTP_POST_VARS[$varname]) {
      ${$varname}=$HTTP_POST_VARS[$varname];
   }
   elseif (isset($HTTP_POST_VARS['next'])) {
      ${$varname}+=1;
   }
   elseif (isset($HTTP_POST_VARS['previous'])) {
      $$varname-=1;
   }
   if ($$varname<1)
      $$varname=1;
   $HTTP_SESSION_VARS[$varname]=${$varname}; 
   session_register($varname);
   return ${$varname};
}

////
// !Assembles the search SQL statement and remembers it in HTTP_SESSION_VARS
function make_search_SQL($db,$tableinfo,$fields,$USER,$search,$searchsort,$whereclause=false) {
   global $HTTP_POST_VARS, $HTTP_SESSION_VARS;

   // apparently searchsort can be passed as an empty string.  that is bad
   if (!$searchsort)
      $searchsort=$tableinfo->realname.'.title';
   $fieldvarsname=$tableinfo->short.'_fieldvars';
   global ${$fieldvarsname};
   $queryname=$tableinfo->short.'_query';
   if (!$whereclause)
      $whereclause=may_read_SQL ($db,$tableinfo,$USER);
   if (!$whereclause)
      $whereclause=-1;
   if ($search=='Search') {
      ${$queryname}=search($db,$tableinfo,$fields,$HTTP_POST_VARS," $whereclause ORDER BY $searchsort");
      ${$fieldvarsname}=$HTTP_POST_VARS;
   }
   elseif (session_is_registered ($queryname) && isset($HTTP_SESSION_VARS[$queryname])) {
      ${$queryname}=$HTTP_SESSION_VARS[$queryname];
      ${$fieldvarsname}=$HTTP_SESSION_VARS[$fieldvarsname];
   }
   else {
      ${$queryname} = "SELECT $fields FROM $tableinfo->realname WHERE $whereclause ORDER BY date DESC";
      ${$fieldvarsname}=$HTTP_POST_VARS;
   }
   $HTTP_SESSION_VARS[$queryname]=${$queryname};   
   session_register($queryname);
   if (!${$fieldvarsname})
      ${$fieldvarsname}=$HTTP_POST_VARS;
   $HTTP_SESSION_VARS[$fieldvarsname]=${$fieldvarsname};   
   session_register($fieldvarsname);

   if ($search !='Show All') {
      // globalize HTTP_POST_VARS 
      $column=strtok($fields,',');
      while ($column) {
         global ${$column};
         ${$column}=$HTTP_POST_VARS[$column];
         $column=strtok(',');
      }
      // extract variables from session
      globalize_vars ($fields, ${$fieldvarsname});
   }
//echo "${$queryname}.<br>";
   return ${$queryname};
}


////
// !Checks whether a user has access to a given table
//
function may_see_table($db,$USER,$tableid) {
   include ('includes/defines_inc.php');
   // Sysadmin may see it all
   if ($USER['permissions'] & $SUPER)
      return true;
   $group_list=$USER['group_list'];
   $r=$db->Execute ("SELECT tableid FROM groupxtable_display WHERE groupid IN ($group_list) AND tableid='$tableid'");
   if ($r && !$r->EOF)
      return true;
   else
      return false;
}

?>
