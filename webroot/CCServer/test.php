<? php

	$db_host = $_SERVER["MYSQL_SERVICE_HOST"];
	$db_name = $_SERVER["DATABASE_NAME"];
	$cc_database_user = $_SERVER["DATABASE_USER"];
	$cc_database_password = $_SERVER["DB_SERVICE_PWD"];

	echo $db_host;
	echo $db_name;
	echo $cc_database_user;

	try {
		$pdo = new PDO("mysql:host=$database_host;dbname=collect_connect", $cc_database_user, $cc_database_pwd);
		//echo $pdo;
		//$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


	}
	catch (Error | Exception $e) {
		output_and_quit(TRUE, 'FATAL! PDO connection failed');
		die;
	}
?>

<html>
 <head>
  <title>PHP Test</title>
 </head>
 <body>
 <?php echo '<p>Hello World</p>'; ?>
 <p><? php echo $db_host; ?></p>
 <p><? php echo $db_name; ?></p>
 <p><? php echo $cc_database_user; ?></p>
 </body>
</html>

