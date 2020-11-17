<?php
//ini_set('display_errors', 1);

require __DIR__. '/Vendor/config/constants.php';
require __DIR__. '/Vendor/autoloader.php';

$myPDO = \Vendor\Factories\PDOMySQL::getInstance();

$date1 = !empty($_REQUEST['date1']) ? new DateTime($_REQUEST['date1']) : '';
$date2 = !empty($_REQUEST['date2']) ? new DateTime($_REQUEST['date2']) : '';

$data = [];

if(!$date1 || (!$date1 && !$date2) || ($date1 && $date2 && $date1 > $date2)) {
    header('Location: index.html');
}

// If we want to view stats for just one day
if($date1 && !$date2) {
    $q = $myPDO->prepare('SELECT l.lemma, lc.occurences FROM lemma l LEFT JOIN lemma_count lc ON l.id = lc.lemma_id WHERE tweet_date = :tweet_date ORDER BY lc.occurences DESC LIMIT 500');
    $q->bindParam('tweet_date', $_REQUEST['date1']);
    $q->execute();

    $data = $q->fetchAll();
} elseif($date1 && $date2) {
    $q = $myPDO->prepare('SELECT l.lemma, SUM(lc.occurences) AS occurences FROM lemma l LEFT JOIN lemma_count lc ON l.id = lc.lemma_id WHERE tweet_date BETWEEN :tweet_date_1 AND :tweet_date_2 GROUP BY l.lemma ORDER BY occurences DESC LIMIT 1000');
    $q->bindParam('tweet_date_1', $_REQUEST['date1']);
    $q->bindParam('tweet_date_2', $_REQUEST['date2']);
    $q->execute();

    $data = $q->fetchAll();
}

$maxScore = max(array_column($data, 'occurences')); // Score maximal obtenu

if(!$maxScore) {
    header('Location: index.html');
}

?>

<!DOCTYPE html>
<html>
<head>
    <script src="https://d3js.org/d3.v3.min.js"></script>
    <script src="js/d3.layout.cloud.js"></script>
    <title><?= strip_tags($_REQUEST['date1']) ?> <?= $date2 ? 'and '. strip_tags($_REQUEST['date2']) : '' ?> trends - US Election 2020</title>
    <link rel="stylesheet" href="css/sheet.css">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans&display=swap" rel="stylesheet">
</head>
<header>
    <h1><a href="index.html">⭠ Go back to homepage</a></h1>
</header>
<h3>5 Most used words (<?= strip_tags($_REQUEST['date1']) ?><?= ($date2) ? '/'. strip_tags($_REQUEST['date2']) : '' ?>)</h3>

<table>
    <tr>
        <th>Lemma</th>
        <th>Occurences</th>
        <th>% of usage</th>
    </tr>

    <?php
    for($i = 0; $i < 5; $i++) {
        $ret = '<tr>';
        $ret .= '<td>' . $data[$i]['lemma']. '</td>';
        $ret .= '<td>' . $data[$i]['occurences']. '</td>';
        $ret .= '<td>' . round(($data[$i]['occurences'] / $maxScore) * 100) .'</td>';
        echo $ret;
    }
    ?>
</table>

<h2>Tagcloud</h2>
<noscript>You should enable Javascript to view the tagcloud 😕 !</noscript>
</body>
<script>
    var frequency_list = [ <?php
    for($i = 0; $i < count($data); $i++) {
        $score = ($data[$i]['occurences'] / $maxScore) * 100; // On situe le nombre d'occurences du lemme par rapport à celui apparu le plus
        $score = ceil($score/10) * 10; // On passe à la dizaine supérieure pour l'affichage

        echo '{"text":"' . $data[$i]['lemma'] . '","size":'. $score * 1.1 .'}';
        if ($i < count($data)) {
            echo ',';
        }
    }
    ?>];

    var color = d3.scale.linear()
        .domain([10,20,30,40,50,60,70,80,90,100])
        .range(["purple", "blue", "#fff", "#f2f2f2", "#e6e6e6", "#888", "#dedede", "#dedede", "#555", "#555", "#555"]);

    d3.layout.cloud().size([1200, 600])
        .words(frequency_list)
        .rotate(-5)
        .fontSize(function(d) { return d.size * 1.4; }) // On agrandit la taille de la font...
        .on("end", draw)
        .start();

    function draw(words) {
        d3.select("body").append("svg")
            .attr("width", 1200)
            .attr("height", 700)
            .attr("margin", "0 auto")
            .attr("class", "wordcloud")
            .append("g")
            // without the transform, words words would get cutoff to the left and top, they would
            // appear outside of the SVG area
            .attr("transform", "translate(600,400)")
            .selectAll("text")
            .data(words)
            .enter().append("text")
            .style("font-size", function(d) { return d.size + "px"; })
            .style("fill", function(d, i) { return color(i); })
            .attr("transform", function(d) {
                return "translate(" + [d.x, d.y] + ")rotate(" + d.rotate + ")";
            })
            .text(function(d) { return d.text; });
    }
</script>
</html>