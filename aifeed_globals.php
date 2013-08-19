<?php
$db_server = "mysql.example.com";
$db_name = "aifeed_db";
$db_user = "aifeed_user";
$db_password = "aifeed_password";
$db_table_feeds = "feedlist";
$db_table_items = "itemlist";
$db_table_feed_item = "feed_item_list";
$default_item_no = 10;
$locale = "de_DE.UTF-8";

$ns = array
  (
    'content' => 'http://purl.org/rss/1.0/modules/content/',
    'wfw' => 'http://wellformedweb.org/CommentAPI/',
    'dc' => 'http://purl.org/dc/elements/1.1/'
  );

setlocale(LC_TIME, $locale);
$db_connection = mysql_connect($db_server, $db_user, $db_password) or die ("keine Verbindung möglich.
  Benutzername oder Passwort ist falsch.");
mysql_select_db($db_name) or die ("Die Datenbank existiert nicht.");
?>
