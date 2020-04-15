<?php
  require_once('auth_functions.inc.php');
  require_once('mysql.inc.php');    # MySQL Connection Library
  $db = new myConnectDB();          # Connect to MySQL

  session_start();                  # Start the Session
  $sessionid = session_id();        # Retrieve the session id

// OPTIONAL: modify auth.inc.php to provide input validation for username and password from user
// Input validation method from: https://www.w3schools.com/php/php_form_validation.asp
  function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
  }

  //define variables for login:
  $username = $password = "";

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = test_input($_POST["username"]);
  $password = test_input($_POST["password"]);
  }

  // LOGON THE USER (if requested)  # Check to see if user/password were sent
  if ($username && $password) {

    // Validate the user/password combination
    if (!logon($db, $username, $password, $sessionid)) {
      header('Location: ../login.php');# Redirect the user to the login page
      die;                          # End the script (just in case)
    }
  }

  // VERIFY THE USER IS LOGGED ON
  $user = verify($db, $sessionid);  # Verify the user, return username or ''
  if ($user == '') {                # User was not successfully verified!
    header('Location: ../login.php');  # Redirect the user to the login page
    die;                            # End the script (just in case)
  }

  // LOGOFF THE USER
  if (isset($_REQUEST['logoff'])) { # Did the user request to logoff?
    logoff($db, $sessionid);        # Remove the row with this sessionid
    header('Location: ../login.php');  # Redirect the user to the login page
    die;                            # End the script (just in case)
  }

?>
