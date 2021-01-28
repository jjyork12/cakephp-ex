<html>
 <head>
  <title>PHP Test</title>
 </head>
  <body>

 <? php

	$hello = "OK";
	$db_host = $_SERVER["MYSQL_SERVICE_HOST"];
	$db_name = $_SERVER["DATABASE_NAME"];
	$cc_database_user = $_SERVER["DATABASE_USER"];
	$cc_database_password = $_SERVER["DB_SERVICE_PWD"];

	echo $hello;

	echo "<p>Hello Now</p>";

?>

 </body>
</html>
