<?php

@include "/opt/cgm-albis-dbase2mariadb/config/config.php";			// please copy the config.sample.php and edit the correct fields

require 'classes/XBase/Memo.php';
require 'classes/XBase/Enum/TableType.php';
require 'classes/XBase/Table.php';
require 'classes/XBase/Column.php';
require 'classes/XBase/Record.php';
include "classes/DBFhandler.php";

use XBase\Table;

// Initializing vars
ini_set( 'memory_limit', '2048M' );
set_time_limit( 0 );
iconv_set_encoding("input_encoding", "utf-8");
iconv_set_encoding("internal_encoding", "utf-8");
iconv_set_encoding("output_encoding", "utf-8");

$time_start = time();
$files = scandir($xbase_dir) or die ("Error! Could not open directory '$xbase_dir'.");
$conn = new mysqli($db_host, $db_uname, $db_passwd, $db_name) or die ("Error connecting to mysql $mysqli->connect_error");

foreach ($files as $file) {
  switch ($file) {
  case (preg_match("/dbf$/i", $file) ? true : false):
  	print_r("DBF: $file\n");
  	dbftomysql($file);
  	break;
  default:
  	print_r("Other file: $file\n");
  }
}

$time_end = time();
echo "\n\nImport finished! Time spent: ". round( ( $time_end - $time_start ) / 60, 2 ) ." minutes\n";

function dbftomysql($file) {
	// Path to dbase file
	global $xbase_dir;
	global $conn;
	$db_path = sprintf("%s/%s",$xbase_dir,$file);
	// Open dbase file
	$table = new Table($db_path, null, "windows-1252");
	$table->languageCode = 0x03;
	$tbl = substr($file,0,strlen($file)-4);
//	print_r ("$tbl");
	$line = array();


	$sql = "DROP TABLE IF EXISTS `albis`.`$tbl`;";
	 if ($conn->query("$sql") === TRUE) {
                echo "Table $tbl successfully dropped\n";
        } else {
                echo "Error SQL: ".$conn->error ." >> $sql \n";
        }	


	foreach ($table->getColumns() as $column) {
		print_r("\t$column->name ($column->type / $column->length)\n");
		switch($column->type) {
			case 'C':	// Character field
				$line[]= "`$column->name` VARCHAR($column->length)";
				break;
			case 'F':	// Floating Point
				$line[]= "`$column->name` FLOAT";
				break;
			case 'N':	// Numeric
				$line[]= "`$column->name` INT";
				break;
			case 'L':	// Logical - ? Y y N n T t F f (? when not initialized).
				$line[]= "`$column->name` TINYINT";
				break;
			case 'D':	// Date
				$line[]= "`$column->name` DATE";
				break;
			case 'T':	// DateTime
				$line[]= "`$column->name` DATETIME";
				break;
			case 'M':	// Memo type field
			default:
				$line[]= "`$column->name` TEXT";
				break;
		}
	}

	$str = implode(",",$line);
	$sql = "CREATE TABLE `albis`.`$tbl` ( $str );";

	if ($conn->query("$sql") === TRUE) {
		echo "Table $tbl successfully created\n";
	} else {
		echo "Error SQL: ".$conn->error ." >> $sql \n";
	}

	$table->close();

	// Import using dbf + fpt files (for MEMO data...)
	$fpt_file = str_replace( '.dbf', '.fpt', $db_path );
	$fpt_path = ( file_exists( $fpt_file ) ? $fpt_file : '' );
	//import_dbf_to_mysql( $tbl, $db_path, $fpt_path );
	import_dbf($db_path, $tbl);
}

function import_dbf($db_path, $tbl) {
	global $conn;
	// print_r ("$db_path\n");
	$table = new Table($db_path);

//	print_r ("$table->recordCount\n");
//	print_r (sizeof($table->columns));
	$i = 0;
	while ($record=$table->nextRecord()) {
		$fields = array();
		$line = array();
		foreach ($record->getColumns() as $column) {
			$fields[]=$column->name;
			// print_r("$column->name\n");

			switch($column->type) {
				case 'C':	// Character field
				case 'M':	// Memo type field
					$line[]= sprintf("'%s'", $record->getObject($column) );
					break;
				case 'F':	// Floating Point
					$line[]=sprintf("%7.2f", $record->getObject($column) );
					break;
				case 'N':	// Numeric
					$line[]=sprintf("%d", $record->getObject($column) );
					break;
				case 'L':	// Logical - ? Y y N n T t F f (? when not initialized).
					$line[] = sprintf("%d", ($record->getBoolean($column) ? 1 : 0) );
					//$line[] = sprintf("%d", $record->getString($column->name) ); 
					break;
				case 'T':	// DateTime
				case 'D':	// Date
					$line[]= sprintf("'%s'", strftime("%Y-%m-%d %H:%M", $record->getObject($column) ) );
					break;
			}			
		}

		$val = implode(",",$line);
		$col = implode(",",$fields);

		$sql = "INSERT INTO `$tbl` ($col) VALUES ($val)\n";
		// print_r ("$sql");
		if ($conn->query("$sql") === TRUE) {
			$i++;
			if ( $i % 100 == 0 ) {
                echo ".";
			}
		} else {
			echo "Error SQL: ".$conn->error ." >> $sql \n";
		}
	}
	$table->close();
	echo "\nTable $tbl imported\n";

}

function import_dbf_to_mysql( $table, $dbf_path, $fpt_path ) {
	echo "Initializing import: Table $table\n";
	global $conn;
    $i = 0;
    $Test = new DBFhandler( $dbf_path, $fpt_path );
    while ( ($Record = $Test->GetNextRecord( true )) and ! empty( $Record ) ) {
        $a = 0;
        $sql1 = "INSERT INTO `$table` (";
        $sql2 = ") values (";
        $sql = "";
        print_r($Record);
        foreach ( $Record as $key => $val ) {
            if ( $val == '{BINARY_PICTURE}' ) {
                continue;
            }
            $val = str_replace( "'", "", $val );
            $a = $a + 1;
            if ( $a == 1 ) {
                $sql1 .=$key;
                $sql2 .="'" . trim( $val ) . "'";
            } else {
                $sql1 .=",$key";
                $sql2 .=",'$val'";
            }
        }
        $sql = "$sql1 $sql2)";
        if ($conn->query("$sql") === TRUE) {
			$i++;
			if ( $i % 100 == 0 ) {
				echo ".";
			}
		} else {
			echo "Error SQL: ".$conn->error ." >> $sql \n";
		}

    }

	echo "Table $table imported\n";

}


