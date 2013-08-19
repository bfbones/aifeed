<?php
session_start();
include 'aifeed_functions.php';
include 'aifeed_globals.php';
?>
<!DOCTYPE HTML>
<html>
  <head>
    <title>aiFeed - Feed Reader</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  </head>
  <body>
    <header>
      <h1><a href="./">aiFeed - Feed Reader</a></h1>
    </header>
    <nav>
    </nav>
    <aside>
      <header>
        <h1>Feedliste</h1>
      </header>
      <nav>
<?php
show_feeds();
?>
      </nav>
    </aside>
    <main>
<?php
show_items($_GET['feed_id']);
?>
    </main>
  </body>
</html>




