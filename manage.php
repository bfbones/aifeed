<?php
session_start();

include 'aifeed_functions.php';
include 'aifeed_globals.php';

switch ($_POST['action'])
{
case 'save':
  feed_change($_POST['feed_id'],
    mysql_real_escape_string($_POST['feed_url']),
    mysql_real_escape_string($_POST['feed_title']),
    $_POST['feed_item_no'],
    $_POST['feed_type'],
    $_POST['feed_list']);
  header("Location: manage.php");
  exit;
  break;
case 'delete':
  feed_delete($_POST['feed_id']);
  header("Location: manage.php");
  exit;
  break;
case 'new':
  feed_create($_POST['feed_url'], $_POST['feed_list']);
  header("Location: manage.php");
  exit;
  break;
case 'import':
  file_import($_FILES['datei']);
  header("Location: manage.php");
  exit;
  break;
}
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
    <section>
      <header>
        <h2>Feed-Verwaltung</h2>
        <a href="manage.php">Aktualisieren</a>
      </header>
<?php

printf("<h3>Neue Feeds anlegen</h3><form action=\"manage.php\" method=\"post\">
  <label for=\"feed_url\">Url eines RSS/Atom Feeds:</label>
  <input type=\"hidden\" name=\"action\" value=\"%s\">
  <input type=\"type\" size=\"50\" maxlength=\"255\" name=\"feed_url\" value=\"%s\">
  <input type=\"checkbox\" name=\"feed_list\" title=\"in Feedschau zeigen?\" value=\"1\" %s >
  <input type=\"submit\" value=\"%s\">
  </form><br />",
  "new",
  "",
  "checked",
  "Neu anlegen");

print('
  <form action="manage.php" method="post" enctype="multipart/form-data" id="uploadform">
  <label for="datei">opml-Datei importieren:</label>
  <input type="hidden" name="action" value="import">
  <input size="30" type="file" name="datei">
  <input type="submit" value="Hochladen">
  </form>');

print('<h3>Feeds bearbeiten</h3>');

$feeds = get_feed_list();
while($feed = mysql_fetch_object($feeds))
{
  if($_POST['action'] == "edit" && $_POST['feed_id'] == $feed->feed_id)
  {
    $readonly = "";
    $button = "Speichern";
    $action = "save";
  }
  else
  {
    $readonly = "disabled";
    $button = "Bearbeiten";
    $action = "edit";
  }
  printf("<a name=\"%2\$s\"></a> <form action=\"manage.php#%2\$s\" method=\"post\">
  <input type=\"hidden\" name=\"action\" value=\"%1\$s\">
  <input type=\"hidden\" name=\"feed_id\" value=\"%2\$s\">
  <input type=\"type\" size=\"50\" maxlength=\"255\" name=\"feed_url\" title=\"URL des Feeds\" value=\"%3\$s\" %7\$s >
  <input type=\"text\" size=\"30\" maxlength=\"255\" name=\"feed_title\" title=\"Titel des Feeds (leere Eingabe f&uuml;r Originaltitel)\" value=\"%4\$s\" %7\$s >
  <select name=\"feed_item_no\" title=\"Anzahl der Eintr&auml;ge pro Seite\" size=\"1\" %7\$s >
  <option value=\"0\" %10\$s>default</option>
  <option value=\"10\" %11\$s>10</option> 
  <option value=\"25\" %12\$s>25</option> 
  <option value=\"50\" %13\$s>50</option> 
  </select>
  <select name=\"feed_type\" title=\"Typ des Feeds\" size=\"1\" %7\$s >
  <option value=\"rss\" %8\$s>RSS</option>
  <option value=\"atom\" %9\$s>Atom</option>
  </select>
  <input type=\"checkbox\" name=\"feed_list\" title=\"in Feedschau zeigen?\" value=\"1\" %5\$s %7\$s >
  <input type=\"submit\" value=\"%6\$s\">",
    $action,
    $feed->feed_id,
    $feed->feed_url,
    $feed->feed_title,
    (($feed->feed_list == 1) ? "checked" : NULL),
    $button,
    $readonly,
    (($feed->feed_type == 'rss') ? 'selected' : NULL),
    (($feed->feed_type == 'rss') ? NULL : 'selected'),
    (($feed->feed_item_no == 0) ? 'selected' : NULL),
    (($feed->feed_item_no == 10) ? 'selected' : NULL),
    (($feed->feed_item_no == 25) ? 'selected' : NULL),
    (($feed->feed_item_no == 50) ? 'selected' : NULL));
  if($action == 'save')
  {
    printf("<input type=\"reset\" value=\"Reset\">");
  }
  print("</form>\n");
  if($action == 'save')
  {
    printf("<form action=\"manage.php\" method=\"post\">
      <input type=\"hidden\" name=\"action\" value=\"delete\">
      <input type=\"hidden\" name=\"feed_id\" value=\"%s\">
      <input type=\"submit\" value=\"Feed l&ouml;schen\">
      </form>\n", $feed->feed_id);
  }
}
?>
    </section>
  </body>
</html>
