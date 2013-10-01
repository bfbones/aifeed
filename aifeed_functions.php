<?php

include 'aifeed_globals.php';

function filecheck($file)
{
  if(($file['type'] != 'text/x-opml+xml') or ($file['size'] > 65536))
  {
    $file_size_kB = sprintf("%d,%02d kB",
      $file['size'] / 1024, $file['size'] % 1024 * 100 / 1024);

    printf("<p>Datei ist keine OPML-Datei oder gr&ouml;&szlig;er als 64kB!<br />
      Dateiname: %s<br />
      Dateityp: %s<br />
      Dateigr&ouml;&szlig;e: %s</p>
      <a href=\"manage.php\">Zur&uuml;ck</a>",
      $file['name'], $file['type'], $file_size_kB);
    exit;
  }
  else
  {
    return true;
  }
}

function file_import($file)
{
  filecheck($file);

  $opml = simplexml_load_string(file_get_contents($_FILES['datei']['tmp_name']));

  foreach ($opml->xpath('//body/outline//outline') as $outline)
  {
    if($outline['type'] == 'folder') { continue; }
      //printf("<h3>%s</h3>\n", $outline['title']);
    $feed = array(
      'url' => $outline['xmlUrl'],
      'type' => $outline['type'],
      'title' => $outline['title']
    );

    $out = feed_import($feed);
    //  ? "Feed \"%s\" erfolgreich importiert<br />\n"
    //  : "ERROR: Feed \"%s\" konnte nicht importiert werden!<br />\n";
    echo $out;
    if($out)
    {
      $feed['id'] = $out;
      update_feed($feed, get_xml_contents($feed['url']));
    }

    //printf($out, $outline['title']);
  }
}

function create_tables() {
  db_query("CREATE TABLE IF NOT EXISTS feedlist (
    feed_id int(5) unsigned NOT NULL AUTO_INCREMENT,
    feed_type varchar(16) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    feed_url varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    feed_link varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    feed_title varchar(150) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    feed_desc text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    feed_list tinyint(1) unsigned NOT NULL DEFAULT '1',
    feed_item_no int(3) unsigned NOT NULL,
    PRIMARY KEY (feed_id) ) ENGINE=MyISAM  DEFAULT CHARSET=utf8");

  db_query("CREATE TABLE IF NOT EXISTS feed_item_list (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    feed_id int(5) unsigned NOT NULL,
    item_id int(10) unsigned NOT NULL,
    PRIMARY KEY (id) ) ENGINE=MyISAM  DEFAULT CHARSET=utf8");

  db_query("CREATE TABLE IF NOT EXISTS itemlist (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    item_guid varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    item_title varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    item_link varchar(150) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    item_pubDate int(11) unsigned NOT NULL,
    item_creator varchar(50) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    item_desc text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    item_content longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY item_guid (item_guid) ) ENGINE=MyISAM  DEFAULT CHARSET=utf8");
}

function db_query($query_string)
{
  $sqlquery = mysql_query($query_string)
    or die("ERROR: $query_string <br />".mysql_error()."<br />");
  return $sqlquery;
}

function feed_import($feed)
{
  global $db_table_feeds; 

  $check_url_string = sprintf("
    SELECT * 
    FROM %s 
    WHERE feed_url = '%s'",
    $db_table_feeds,
    $feed['url']);

  if(mysql_num_rows(db_query($check_url_string)) == 0)
  {
    $feed_import_string = sprintf("
      INSERT INTO %s (feed_type, feed_url, feed_title) 
      VALUES ('%s', '%s', '%s')",
        $db_table_feeds,
        $feed['type'],
        $feed['url'],
        mysql_real_escape_string($feed['title']));

    return (db_query($feed_import_string) ? mysql_insert_id() : false);
  }
  else
  {
    print("Feed bereits vorhanden!<br />\n");
    return false;
  }
}

function url_exists($url)
{
  $file_headers = @get_headers($url);
  return preg_match("@200|30?@", $file_headers[0])
    ? true
    : false;
}


function get_xml_contents($url)
{
  // catch errors ourself
  libxml_use_internal_errors(true);
  $xml = simplexml_load_string(file_get_contents($url));
  if(!$xml) {
    if($debug)
    {
      echo "Laden des XML \"$url\" fehlgeschlagen.<br />\n";
      foreach(libxml_get_errors() as $error) {
        echo "\t", $error->message;
      }
    }
    return 1;
  }
  return $xml;
}

function item_update($feed)
{
  global $db_table_items, $db_table_feed_item, $ns;

  foreach($feed['items'] as $item)
  {
    switch($feed['type'])
    {
    case 'rss':
      $guid = $item->guid;
      $time = $item->pubDate;
      $desc = $item->description;
      $content = $item->children($ns['content']);
      $link = $item->link;
      break;
    case 'atom':
      $guid = $item->id;
      $time = $item->updated;
      $desc = $summary;
      $content = $item->content;
      foreach($item->link as $ilink)
      {
        if(empty($ilink['rel']))
        {
          $link = $ilink['href'];
        }
      }
      break;
    default:
      continue;
    }

    $check_guid_string = sprintf("
      SELECT id
      FROM %s 
      WHERE item_guid = '%s'",
      $db_table_items,
      $guid);

    $guid_sqlquery = db_query($check_guid_string);

    if(mysql_num_rows($guid_sqlquery) == 0)
    {
      $content = empty($content) ? $desc : $content;

      $item_insert_string = sprintf("
        INSERT INTO %s
        (item_guid, item_link, item_title, item_pubDate, item_desc, item_seen, item_content)
        VALUES ('%s', '%s', '%s', '%s', '%s', 0, '%s')",
          $db_table_items,
          $guid,
          $link,
          mysql_real_escape_string($item->title),
          strtotime($time),
          mysql_real_escape_string($desc),
          mysql_real_escape_string($content));

      db_query($item_insert_string);
      $item_id = mysql_insert_id();
    }
    else
    {
      $row = mysql_fetch_row($guid_sqlquery);
      $item_id = $row->id;
    }

    $check_string = sprintf("
      SELECT * FROM %s WHERE feed_id = '%s' AND item_id = '%s'",
      $db_table_feed_item,
      $feed['id'],
      $item_id);
    $is_in_new_table = db_query($check_string);
    if(mysql_num_rows($is_in_new_table) == 0)
    {
      $newdb_insert_string = sprintf("
        INSERT INTO %s (feed_id, item_id)
        VALUES ('%s', '%s')",
        $db_table_feed_item,
        $feed['id'],
        $item_id);

      db_query($newdb_insert_string);
    }
  }
}


function feed_create($feed_url, $feed_list)
{
  $feed=array(
    'url' => $feed_url,
    'list' => $feed_list
  );

    if(url_exists($feed['url']))
    {
      $xml = get_xml_contents($feed['url']);
      if(!$xml)
        return 5;
    }
    else
    {
      echo "URL \"".$feed['url']."\" konnte nicht geladen werden.<br />\n";
      return 1;
    }

    $feed['type'] = get_feed_type($xml);
    if($feed['type'] == "none")
    {
      echo "Unbekannter Feedtyp.<br />\n";
      return 2;
    }

    $feed['title'] = get_feed_title($xml);
    //print($feed_url."<br>".$feed_type."<br>".($feed_title ? $feed_title : $feed_url)."<br>\n");
     
    $res = feed_import($feed);
    if($res)
    {
      $feed['id'] = $res;
      update_feed($feed, $xml);
    }

}

function get_feed_type($xml)
{
  if($xml->channel)
    return "rss";
  elseif($xml->entry)
    return "atom";
  else
    return "none";
}

function get_feed_title($xml)
{
  $type = get_feed_type($xml);
  switch($type)
  {
  case 'rss':
    return $xml->channel->title;
    break;
  case 'atom':
    return $xml->title;
    break;
  default:
    return false;
  }
}

function feed_change($feed_id, $feed_url, $feed_title, $feed_item_no, $feed_type, $feed_list)
{
  global $db_table_feeds, $default_item_no;

  $sqlquery = sprintf("
    UPDATE %s Set
    feed_url = '%s',
    feed_title = '%s',
    feed_type = '%s',
    feed_list = '%s',
    feed_item_no = '%s'
    WHERE feed_id = '%s'",
    $db_table_feeds,
    $feed_url,
    (($feed_title == '') ? get_feed_title(get_xml_contents($feed_url)) : $feed_title),
    $feed_type,
    $feed_list,
    (($feed_item_no == $default_item_no) ? 0 : $feed_item_no),
    $feed_id);

  db_query($sqlquery);
}

function feed_delete($feed_id)
{
  global $db_table_feeds, $db_table_feed_item, $db_table_items;

  $sqlquery = sprintf("
    DELETE FROM %s WHERE feed_id = '%s'",
    $db_table_feeds,
    $feed_id);

  db_query($sqlquery);

  $sqlquery = sprintf("
    DELETE FROM %s WHERE feed_id = '%s'",
    $db_table_feed_item,
    $feed_id);

  db_query($sqlquery);

  $sqlquery = sprintf("DELETE FROM %s WHERE NOT EXISTS
    ( SELECT * FROM %s WHERE %1\$s.id = %2\$s.item_id )",
    $db_table_items,
    $db_table_feed_item);

  db_query($sqlquery);
}

function delete_leftover_items()
{
  $sqlquery = sprintf("DELETE FROM '%1\$s'
    WHERE NOT EXISTS
      ( SELECT * FROM '%2\$s
      WHERE %1\$s.item_feed_1 = %2\$s.feed_id);",
      $db_table_items,
      $db_table_feeds);

  db_query($sqlquery);
}

function feed_update()
{
  global $db_table_feeds;

  $feed_query_string = sprintf("
    SELECT feed_id, feed_type, feed_url 
    FROM %s",
    $db_table_feeds);

  $sqlquery = db_query($feed_query_string);

  while($row = mysql_fetch_object($sqlquery))
  { 
    $feed = array
      (
        'id'    => $row->feed_id,
        'type'  => $row->feed_type,
        'url'   => $row->feed_url
      );
    if(url_exists($feed['url']))
    {
      $xml = get_xml_contents($feed['url']);
    }
    else
    {
      echo "URL \"".$feed['url']."\" konnte nicht geladen werden.<br />\n";
      continue;
    }

    update_feed($feed, $xml);
  }
}

function update_feed($feed, $xml)
{
  global $db_table_feeds;

  switch($feed['type'])
  {
  case "rss":
    $feed['link'] = $xml->channel->link;
    $feed['desc'] = $xml->channel->description;
    $feed['items'] = $xml->channel->item;
    break;
  case "atom":
    foreach($xml->link as $flink)
    {
      if(empty($flink['rel']))
      {
        $feed['link'] = $flink['href'];
      }
    }
    $feed['desc'] = $xml->description;
    $feed['items'] = $xml->entry;
    break;
  default:
    die("ERROR: unknown type of feed: ".$feed['type']." for \"".$feed['url']."\".");

  }

  #print($feed['url']."<br>\n");

  $feed_update_string = "UPDATE {$db_table_feeds} Set
    feed_link = '".mysql_real_escape_string($feed['link'])."',
      feed_desc = '".mysql_real_escape_string($feed['desc'])."'
      WHERE feed_id = '{$feed['id']}'";

  db_query($feed_update_string);

  item_update($feed);
}

function show_items($feed_id = '', $item_no = NULL)
{
  global $db_table_feeds, $db_table_items, $db_table_feed_item, $default_item_no;

  $item_no = ($item_no == NULL) ? $default_item_no : $item_no;
  $item_query_string;
  $page = isset($_GET['page']) ? $_GET['page'] : 1;
  $start = $page * $item_no - $item_no;

  if($feed_id != '')
  {
    $feed_id_esc = mysql_real_escape_string($feed_id);
    $feed_query_string = "SELECT feed_id, feed_link, feed_title, feed_item_no 
      FROM {$db_table_feeds} 
      WHERE feed_id = '{$feed_id_esc}'";

    $sqlquery = db_query($feed_query_string);
    $row = mysql_fetch_object($sqlquery);
    $heading = "<a href=\"{$row->feed_link}\">{$row->feed_title}</a>";
    $item_no = $row->feed_item_no > 0 ? $row->feed_item_no : $item_no;

    $item_query_string = "SELECT A.*
      FROM {$db_table_items} AS A JOIN {$db_table_feed_item} AS B
      ON B.item_id = A.id
      WHERE '{$feed_id_esc}' = B.feed_id 
      ORDER BY A.item_pubDate DESC
      LIMIT {$start}, {$item_no}";

    $item_count_query = "SELECT COUNT(*) FROM {$db_table_items} AS A JOIN {$db_table_feed_item} AS B
      ON B.item_id = A.id
      WHERE '{$feed_id_esc}' = B.feed_id";
    $item_count = (db_query($item_count_query));
    $feed_id_link_string = "&feed_id={$feed_id_esc}";
  }
  else
  {
    $heading = "Neueste Eintr&auml;ge";

    $item_query_string = sprintf("SELECT A.*, C.*
      FROM %s AS A
      JOIN %s AS B 
      ON B.item_id = A.id
      JOIN %s AS C
      ON B.feed_id = C.feed_id
      WHERE C.feed_list = 1
      ORDER BY A.item_pubDate DESC 
      LIMIT %d, %d",
      $db_table_items, 
      $db_table_feed_item,
      $db_table_feeds,
      $start,
      $item_no);

    $item_count_query = "SELECT COUNT(*) 
      FROM {$db_table_items} AS A JOIN {$db_table_feed_item} AS B
      ON B.item_id = A.id
      JOIN {$db_table_feeds} AS C
      ON B.feed_id = C.feed_id
      WHERE C.feed_list = 1";
    $item_count = (db_query($item_count_query));
  }
  $item_count = mysql_fetch_row($item_count);
  $item_count = $item_count[0];
  $page_max = ($item_count / $item_no);

  $item_sqlquery = db_query($item_query_string);

  print_pagination($page, $page_max, $feed_id_link_string);

  while($row = mysql_fetch_assoc($item_sqlquery))
  {
    if($row['item_seen'] == 0) {
	    print("<article>\n<header>\n<h3>
	      <a href=\"{$row['item_link']}\">{$row['item_title']}</a></h3>
	      <p>".strftime("%a, %x - %R", $row['item_pubDate'])."
	      <a href=\"{$row['feed_link']}\">{$row['feed_title']}</a>");
    } else {
            print("<article>\n<header>\n<h3>
              <a href=\"{$row['item_link']}\" style=\"color: #333;\">{$row['item_title']}</a></h3>
              <p>".strftime("%a, %x - %R", $row['item_pubDate'])."
              <a href=\"{$row['feed_link']}\">{$row['feed_title']}</a>");
    }

    print("</p>\n</header>
    {$row['item_content']}
    </article>\n");
     if($feed_id != '')
     {
    	$item_seen_query = 'UPDATE  itemlist SET item_seen=1 WHERE id ='.$row['id'].';';
    	db_query($item_seen_query);
     }
  }
  print_pagination($page, $page_max, $feed_id_link_string);
}

function print_pagination($page, $page_max, $feed_id_link_string)
{
  print("<div class=\"nav\">");
  if($page > 1)
  {
    print("<a href=\"{$_SERVER['SCRIPT_NAME']}?page=".($page - 1).$feed_id_link_string."\">Vorherige</a>");
  }
  else
  {
    print("Vorherige");
  }
  if($page < $page_max)
  {
    print("<a class=\"right\" href=\"{$_SERVER['SCRIPT_NAME']}?page=".
      ($page + 1).$feed_id_link_string."\">N&auml;chste</a>");
  }
  else
  { print("<span class=\"right\">N&auml;chste</span>"); }
  print("</div>\n");
}

function get_feed_list()
{
  global $db_table_feeds;

  $feed_query_string = "SELECT * FROM {$db_table_feeds}";

  return db_query($feed_query_string);
}

function get_unseen_count($feed_id)
{
	global $db_table_feeds;

	$item_query_string = "SELECT COUNT(*)
      FROM itemlist AS A JOIN feed_item_list AS B
      ON B.item_id = A.id WHERE B.feed_id=".$feed_id." AND item_seen=0";
	$item_count = db_query($item_query_string);
	$item_count = mysql_fetch_row($item_count);
	$item_count = $item_count[0];;
	if($item_count != 0) {
		$item_count = '&nbsp;<span style="color: #D63030; font-size: 11px; vertical-align: super;">'.$item_count."</span>";
	} else {
		$item_count = "";
	}
	return $item_count;
}

function show_feeds()
{
  print("<ul>\n");

  $sqlquery = get_feed_list();
  while($row = mysql_fetch_assoc($sqlquery))
  {
    print("<li><a href=\"./?feed_id={$row['feed_id']}\">{$row['feed_title']}</a>".get_unseen_count($row['feed_id'])."</li>\n");
  }
  print("</ul>\n");
}
