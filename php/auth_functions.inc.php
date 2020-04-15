<?php

function logon( $db, $username, $password, $sessionid, $test=FALSE ) {
  /*
  PURPOSE: Verifies that the username / password combination is valid, adds the $sessionid
           to the $db, linking it to $username in auth_session TABLE.

  INPUT:
        $db: MYSQLI CLASS, expect the user to have already connected to their database
             using the mysqli class and created proper schema for auth_user, auth_session,
             auth_access TABLES.

        $username: STRING, username that exists in user column of auth_user

        $password: STRING, password provided by users

        $sessionid: STRING, generated from start_session()

        $test: BOOLEAN, defaults to FALSE, but function will print additional errors
                and success messages if user passes TRUE

  OUTPUT:
        BOOLEAN, TRUE if credentials valid, FALSE if credentials invalid

  */

  // Query $db to get username and password hash values from auth_user:
  $query = "SELECT hash
              FROM auth_user
              WHERE user = ?";

  $stmt = $db -> stmt_init();
  $stmt -> prepare( $query );
  $stmt -> bind_param ('s', $username);

  $success = $stmt -> execute();
  $stmt -> store_result();
  $num_rows = $stmt->num_rows;

  //Return an error if no results returned!
  if ( $num_rows == 0){
    echo "<h5> ERROR: $username does not exist in the database!</h5>";

  //Error check only when requested via $test:
  } else if ( $test && (!$success ) ){
    echo "<h5> ERROR: " . $db -> error . " for query *$query* in login()! </h5><hr> Please Try Again!";

  } else if ( $test ) {
    echo "<h5> Query for user, hash in logon() SUCCESS!</h5>";
  }

  if ($success){ //store, bind and fetch the results if query successful
    $stmt -> store_result();
    $stmt -> bind_result( $hash );

    //$sessionID must be unique so only one fetch() necessary:
    $stmt -> fetch();
  }

  $stmt->close();

  // Check the password with the hash stored in the DB!
  $hash_check =  password_verify ( $password, $hash );

  if ( $test ){ //Print hashes and check result if $test set for debugging
    if ( $hash_check ){
    echo "<h5> logon(): HASHES MATCH!!!</h5>";

    }else{
      echo "<h5> logon(): ERROR!!! HASHES DO NOT MATCH!!!</h5>";
    }
  }

  // If hashes checks out, Load $_SESSION from auth_user (session):
  if ( $hash_check ){

    // Update auth_user (lastlogin):
    $query_lastlogin = "UPDATE auth_user
                          SET lastlogin = NOW()
                          WHERE user = ?";

    $stmt = $db -> stmt_init();
    $stmt -> prepare( $query_lastlogin );
    $stmt -> bind_param ('s', $username);

    $success = $stmt -> execute();
    if ( $test && $success && $db -> affected_rows > 0  ) { // if $test set for debugging
      echo '<h5>logon(): auth_user( lastlogin ) updated successfully! </h5>';

    } else if ( $db -> affected_rows == 0 ){
      echo "<h5> logon(): ERROR!!! No rows updated in auth_user( lastlogin )!!!</h5>";
      return FALSE;
      die; // Just to be sure we don't update auth_session below in this case

    }else if ( !$success ) {
      echo "<h5> ERROR: " . $db -> error . " for query *$query* in login()! </h5><hr> Please Try Again!";
      return FALSE;
      die; // Just to be sure we don't update auth_session below in this case
    }
    $stmt -> close();

    // Store sessionid and user in auth_session TABLE (set lastvisit too):
    // NOTE: If a user logs in twice, we want to just update their last visit time!

    $query_authsess = "INSERT INTO auth_session
                       VALUES(?, ?, NOW())
                       ON DUPLICATE KEY UPDATE lastvisit=NOW()";

    $stmt = $db -> stmt_init();
    $stmt -> prepare( $query_authsess );
    $stmt -> bind_param ('ss', $username, $sessionid );

    $success = $stmt -> execute();

    // if $test set, provide success otherwise just errors!
    if ( $test && $success && $db -> affected_rows > 0  ) {
      echo '<h5>logon(): auth_session(lastvisit) updated successfully! </h5> ';

    } else if ($db -> affected_rows== 0){
      echo "<h5> logon(): ERROR!!! No rows updated in auth_user(lastlogin)!!!</h5>";

    } else if ( !$success ) {
      echo "<h5> ERROR: " . $db -> error . " for query *$query_authsess* in login()! </h5> Please Try Again!";
    }

  } //hash check

  return $success;
} //logon()


function logoff( $db, $sessionid ) {
  /*
  PURPOSE: Function removes the row from the TABLE auth_session that is linked with the sessionid.

  INPUT:
        $db: MYSQLI CLASS, expect the user to have already connected to their database
             using the mysqli class and created proper schema for auth_user, auth_session,
             auth_access TABLES.

        $sessionid: STRING, generated from start_session()

  OUTPUT:
        BOOLEAN, TRUE if row deleted FALSE if TABLE auth_session unaffected

  */

  // OPTIONAL: It's a good idea to check for expired sessionIDs from the user to
  //           clean-up the DB from a session they never logged out of:

  // Optional 1) Get the associated Username:
  $sessID2Username = "SELECT user
                        FROM auth_session
                        WHERE id = ? ";

  $stmt = $db -> stmt_init();
  $stmt -> prepare( $sessID2Username );
  $stmt -> bind_param ( 's', $sessionid );
  $stmt -> execute();
  $stmt -> store_result();
  $stmt -> bind_result( $username );

  //Only expect one username for this sessionID:
  $stmt -> fetch();

  // Optional 2) Select all sessions from this Username that are expired. leave non-expired
  //             in case the user is logged-in in another browser:
  $username2allSessions = " DELETE FROM auth_session
                              WHERE ( NOW() > (DATE_ADD( lastvisit, INTERVAL 1 HOUR )))
                                    AND user = ? ";

  $stmt = $db -> stmt_init();
  $stmt -> prepare( $username2allSessions );
  $stmt -> bind_param ( 's', $username );
  $stmt -> execute();

  //REQUIRED: delete the row in auth_session corresponding to the passed SessionID:
  $delete_session = "DELETE FROM auth_session
                        WHERE id = ? ";

  $stmt = $db -> stmt_init();
  $stmt -> prepare( $delete_session );
  $stmt -> bind_param ( 's', $sessionid );
  $success = $stmt -> execute();

  return $success;
} //logoff()


function newUser( $db, $username, $password, $specialAccess=NULL ) {
  /*
  PURPOSE: Given a new username and password combination, store these values and
  any special access information in auth_access Table

  INPUT:
        $db: MYSQLI CLASS, expect the user to have already connected to their database
             using the mysqli class and created proper schema for auth_user, auth_session,
             auth_access TABLES.

        $username: STRING, username that does NOT exist in user column of auth_user

        $password: STRING, password provided by user as new password

        $specialAccess: ARRAY, optional user specific access information

  OUTPUT:
        BOOLEAN, TRUE if credentials stored, FALSE if credentials failed or already exist
  */

  //generate hash of provided password:
  $password_hash = password_hash($password, PASSWORD_BCRYPT);

  // Build the query:
  $query = "INSERT INTO auth_user (user, hash, lastlogin)
  VALUES(?, ?, NOW() )";

  $stmt = $db -> stmt_init();
  $stmt -> prepare( $query );

  // Bind our query parameters:
  $stmt -> bind_param ( 'ss', $username, $password_hash);
  $success = $stmt -> execute();

  // Catch duplicate username entries and display error to user:
  if ($db->errno == 1062) { // 1062 = duplicate key error number!
    echo "Username: $username already taken, please select another username and try again!!!";

  } else if ( !$success || $db -> affected_rows == 0 ){
    echo "<h5>ERROR: " . $db -> error . " for query *$query* in newUser()</h5><hr> Please Try Again!";

  } else {
    echo "<h4> Row: $username, $password_hash, added to auth_user! </h4>";
  }

  if ($specialAccess){

    // CHALLENGE: Here is where you would implement your logic to insert special
    //            access values in auth_access for different permission levels

  }

  return $success;
} //newUser()


function verify( $db, $sessionid, $test = FALSE ) {
  /*
  PURPOSE:
    Note 1: Function verifies that the sessionid is valid by checking:
          1. that the sessionid is in the database
          2. that the time since last visit is less than a limit
          3. that the time since last logon is less than a limit
    Note 2: Function should restore the session information and return the username
    Note 3: Function should update the last visit value for the session if valid

  INPUT:
        $db: MYSQLI CLASS, expect the user to have already connected to their database
             using the mysqli class and created proper schema for auth_user, auth_session,
             auth_access TABLES.

        $sessionid: STRING, generated from start_session()

        $test: BOOLEAN, defaults to FALSE, but function will print additional errors
                and success messages if user passes TRUE

  OUTPUT:
        $user: STRING, username corresponding to the provided $sessionid

  */

  // Step 1: Query $db to get auth_session( user, lastvisit ) and auth_user( lastlogin ) by $sessionid:
  $query = "SELECT user, session
              FROM auth_user INNER JOIN auth_session USING ( user )
              WHERE ( NOW() < (DATE_ADD( lastvisit, INTERVAL 1 HOUR )))
                AND ( NOW() < (DATE_ADD( lastlogin, INTERVAL 1 DAY )))
                AND id = ?";

  if( $test ){ echo "sessionid in verify(): $sessionid </br>"; }

  $stmt = $db -> stmt_init();
  $stmt -> prepare( $query );
  $stmt -> bind_param ( 's', $sessionid );

  $success = $stmt -> execute();
  $stmt -> store_result();

  $num_rows = $stmt->num_rows;

  $stmt -> bind_result( $user, $session );

  //$sessionID must be unique so only one fetch() necessary:
  $stmt -> fetch();

  //Error check, no valid sessionid message and success message only when requested via $test:
  if ( $test && $num_rows == 0 ){
    echo "<h5> verify(): No existing valid session, send user to logon()!";

  } else if ( !$success ){ // always return query errors
    echo "<h5> ERROR: " . $db -> error . " for query *$query* in verify()! </h5><hr> Please Try Again!";

  } else if ( $test ) { // Success messages when debugging with $test
    echo "<h5> Query for username, valid sessionid found in verify()!</h5>";
  }
  $stmt -> close();

  // STEP 2: If result returned, restore session information, otherwise return empty string
  $valid_user = '';
  if ( !empty( $user ) ){
    // Restore the session information (really just $_SESSION):
    session_decode( $session );

    //Only return $valid user after successful decode.
    $valid_user = $user;
  }

  // STEP 3: Update auth_session (set lastvisit):
  $query_authsess = "UPDATE auth_session
                      SET lastvisit=NOW()
                      WHERE id=?";

  $stmt = $db -> stmt_init();
  $stmt -> prepare( $query_authsess );
  $stmt -> bind_param ('s', $sessionid );

  $success = $stmt -> execute();

  // if $test variable provided, print success message, else if just errors!
  if ( $test && $success && $db -> affected_rows > 0  ) {
    echo 'verify(): auth_session(lastvisit) updated successfully! <br> ';

  }else if ( !$success ) {
    echo "<h5> ERROR: " . $db -> error . " for query *$query_authsess* in verify()! </h5><hr> Please Try Again!";
  }

  // Return $username or '' if checks fail!
  return $valid_user;

} //verify()


function update( $db, $username, $session_string, $test = FALSE ){
  /*
  PURPOSE:
    This function simply makes the necessary calls to $db in order to store a new instance
    or update an existing instance into TABLE auth_user using INSERT INTO, updating values on duplicate key.

  INPUT:
    $db: MYSQLI CLASS, expect the user to have already connected to their database
    using the mysqli class and created $dbTable with following schema:
    PHP_Sessions( username, session_id, session_array )

    $username: STRING of username pertaining to the session (NOTE: could

    $session_string: STRING as provided from session_encode()

  RETURNS:
    BOOLEAN to inform caller of successful store of instance in auth_user in $db

  */

  //Update the session_string in auth_user(session):
  $query = "UPDATE auth_user
              SET session = ?
              WHERE user = ?";

  $stmt = $db -> stmt_init();
  $stmt -> prepare( $query );
  $stmt -> bind_param ( 'ss', $session_string, $username );

  $success = $stmt -> execute();

  //Error check, success message only when requested via $test:
  if ( !$success ){
    echo "<h5>ERROR: " . $db -> error . " for query *$query*</h5> in setSession()<hr> Please Try Again!";
  } else if ( $test ) {
    echo "<h4> Row: $sessionID, $session_string added to Database! </h4>";
  }

  return $success;
} //update()


?>
