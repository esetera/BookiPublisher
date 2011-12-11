<?php

function ratedOutput ($info2,$tablerows){
  $tablerows=$tablerows-1;
  $categories = Array();
  $categoryRating = Array();

  foreach ((array)$info2 as $key => $value){
    $categories[] = $value["category"];
  }

  $categories=array_unique($categories);

  foreach ((array)$info2 as $key=>$value){
    if (strtolower($info2[$key]["status"])=="no status"){
      $info2[$key]["bookrating"]=5;
      $thisCategory=$info2[$key]["category"];
      if (array_value($categoryRating, $thisCategory)) {
        $categoryRating[$thisCategory]+=5;
      } else {
        $categoryRating[$thisCategory]=5;
      }
    }
    if (strtolower($info2[$key]["status"])=="new" || strtolower($info2[$key]["status"])=="updated"|| strtolower($info2[$key]["status"])=="major update") {
      $thisCategory=$info2[$key]["category"];
      if (array_value($categoryRating, $thisCategory)) {
        $categoryRating[$thisCategory]+=5;
      } else {
        $categoryRating[$thisCategory]=5;
      }
      $thisBook=$info2[$key]["title"];
      $bookRating[$thisBook]=5;
      $daysSinceCreated = 90 + (strtotime($info2[$key]["date"]) - strtotime(strftime("%Y-%m-%d %H:%M"))) / (60 * 60 * 24);
      if ($daysSinceCreated <= 0) $daysSinceCreated=0;
      $categoryRating[$thisCategory]+=$daysSinceCreated;
      $bookRating[$thisBook]+=$daysSinceCreated;
      $daysSinceModified = 45 + (strtotime($info2[$key]["modified"]) - strtotime(strftime("%Y-%m-%d %H:%M"))) / (60 * 60 * 24);
      if ($daysSinceModified <= 0) $daysSinceModified=0;
      $categoryRating[$thisCategory]+=$daysSinceModified;
      $bookRating[$thisBook]+=$daysSinceModified;
      if (strtolower($info2[$key]["status"])==("major update")) {
        $bookRating[$thisBook]+=15;
        $categoryRating[$thisCategory]+=5;
      }
      if (strtolower($info2[$key]["status"])==("new")) {
        $bookRating[$thisBook]+=10;
        $categoryRating[$thisCategory]+=20;
      }

      $info2[$key]["bookrating"]=$bookRating[$thisBook];
    }
  }
  @arsort($categoryRating);

  $sorter = new Sorter();
  $sorter->numeric = true;
  $sorter->backwards = true;

  $info2=$sorter->sort($info2,'bookrating');

  $html="<table width='650px'><tr>";
  $tablecounter=0;
  foreach ((array)$categoryRating as $key => $val) {
    $category=$key;
    if ($tablecounter==$tablerows+1) {
      $html.="<tr><td valign='top'>";
    }
    else{
      $html.="<td valign='top'>";
    }
    $html.="<h2 style='text-indent:-5px;'>".strtoupper($category)."</h2>";
    foreach ($info2 as $info3){
      $status="";
      if (array_value($info3, 'category')==$category){
        if (array_value($info3, 'visible')=='on'){
          if (array_value($info3, 'bookrating') > 20) $status="(".$info3['status'].")";
          $html.= '<span class="name" style="display:block;text-indent:-5px;padding-bottom:5px;"><a href="/'.$info3['dir'] .'/">'. $info3['title'] .'</a> '. $status.' <sup><small>'.$info3['translations'].'</small></sup> </span>';
        }
      }
    }
    if ($tablecounter==$tablerows) {
      $html.="</td></tr>";
      $tablecounter=0;
    }
    else{
      $html.="</td>";
      $tablecounter++;
    }
  }
  $html.="</table>";
  return $html;
}


function read_index() {

  include_once('data/redirect.php');
  $book = array_value($_GET, 'book');
  #$base_url = BASE_URL
  #if $booki===''    header( 'Location: $base_url' )
  $chapter = array_value($_GET, 'chapter') ? array_value($_GET, 'chapter') : 'index'; //default
$section=$_GET['dir'];
  //check data/redirect.php to see if the array contains a remapping
  foreach($mapping as $key => $value) {
    if (strtolower($book)== strtolower($key)) {
      foreach($mapping[$key] as $key2 => $value2) {
        if (strtolower($chapter)== strtolower($key2)) $chapter =$value2;
      }
    }

  }

  if (!isset($book)){
    if (DISPLAY_DIRS=='true'){

      if ($dh = opendir(BOOKI_DIR."/")) {
        while (($file = readdir($dh)) !== false) {
          if($file != '.' && $file != '..') {
            $content.= "<a href='index.php?book=$file'>$file</a><br>";
          }
        }
        closedir($dh);
      }
    } else {
      $tableBooks = new Axon(DB_TABLE_BOOKS);
      $content=ratedOutput($tableBooks->find(), 3);
    }

    $content = addTemplate('read', $content);
    return $content;

  } else if ($chapter == '_all') {
    $book=strtolower($book);
    $bookdir = BOOKI_DIR."/$book/";
    $content = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  </head>
  <body>
  ';

    foreach (glob("$bookdir/*.txt") as $chapterfile) {
      if ($chapterfile == "$bookdir/contents.txt" || $chapterfile == "$bookdir/index.txt") {
        continue;
      }
      $chaptercontent=@file_get_contents($chapterfile);
      $content .= $chaptercontent;
    }
    $content .= '  </body>
</html>';

    //    $content = addTemplate('book', $content);
    echo '<style type="text/css">.menu-goes-here { display: none }</style>'.$content;
  } else {
    $book=strtolower($book);
    //echo "ch=$chapter book=$book";
    if ($chapter === '')
      $chapter = "index";
    $filename=BOOKI_DIR."/$book/$section/$chapter.txt";
    $content=@file_get_contents($filename);

    if ($content == "") {
      $content =  "<br />This page does not exist. Book request=$book Chapter request=$chapter";
      $content = addTemplate('error', $content);
    } else {
      //$content = preg_replace("[href=\"([\w!\/]*).html\"]", "href=\"\\1\"", $content);
      //$content = preg_replace("[\"(static/.*)\"]", "\"booki/$book/\\1\"", $content);
      //$content = preg_replace("[<html dir=\"LTR\"><body>]", "", $content);
      //$content = preg_replace("[</body></html>]", "", $content);
      $content = addTemplate('book', $content);
      $content = render_widgets($book, $chapter, $content);
    }
    echo $content;
  }
}


function read_dispatcher($name) {
  if($name == "index") return read_index();
}

function read_beforedisplay() {
}

function read_tagreplace($hook_args) {
  $book = strtolower(array_value($_GET, 'book'));
  $output = $hook_args;
  $tableBooks = new Axon(DB_TABLE_BOOKS);
  $info = $tableBooks->find();
  $thisBook = searchArray($info,'dir',$book);
  if (!empty($thisBook[0])) {
    $title= array_value($thisBook[0], 'title');
    $dirname= array_value($thisBook[0], 'dir');
    $pdf= array_value($thisBook[0], 'pdf');
    $epub= array_value($thisBook[0], 'epub');
    $translations= array_value($thisBook[0], 'translations');
    $textdirection= array_value($thisBook[0], 'textdirection');
    $analyzer= stripslashes(array_value($thisBook[0], 'analyzer'));
    $output = preg_replace("[<book-title/>]",$title,$hook_args);
    $output = preg_replace("[<book-dir/>]",$dirname,$output);
    $output = preg_replace("[<textdirection/>]",$textdirection,$output);
    $output = preg_replace("[<pdf-location/>]",$pdf,$output);
    $output = preg_replace("[<epub-location/>]",$epub,$output);
    $output = preg_replace("[<translation-links/>]",stripslashes($translations),$output);
    $output = preg_replace("[<analyzer/>]",$analyzer,$output);
  }
  return $output;
}

function read_initialize() {
  add_hook("before_display", "read_beforedisplay");
  add_hook("tag_replace", "read_tagreplace");
}

function read_install() {

}

function read_uninstall() {

}


function read_plugin() {
  return Array("info" => Array("author" => "Adam Hyde",
    "license" => "AGPL",
    "description" => "Basic plugin for displaying books.",
    "version" => "1.0")
  );
}
