<?php

// dumpcontent.php - Dumps table content in tab-delimited file
// dumpcontent.php - author: Nico Stuurman<nicost@sourceforge.net>

  /***************************************************************************
  * Dumps table content in tab-delimited file                                *
  * Takes 'tablename' as a get variable                                      *
  *                                                                          *
  * Copyright (c) 2003 by Nico Stuurman                                      *
  * ------------------------------------------------------------------------ *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
  \**************************************************************************/                                                                                     
// this may take a long time.  Simply kill me if I hang
ini_set("max_execution_time","0");

require ("include.php");
require ("includes/db_inc.php");
require ("includes/general_inc.php");

printheader($httptitle,false);
navbar ($USER["permissions"]);

if (!$USER["permissions"] & $SUPER) {
   echo "<h3 align='center'>Sorry, this page is not for you.</h3>\n";
   printfooter($db, $USER);
}

$tablename=$HTTP_GET_VARS["tablename"];
if (!$tablename) {
   echo "<h3 align='center'>Usage: dumpcontent.php?tablename=mytablename.</h3>\n";
   printfooter($db, $USER);
   exit();
}

$tableinfo=new tableinfo($db);

if (!$tableinfo->id) {
   echo "<h3 align='center'>Table <i>$tablename</i> does not exist.</h3>\n";
   printfooter($d, $USER);
   exit();
}

$pre_seperator="";
$post_seperator="\t";

// open file to write output to
$outfile=$system_settings["tmpdir"]."/dumpcontent.txt";
$fp=fopen($outfile,"w");
if (!$fp) {
   echo "<h3 align='center'>Failed to open <i>$outfile</i> for output</h3>\n";
   printfooter($db, $USER);
}

// 
$fields="id,".comma_array_SQL($db,$tableinfo->desname,'columnname');
$headers=getvalues($db,$tableinfo,$fields);

foreach ($headers as $header) {
   if ($header['label'])
      fwrite ($fp,$pre_seperator.$header['label'].$post_seperator);
   else
      fwrite ($fp,$pre_seperator."id".$post_seperator);
}
fwrite ($fp,"\n");

$r=$db->Execute("SELECT $fields FROM ".$tableinfo->realname);
while ($r->fields["id"] && !$r->EOF) {
   $rowvalues=getvalues($db,$tableinfo,$fields,"id",$r->fields["id"]);
   foreach ($rowvalues as $row) {
      if ($row['datatype']=="textlong")
         fwrite ($fp,$pre_seperator.$row['values'].$post_seperator);
      else
         fwrite ($fp,$pre_seperator.$row['text'].$post_seperator);
   }
   fwrite ($fp,"\n");
   $counter++;
   $r->MoveNext();
}


fclose($fp);

echo "<h3>Wrote script to $outfile.</h3>";
echo "<h3>Wrote $counter records.</h3>";

printfooter($db, $USER);

?>
