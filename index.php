<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>PHP-IMDB </title>
  <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Open+Sans:400,700">
  
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
include 'imdb.class.php';

$aTests = [
    'https://www.imdb.com/title/tt4633694/',
    'https://www.imdb.com/title/tt0110357/'];
    
set_time_limit(count($aTests) * 15);

$i = 0;
foreach ($aTests as $sMovie) {
    $i++;
    $oIMDB = new IMDB($sMovie);
    if ($oIMDB->isReady) {
        echo '<h1>' . $sMovie . '</h1>';
        foreach ($oIMDB->getAll() as $aItem) {
            echo '<p><b>' . $aItem['name'] . '</b>: ' . $aItem['value'] . '</p>';
        }
    } else {
        echo '<p><b>Movie not found</b>: ' . $sMovie . '</p>';
    }
    echo '<hr>';
}
?>
</body>
</html>
