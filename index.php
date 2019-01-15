<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>PHP-IMDB </title>
  <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Open+Sans:400,700">
  <h1> SCRAPING Most Popular Movies IMDB Dengan Regex</h1>
  <style>
    body {
      background-color: #E5D555;
      color: #222;
      font-family: "Open Sans", sans-serif;
      font-size: 15px;
      max-width: 1000px;
      margin: 20px auto;
      width: 100%;
    }

    p {
      margin: 0 0 10px;
      padding: 0;
    }

    hr {
      margin: 25px 0;
      border: 1px #000 solid;
      height: 1px;
      background: #FFF;
    }

    a {
      color: #222;
    }

    a:hover, a:focus, a:active {
      text-decoration: none;
      color: #222;
    }

    h1 {
      font-size: 32px;
      text-align: center;
      font-weight: 700;
    }
  </style>
  
</head>
<body>
<?php 
$page = file_get_contents('https://www.imdb.com/chart/moviemeter');
$reg = array();
preg_match_all('/<td class="titleColumn">[\s\S]*?<a href="([\s\S]*?)"[\s\S]*?>([\s\S]*?)<\/a>[\s\S]*?<span class="secondaryInfo">\((\d*?)\)<\/span>[\s\S]*?<\/td>/',$page, $reg);
$link = $reg[1];
$title = $reg[2];
$year = $reg[3];

echo '<table><tr><th>Judul</th><th>Tahun</th><th>sinopsis</th></tr>';
for($i=0; $i<count($link); $i++) {
  echo '<tr>';
  echo '<td>'.$title[$i].'</td>';
  echo '<td>'.$year[$i].'</td>';
  echo '</tr>';
}

 ?>
</body>
</html>