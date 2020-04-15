<?php
  session_start();
  $mysessID = session_id();

  require_once('php/page.inc.php');
  require_once('php/mysql.inc.php');
  $db = new myConnectDB();

  require_once('php/auth_functions.inc.php');


  $page = New Page("Home - Si's stocks");
  $page -> content ='  <!-- ======= Hero Section ======= -->
    <section id="hero" class="d-flex justify-cntent-center align-items-center">
      <div id="heroCarousel" class="container carousel carousel-fade" data-ride="carousel">

        <!-- Slide 1 -->
        <div class="carousel-item active">
          <div class="carousel-container">
            <h2 class="animated fadeInDown">Welcome to <span>Si\'s Stock Management</span></h2>
            <form action="main.php?" method="post">
              Username: <input type="text" name="username" required /><br>
              Password: <input type="text" name="password" required /><br>
              <input type="submit">
            <a href="login.php" class="btn-get-started animated fadeInUp">Already a user? Login here </a>
          </div>
        </div>

        <a class="carousel-control-prev" href="#heroCarousel" role="button" data-slide="prev">
          <span class="carousel-control-prev-icon bx bx-chevron-left" aria-hidden="true"></span>
          <span class="sr-only">Previous</span>
        </a>

        <a class="carousel-control-next" href="#heroCarousel" role="button" data-slide="next">
          <span class="carousel-control-next-icon bx bx-chevron-right" aria-hidden="true"></span>
          <span class="sr-only">Next</span>
        </a>

      </div>
    </section><!-- End Hero -->';

  if( isset($_POST['username']) && isset($_POST['password'])){
    $username = htmlspecialchars($_POST['username']);
    $password = htmlspecialchars($_POST['password']);
  }
  $success = newUser($db,$username,$password);
  $db -> close();
  $page ->display();

 ?>
