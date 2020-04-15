<?php
  // mysql.inc.php - This file will be used to establish the database connection.
  class myConnectDB extends mysqli{
    public function __construct($hostname="localhost",
        $user="m202484",
        $password="m202484",
        $dbname="m202484"){
      parent::__construct($hostname, $user, $password, $dbname);
    }
  }
?>
