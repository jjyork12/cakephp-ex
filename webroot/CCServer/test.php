<html>
 <head>
  <title>PHP Test</title>
 </head>
  <body>

<?php

	$hello = "OK";
	$db_host = $_SERVER['MYSQL_SERVICE_HOST'];
	$db_host2 = $_ENV['MYSQL_SERVICE_HOST'];
	$db_name = $_SERVER['DATABASE_NAME'];
	$cc_database_user = $_SERVER['DATABASE_USER'];
	$cc_database_password = $_SERVER['DB_SERVICE_PWD'];

	//echo $hello;
	echo $hello;
	echo $db_host2;

	echo "<p>Hello Now</p>";

?>

 </body>
</html>
