<?php
error_reporting(0);

/*
these come from remix template
   cover : not used
   exportas : htmlzip.htmltar,pdf
   template :
   archive : not used
   pagesn : array of book__chapter
   title : the title of the book (if entered)
   comment : not used
   css : css sent for html
   pdfcss : css sent for pdf
   pdftitle : not used
   extra_desc_ : ennumerated string (extra_desc_0, extra_desc_1 etc) of page titles
*/

//function to find img tags and copy files
function getImages($page,$bookdir,$staticdir){
  $doc = new DOMDocument();
  @$doc->loadHTML($page);
  $xml=simplexml_import_dom($doc); // just to make xpath more simple
  $images=$xml->xpath('//img');
  foreach ($images as $img) {
  copy(BOOKI_DIR."/".$bookdir."/static/".basename($img['src']),$staticdir."/".basename($img['src']));
  }
}

//preg match to remove a class
function stripClass($name, $text) {
   $regex = '#<(\w+)\s[^>]*(class|id)\s*=\s*[\'"](' . $name .
            ')[\'"][^>]*>.*</\\1>#isU';
   return(preg_replace($regex, '', $text));
}

function remix_index() {
  $toctext = null;
  $menutext = null;
  $book = null;

  //if there is an export request then process
  if (array_value($_POST, 'pagesn')) {
	$title = array_value($_POST, 'title');
	if (!$title) $title = 'remixed_book';
    $requestPages = array_value($_POST, 'pagesn');
	//warning : the first array item is probably empty
	$pages=explode(",",$requestPages);
	$i=0;
	//lets make a nice array with the structure of the rmeix intact
	//0=bookname, 1=link or identifier for heading ,2=chapter/section name
	foreach ((array)$pages as $page){
		if ($page!=""){
        	$pageTitle = $_POST["extra_desc_".$i];
        	//echo $page;
		//echo $_POST["extra_desc_".$i];
		$page=$page."__".$pageTitle;
		$pageDetails[]= preg_split('[__]', $page);
		$i++;
		}
	}

    if (array_value($_POST, 'exportas')=="pdf"){
	//get the html for each page and remove the menu
	foreach ((array)$pageDetails as $pageDetail){
		$book.=@file_get_contents(BOOKI_DIR."/$pageDetail[0]/$pageDetail[1].txt");
		$book= preg_replace("[\"(static/.*)\"]", "\"../".BOOKI_DIR."/$pageDetail[0]/\\1\"", $book);
    	$book= preg_replace("[\"(".BOOKI_DIR."/.*)\"]", "\"../\\1\"", $book);
		$book=stripClass("menu-goes-here",$book);
	}
	//make temp dirs and make the pdf
	$book.="<style type=\"text/css\">\n";
	$book.=array_value($_POST, 'pdfcss');
	$book.="</style>";
  	$thisBook = tempnam("tmp/", "makebook_");
	$thisBookHTML="tmp/".basename($thisBook).".html";
	$thisBookPDF="tmp/".basename($thisBook).".pdf";
	file_put_contents($thisBookHTML,utf8_decode($book));
	$makecmd =$thisBookHTML."  ".$thisBookPDF;
	exec("lib/wkhtmltopdf-i386 -t $makecmd --cover data/remix/pdf_cover/cover.html", $results);
	//return the file
     	header("Cache-Control: public");
     	header("Content-Description: File Transfer");
     	header("Content-Disposition: attachment; filename=$title.pdf");
     	header("Content-Type: application/pdf");
     	header("Content-Transfer-Encoding: binary");

     // Read the file from disk
     readfile($thisBookPDF);
    }
    if ((array_value($_POST, 'exportas')=="htmlzip")
    || (array_value($_POST, 'exportas')=="htmltgz")){
	//build the menu
	$menu="<div id=\"index\"><div class=\"topics\"><ul>\n";
	foreach((array)$pageDetails as $pageDetail) {
		if ( (preg_match("[---]",$pageDetail[1]))||(preg_match("[TITLE]",$pageDetail[1]))) {
			$menu.="<li class=\"heading\">$pageDetail[2]</li>\n";
		} else {
			$menu.="<li ><a href=\"$pageDetail[1].html\">$pageDetail[2]</a></li>\n";
		}
		//echo $pageDetail[0]."   ".$pageDetail[1]."    ".$pageDetail[2];
	}
	$menu.="</ul></div></div>\n";
	//make randon dirname in tmp
	for ($i=0; $i<6; $i++) {
    		$d=rand(1,30)%2;
    		$randname.= $d ? chr(rand(65,90)) : chr(rand(48,57));
	}
	$tmpdir="tmp/bookdir_".$randname;
	mkdir ($tmpdir);
	$tmpbookdir=$tmpdir."/book";
	mkdir ($tmpbookdir);
	$staticdir=$tmpbookdir."/static";
	mkdir ($staticdir);

	//build the html for each page with the menu and then get the images
	foreach ((array)$pageDetails as $pageDetail){
		$book="<html><body>\n";
		$book.="<style type=\"text/css\">\n";
		$book.=array_value($_POST, 'css');
		$book.="</style>\n".$menu;
		$book.="<div id=\"content\">\n";
		$book.=@file_get_contents(BOOKI_DIR."/$pageDetail[0]/$pageDetail[1].txt");
    		//$book = preg_replace("[\"(static/.*)\"]", "\"../booki/$pageDetail[0]/\\1\"", $book);
		//$book= preg_replace("[\"(static/.*)\"]", "\"../booki/$pageDetail[0]/\\1\"", $book);
    		//$book= preg_replace("[\"(booki/.*)\"]", "\"../\\1\"", $book);
		$book=stripClass("menu-goes-here",$book);
		$book.="</div>\n";
		$book.="</html></body>";
		getImages($book,$pageDetail[0],$staticdir);
		$book= preg_replace("[\"(.*)(static/.*)\"]", "\"\\2\"", $book);
    		//$book= preg_replace("[\"(booki/.*)\"]", "\"../\\1\"", $book);
		file_put_contents($tmpbookdir."/".$pageDetail[1].".html",$book);
	}

    }

    //return the zip
    if (array_value($_POST, 'exportas')=="htmlzip"){
	shell_exec("cd $tmpdir;zip -r book.zip book/");

	header("Cache-Control: public");
     	header("Content-Description: File Transfer");
     	header("Content-Disposition: attachment; filename=book.zip");
     	header("Content-Type: application/zip");
     	header("Content-Transfer-Encoding: binary");

     // Read the file from disk
     readfile($tmpdir."/book.zip");
    }

    //return the tar
    if (array_value($_POST, 'exportas')=="htmltgz"){
	shell_exec("cd $tmpdir;tar cvvf book.tar book/;gzip book.tar");
	header("Cache-Control: public");
     	header("Content-Description: File Transfer");
     	header("Content-Disposition: attachment; filename=book.tar.gz");
     	header("Content-Type: application/x-compressed-tar");
     	header("Content-Transfer-Encoding: binary");

     // Read the file from disk
     readfile($tmpdir."/book.tar.gz");

    }

  }

  //build the arrays for the remix html
  $sectioncounter=1;
  $tableBooks = new Axon(DB_TABLE_BOOKS);
  $info = $tableBooks->find();
  foreach((array)$info as $info2) {
	$dirname=$info2["dir"];
	$menutext.="<option value=\"".$info2["dir"]."\">".$info2["title"]."</option>";

 	$file = $_SERVER['DOCUMENT_ROOT'].'/'.BOOKI_DIR."/$dirname/index.txt";
	$toctext.="PdfArrange.Base.web_list.push(\"$dirname\");\n";
	$toctext.="PdfArrange.Base.web_topics[\"$dirname\"] = new Array();\n";
	$toctext.="PdfArrange.Base.web_topics[\"$dirname\"].";
	$toctext.="push(new Array(\"".$dirname."__TITLE$sectioncounter\", \"TITLE$sectioncounter\", \"".utf8_decode($dirname)."\", \"2\"));\n";

	$doc = new DOMDocument();

	if (file_exists($file)) {
    	$doc->loadHTMLFile($file);
    	$xpath = new DOMXpath($doc);

    	$elements = $xpath->query("*/ul[@class='menu-goes-here']/li[@class='booki-section']|*/ul[@class='menu-goes-here']/li|*/ul[@class='menu-goes-here']/li/a/@href");
    	if (!is_null($elements)) {
      		foreach ($elements as $element) {
        			$nodes = $element->childNodes;
        				foreach ($nodes as $node) {
    					if ($element->nodeName=="li" && $node->nodeName=="#text"){
          						$toctext.= "PdfArrange.Base.web_topics[\"$dirname\"].";
    						$toctext.="push(new Array(\"".$dirname."__---".$sectioncounter."\",\"---".$sectioncounter."\",\"".utf8_decode($node->nodeValue)."\",\"0\"));\n";
          						$toc[]= "section,".$node->nodeValue."";
    						$section=$node->nodeValue;
    						$sectioncounter++;
    					}
    					if ($element->nodeName=="li" && $node->nodeName=="a"){
          						$chapter =$node->nodeValue;
    					}
    					if ($element->nodeName=="href" && $node->nodeName=="#text"){
          						$toc[$section][$chapter]= $node->nodeValue."";
          						$href =$node->nodeValue;
              					$toctext.="PdfArrange.Base.web_topics[\"$dirname\"].push(new Array(\"".$dirname."__".basename($href)."\", \"".basename($href)."\", \"".utf8_decode($chapter)."\", \"1\"));\n";
       					}
       				}
        }
    }
	}
  }

//build the final html
    $content = addTemplate('remix', null);
    $content = preg_replace("[<remix/>]", $toctext, $content);
    $content = preg_replace("[<remix-menu/>]", $menutext, $content);

    return $content;
}

function remix_dispatcher($name) {
  if($name === "index") return remix_index();
}


function remix_afterdisplay() {
  echo "</body></html>";
}

function remix_initialize() {
  add_hook("after_display", "pages_afterdisplay");
}

function remix_install() {

}

function remix_uninstall() {

}

function remix_plugin() {
  return Array("info" => Array("author" => "Adam Hyde",
			       "email" => "adam@flossmanuals.net",
			       "version" => "1.0",
			       "license" => "AGPL",
			       "description" => "Enables remixing of books and output to various formats.",
			       "requirements" => "Linux, wkhtmltopdf in lib dir (static compiled 9.9 or later)")
	       );
}
