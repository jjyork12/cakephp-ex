<?php
	// Collect Connect database endpoint
	// University of Michigan-Dearborn
	// CIS-4951 Fall 2019, CIS-4952 Winter 2020
	// Abbass Srour, Ryan Dupke

	// N.B. only output JSON from this page or things WILL break!
	// Specify all output as JSON
	header('Content-Type: application/json');
	// Set to 0 to prevent PHP from echoing errors and breaking things
	error_reporting(0);

	/*
		{
			"error": TRUE if message is an error, FALSE otherwise,
			"message": output message,
			"data": {
				extra data, if any
				for any:
					"errormessage": getMessage of exception or error
				for login():
					"authkey": the user's auth key for authenticated actions
				for register(), forgot_recoverykey():
					"authkey": the user's auth key for authenticated actions
					"recoverykey": the user's recovery key
				for get_friends():
					"friends": json object of uid-username key-value pairs
				for get_invite():
					"roomid": invite room id for matchmaking
			}
		}
	*/

	/*
		USERS
			id: pk int unsigned auto_increment
			username: uk varchar(20)
			deviceid: char(64)
			authkey: char(64) null
			recoverykey: varchar(128)
	*/

	/* Database connection information */
	$cc-database-user = $_ENV["DB_SERVICE_USER"];
	$cc-database-password = $_ENV["DB_SERVICE_PWD"];
	$database-host = $_ENV["DB_SERVICE_HOST"];
	// Recovery Key
	// These options can safely be changed without affecting
	// previously generate recovery keys
	define('RK_WORDLIST', 'eff_short_wordlist_2_0.txt'); // filename for word list
	define('RK_WORDCOUNT', 6); // number of words to generate for recovery keys
	define('RK_DIECOUNT', 4); // number of dice per word (4 for EFF short list, 5 for standard list)

	// Other
	define('CARD_FILENAME_TO_DATABASE_CARD_NAME_REGEX', '/_[BF]\./');

	// just output a json message and quit
	function output_and_quit($error, $message, $data = NULL) {
		$output = (object)['error' => $error, 'message' => $message];
		if (!is_null($data)) {
			$output->data = $data;
		}
		echo (isset($_COOKIE['whitespace']) ? json_encode($output, JSON_PRETTY_PRINT) : json_encode($output));
		exit;
	}

	// get user id for username
	function get_userid($pdo, $username) {
		$query = $pdo->prepare('SELECT `id` FROM `users` WHERE `username`=:un;');
		$query->execute(['un' => $username]);
		return $query->fetch(PDO::FETCH_OBJ)->id;
	}

	// get username for user id
	function get_username($pdo, $uid) {
		$query = $pdo->prepare('SELECT `username` FROM `users` WHERE `id`=:uid;');
		$query->execute(['uid' => $uid]);
		return $query->fetch(PDO::FETCH_OBJ)->username;
	}


	function get_cardid($pdo, $cardfilename) {
		$cardname = preg_split(CARD_FILENAME_TO_DATABASE_CARD_NAME_REGEX, $cardfilename)[0];
		$query = $pdo->prepare('SELECT `id` FROM `cards` WHERE `cardKey`=:ck;');
		$query->execute(['ck' => $cardname]);
		return $query->fetch(PDO::FETCH_OBJ)->id;
	}

	function validate_username($username) {
		if (preg_match('/^[A-Za-z][0-9A-Za-z]{4,19}$/', $username) !== 1) {
			//output_and_quit(TRUE, 'Invalid username');
		}
		return TRUE;
	}

	function validate_authkey($pdo, $username, $authkey) {
		$query = $pdo->prepare('SELECT `authkey` FROM `users` WHERE `username`=:un;');
		$query->execute(['un' => $username]);
		$result = $query->fetch(PDO::FETCH_OBJ);

		if (is_object($result) && hash_equals($result->authkey, $authkey)) {
			return TRUE;
		}

		output_and_quit(TRUE, 'Invalid username or authkey');
	}

	function generate_authkey($pdo, $username) {
		$ak = hash_hmac('sha3-256', $username, random_bytes(64));
		$query = $pdo->prepare('UPDATE `users` SET `authkey`=:ak WHERE `username`=:un;');
		$query->execute(['ak' => $ak, 'un' => $username]);
		return $ak;
	}

	function clear_authkey($pdo, $username) {
		$query = $pdo->prepare('UPDATE `users` SET `authkey`=NULL WHERE `username`=:un;');
		$query->execute(['un' => $username]);
	}

	function generate_recoverykey() {
		try {
			// lines into an array
			$diceware = preg_split('/\r?\n/', file_get_contents(RK_WORDLIST));
			if (empty(end($diceware))) {
				array_pop($diceware);
			}

			// parse and dictionaryify wordlist
			$wordlist = array();
			foreach ($diceware as $word) {
				$word = preg_split('/\t/', $word);
				$wordlist[$word[0]] = $word[1];
			}

			// generate words
			$rk = '';
			for ($i = 0; $i < RK_WORDCOUNT; $i++) { // for each word
				$wid = ''; // which word will be used
				for ($j = 0; $j < RK_DIECOUNT; $j++) { // for each die
					// roll the die and append its' value to the id
					// n.b. random_int is a cryptographically secure prng
					// since this is effectively a password, you MUST
					// use a cryptographically secure prng
					$wid .= strval(random_int(1, 6));
				}
				$rk .= $wordlist[$wid] . ' '; // add the word to the recovery key
			}
			return trim($rk); // removing surrounding whitespace
		}
		catch (Error | Exception $e) {
			output_and_quit(TRUE, 'Unable to generate recovery key', ['errormessage' => $e->getMessage()]);
		}
	}

	function hash_dbdata($data) {
		return hash('sha3-256', $data);
	}

	function login($pdo, $username, $deviceid) {
		validate_username($username);

		$query = $pdo->prepare('SELECT `deviceid` FROM `users` WHERE `username`=:un;');
		$query->execute(['un' => $username]);
		$result = $query->fetch(PDO::FETCH_OBJ);

		if (is_object($result) && hash_equals($result->deviceid, hash_dbdata($deviceid))) {
			$ak = generate_authkey($pdo, $username);
			output_and_quit(FALSE, 'Login successful', ['authkey' => $ak]);
		}

		output_and_quit(TRUE, 'Invalid username or device id (if this is a new device, use the recovery option)');
	}

	function logout($pdo, $username, $authkey) {
		validate_username($username);
		validate_authkey($pdo, $username, $authkey);
		clear_authkey($pdo, $username);
		output_and_quit(FALSE, 'Logout successful');
	}

	function register($pdo, $username, $deviceid) {
		validate_username($username);

		$query = $pdo->prepare('SELECT `id` FROM `users` WHERE `username`=:un;');
		$query->execute(['un' => $username]);
		$result = $query->fetch(PDO::FETCH_OBJ);
		if (is_object($result)) {
			output_and_quit(TRUE, 'Username already exists');
		}

		$id = hash_dbdata($deviceid);
		$rk = generate_recoverykey();

		$insert = $pdo->prepare('INSERT INTO `users` (`username`, `deviceid`, `recoverykey`) VALUES (:un, :id, :rk);');
		$insert->execute([
			'un' => $username,
			'id' => $id,
			'rk' => password_hash($rk, PASSWORD_ARGON2ID)
		]);

		$ak = generate_authkey($pdo, $username);
		output_and_quit(FALSE, 'Successfully registered', ['authkey' => $ak, 'recoverykey' => $rk]);
	}

	function forgot_recoverykey($pdo, $username, $deviceid, $authkey) {
		validate_username($username);
		validate_authkey($pdo, $username, $authkey);

		$id = hash_dbdata($deviceid);
		$rk = generate_recoverykey();

		$query = $pdo->prepare('UPDATE `users` SET `recoverykey`=:rk WHERE `username`=:un AND `deviceid`=:id AND `authkey`=:ak;');
		$query->execute([
			'rk' => password_hash($rk, PASSWORD_ARGON2ID),
			'un' => $username,
			'id' => $id,
			'ak' => $authkey
		]);

		clear_authkey($pdo, $username);
		$ak = generate_authkey($pdo, $username);
		output_and_quit(FALSE, 'Reset recovery key', ['authkey' => $ak, 'recoverykey' => $rk]);
	}

	function recover($pdo, $username, $newdeviceid, $recoverykey) {
		validate_username($username);

		$query = $pdo->prepare('SELECT `recoverykey` FROM `users` WHERE `username`=:un;');
		$query->execute(['un' => $username]);
		$result = $query->fetch(PDO::FETCH_OBJ);
		if (!is_object($result)) {
			output_and_quit(TRUE, 'Username does not exist');
		}

		if (is_object($result) && password_verify($recoverykey, $result->recoverykey)) {
			$id = hash_dbdata($newdeviceid);
			$query = $pdo->prepare('UPDATE `users` SET `deviceid`=:id WHERE `username`=:un;');
			$query->execute(['id' => $id, 'un' => $username]);
			clear_authkey($pdo, $username);
			output_and_quit(FALSE, 'Recovery successful');
		}

		output_and_quit(TRUE, 'Invalid recovery key');
	}

	function store_user_word($pdo, $username, $authkey, $roundid, $cardleft, $cardright, $word) {
		validate_username($username);
		validate_authkey($pdo, $username, $authkey);

		if (!is_numeric($roundid) || !is_int(intval($roundid))) {
			output_and_quit(TRUE, 'Invalid parameters', ['errormessage' => 'roundid must be int']);
		}
		if (empty($cardleft)) {
			output_and_quit(TRUE, 'Invalid parameters', ['errormessage' => 'cardleft must be non-null']);
		}
		if (empty($cardright)) {
			output_and_quit(TRUE, 'Invalid parameters', ['errormessage' => 'cardright must be non-null']);
		}
		if (empty($word)) {
			output_and_quit(TRUE, 'Invalid parameters', ['errormessage' => 'word must be non-null']);
		}

		$query = $pdo->prepare('INSERT INTO `card_words` (`round_id`, `leftCard_id`, `rightCard_id`, `player_id`, `wordChosen`) VALUES (:ri, :cl, :cr, :pi, :wd);');
		$query->execute([
			'ri' => $roundid,
			'cl' => get_cardid($pdo, $cardleft),
			'cr' => get_cardid($pdo, $cardright),
			'pi' => get_userid($pdo, $username),
			'wd' => $word
		]);

		output_and_quit(FALSE, 'Word saved');
	}


	function add_friend($pdo, $username, $authkey, $friendname) {
		validate_username($username);
		validate_authkey($pdo, $username, $authkey);

		$a=get_userid($pdo,$username);

		$friendname=substr_replace($friendname ,"", -3);
		$b=get_userid($pdo,strtok($friendname, '\\'));


		$insert = $pdo->prepare('INSERT INTO `user_friends` (`user1_id`, `user2_id`) VALUES (:u1,:u2);');
		$insert->execute([
			'u1' => $a,
			'u2' => $b
		]);
		$insert = $pdo->prepare('INSERT INTO `user_friends` (`user1_id`, `user2_id`) VALUES (:u1,:u2);');
		$insert->execute([
			'u1' => $b,
			'u2' => $a
		]);

		$mssg="Added".$username."=>".$a."and ".$friendname."=>".$b."III";
		output_and_quit(FALSE, $mssg);
		}

	function get_friends($pdo, $username, $authkey) {
		validate_username($username);
		validate_authkey($pdo, $username, $authkey);

		$uid = get_userid($pdo, $username);

		$query = $pdo->prepare('SELECT `user2_id` FROM `user_friends` WHERE `user1_id`=:uid;');
		$query->execute(['uid' => $uid]);
		$result = $query->fetchAll(PDO::FETCH_COLUMN, 'user2_id');
		$friends = [];

		foreach ($result as $uid) {
			$friends[$uid] = get_username($pdo, $uid);
		}

		output_and_quit(FALSE, 'Friends retrieved', ['friends' => $friends]);
	}
function get_friend_colors($pdo, $username, $authkey) {
	validate_username($username);
	validate_authkey($pdo, $username, $authkey);

	$uid = get_userid($pdo, $username);

	$query = $pdo->prepare('SELECT `user2_id` FROM `user_friends` WHERE `user1_id`=:uid;');
	$query->execute(['uid' => $uid]);
	$result = $query->fetchAll(PDO::FETCH_COLUMN, 'user2_id');
	$friends = array();

	foreach ($result as $uid) {
		$username=get_username($pdo, $uid);
		//$friends[$uid] =
		 $color=get_avatar_color($pdo,$username,$authkey,false);
		 array_push($friends,$color);
	}

	output_and_quit(FALSE, 'Friend Colors retrieved', ['friends' => $friends]);
}

	function set_bio($pdo, $username, $authkey, $bio) {
		validate_username($username);
		validate_authkey($pdo, $username, $authkey);

		if (empty($bio)) {
			output_and_quit(TRUE, 'Invalid paramteters', ['errormessage' => 'bio must be non-null']);
		}

		$query = $pdo->prepare('UPDATE `users` SET `userDesc`=:bo WHERE `username`=:un AND `authkey`=:ak;');
		$query->execute([
			'bo' => $bio,
			'un' => $username,
			'ak' => $authkey
		]);

		output_and_quit(FALSE, 'Set bio');
	}

	function get_bio($pdo, $username, $authkey) {
		validate_username($username);
		validate_authkey($pdo, $username, $authkey);

		$query = $pdo->prepare('SELECT `userDesc` FROM `users` WHERE `username`=:un AND `authkey`=:ak;');
		$query->execute(['un' => $username, 'ak' => $authkey]);

		output_and_quit(FALSE, 'Got bio', ['bio' => $query->fetch(PDO::FETCH_OBJ)->userDesc]);
	}

	function set_invite($pdo, $username, $authkey, $invitee, $roomid) {
		validate_username($username);
		validate_username($invitee);
		validate_authkey($pdo, $username, $authkey);

		$query = $pdo->prepare('UPDATE `users` SET `invite`=:rid WHERE `username`=:inv;');
		$query->execute(['rid' => $roomid, 'inv' => $invitee]);

		output_and_quit(FALSE, 'Set invite');
	}
	function get_colors($pdo)
	{
		$query=$pdo->prepare('SELECT `hexCode` FROM `avatar_colors` ORDER BY `id`');
		$query->execute();
		$result = $query->fetchAll(PDO::FETCH_COLUMN, 'hexCode');

		output_and_quit(FALSE, 'Colors Retrieved', ['colors' => $result]);

	}
	function set_avatar_color($pdo, $username, $authkey,$colorID) {
		validate_username($username);
		validate_authkey($pdo, $username, $authkey);

		$query = $pdo->prepare('UPDATE `users` SET `avatarcolor_id`=:cid WHERE `username`=:usr;');
		$query->execute(['cid' => $colorID, 'usr' => $username]);

		output_and_quit(FALSE, 'Set Color');
	}
	function get_avatar_color($pdo, $username,$authkey, $mine=true) {
    validate_username($username);
  //  validate_authkey($pdo, $username, $authkey);

    $query = $pdo->prepare('SELECT avatarcolor_id FROM users WHERE username=:un');
    $query->execute(['un' => $username]);
    if($mine==true)
    	output_and_quit(FALSE, 'Got avatarColor', ['avatarColor' => $query->fetch(PDO::FETCH_OBJ)->avatarcolor_id]);
    else
    	return $query->fetch(PDO::FETCH_OBJ)->avatarcolor_id;

  }
	function get_invite($pdo, $username, $authkey) {
		validate_username($username);
		validate_authkey($pdo, $username, $authkey);

		$query = $pdo->prepare('SELECT `invite` FROM `users` WHERE `username`=:un AND `authkey`=:ak;');
		$query->execute(['un' => $username, 'ak' => $authkey]);
		$result = $query->fetch(PDO::FETCH_OBJ);

		if (is_null($result->invite)) {
			output_and_quit(FALSE, 'No invite', ['roomid' => 'none']);
		}
		else {
			null_invite($pdo, $username);
			output_and_quit(FALSE, 'Invite found', ['roomid' => $result->invite]);
		}

		output_and_quit(TRUE, 'Unknown error occurred');
	}

	function null_invite($pdo, $username) {
		$query = $pdo->prepare('UPDATE `users` SET `invite`=NULL WHERE `username`=:inv;');
		$query->execute(['inv' => $username]);
	}

	function add_games($pdo, $numRounds,$card1,$card2, $username1, $word1, $username2,$word2, $username3,$word3, $username4,$word4) {

	  validate_username($username1);
	  validate_username($username2);
	  validate_username($username3);
	  validate_username($username4);

		$uid1 = get_userid($pdo, $username1);

		$uid2 = get_userid($pdo, $username2);

		$uid3 = get_userid($pdo, $username3);

		$uid4 = get_userid($pdo, $username4);

	  $insert = $pdo->prepare('INSERT INTO `games` (`RoundNumber`,`card1`,`card2`, `Player1`,`word1`,`Player2`,`word2`,`Player3`,`word3`, `Player4`,`word4`) VALUES (:nr, :c1,:c2,:u1,:w1, :u2,:w2, :u3,:w3, :u4,:w4);');
	  $insert->execute([
	    'nr' => $numRounds,
		'c1' => $card1,
		'c2' => $card2,
	    'u1' => $uid1,
		'w1' => $word1,
	    'u2' => $uid2,
		'w2' => $word2,
	    'u3' => $uid3,
		'w3' => $word3,
	    'u4' => $uid4,
		'w4' => $word4
	  ]);
		$mssg="Added Rounds is ".$numRounds." players are =>".$username1.$uid1."and ".$username2.$uid2."=>".$username3.$uid3."and".$username4.$uid4."III";

	  output_and_quit(FALSE,$mssg );//'Successfully added');
	}

	function add_words($pdo, $username, $userWord, $card1, $card2) {

		$uid = get_userid($pdo, $username);

	  $insert = $pdo->prepare('INSERT INTO `words` (`$userID`, `$userWord`,`$card1`, `$card2`) VALUES (:uid, :uw, :c1, :c2);');
	  $insert->execute([
	    'uid' => $uid,
	    'uw' => $userWord,
	    'c1' => $card1,
	    'c2' => $card2
	  ]);

	  output_and_quit(FALSE, 'Successfully added');
	}


	// Main
	// Pass
	try {
		$pdo = new PDO('mysql:host=' . $database-host . ';dbname=collectconnect;charset=utf8mb4,'. $cc-database-user. ',' . $cc-database-pwd);
		echo $pdo;
		//$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


	}
	catch (Error | Exception $e) {
		output_and_quit(TRUE, 'FATAL! PDO connection failed');
		die;
	}

	switch ($_POST['action']) {
		case 'login':
			login($pdo, $_POST['username'], $_POST['deviceid']);
			break;
		case 'logout':
			logout($pdo, $_POST['username'], $_POST['authkey']);
			break;
		case 'register':
			register($pdo, $_POST['username'], $_POST['deviceid']);
			break;
		case 'forgot_recoverykey':
			forgot_recoverykey($pdo, $_POST['username'], $_POST['deviceid'], $_POST['authkey']);
			break;
		case 'recover':
			recover($pdo, $_POST['username'], $_POST['newdeviceid'], $_POST['recoverykey']);
			break;
		case 'store_word':
			store_user_word($pdo, $_POST['username'], $_POST['authkey'], $_POST['roundid'], $_POST['cardleft'], $_POST['cardright'], $_POST['word']);
			break;
		case 'add_friend':
			add_friend($pdo, $_POST['username'], $_POST['authkey'], $_POST['friendname']);
			break;
		case 'get_friends':
			get_friends($pdo, $_POST['username'], $_POST['authkey']);
			break;
		case 'get_friend_colors':
			get_friend_colors($pdo, $_POST['username'], $_POST['authkey']);
			break;
		case 'set_bio':
			set_bio($pdo, $_POST['username'], $_POST['authkey'], $_POST['bio']);
			break;
		case 'get_bio':
			get_bio($pdo, $_POST['username'], $_POST['authkey']);
			break;
		case 'set_invite':
			set_invite($pdo, $_POST['username'], $_POST['authkey'], $_POST['invitee'], $_POST['roomid']);
			break;
		case 'get_invite':
			get_invite($pdo, $_POST['username'], $_POST['authkey']);
			break;
		case 'get_colors':
			get_colors($pdo);
			break;
		case 'set_avatar_color':
            set_avatar_color($pdo, $_POST['username'], $_POST['authkey'],$_POST['colorid']);
			break;
		case 'get_avatar_color':
	  	get_avatar_color($pdo, $_POST['username'],$_POST['authkey']);
	    break;
		case 'update_coins':
			update_coins($pdo, $_POST['username'], $_POST['authkey'], $_POST['coins']);
			break;
		case 'update_points':
			update_points($pdo, $_POST['username'], $_POST['authkey'], $_POST['pointsCollected']);
			break;
		case 'get_coins':
			get_coins($pdo, $_POST['username'], $_POST['authkey']);
			break;
		case 'add_words':
			add_words($pdo, $_POST['userID'], $_POST['userWord'], $_POST['card1'], $_POST['card2']);
			break;
		case 'add_games':
			add_games($pdo, $_POST['numRounds'],$_POST['card1'],$_POST['card2'], $_POST['username1'],$_POST['word1'],$_POST['username2'], $_POST['word2'],$_POST['username3'],$_POST['word3'], $_POST['username4'],$_POST['word4']);
			break;

	}

	output_and_quit(TRUE, 'Invalid action');
?>
