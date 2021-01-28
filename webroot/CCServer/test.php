<? php

	$db_host = $_SERVER["MYSQL_SERVICE_HOST"];
	$db_name = $_SERVER["DATABASE_NAME"];
	$cc_database_user = $_SERVER["DATABASE_USER"];
	$cc_database_password = $_SERVER["DB_SERVICE_PWD"];

<html>
 <head>
  <title>PHP Test</title>
 </head>
 <body>
 <?php echo '<p>Hello World</p>'; ?>
 <? php echo "<p>" . $db_host . "</p>"; ?>
 <? php echo "<p>" . $db_host . "</p>"; ?>
 </body>
</html>

