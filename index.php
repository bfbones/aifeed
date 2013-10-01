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
    <script src="jquery.min.js" type="text/javascript"></script>
    <script type="text/javascript">
    function get_feed_list() {
    	$.get("show_feeds.php").done(
     		function(data) {
     			$('#feeds').empty();
			$(data).appendTo('#feeds');
		});
    }
    function start_reload_timer() {
	window.setInterval("get_feed_list()", 60000);
    }
    </script>
  </head>
  <body onload="get_feed_list(); start_reload_timer();">
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
<div id="feeds"><?php show_feeds(); ?></div>
      </nav>
    </aside>
<main>
<?php 
show_items($_GET['feed_id']);
?>
    </main>
  </body>
</html>




