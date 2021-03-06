<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/../includes.php");
  $this->app->check_partial_include(__FILE__);

  // lists all anime.
  $params['group'] = isset($params['group']) ? $params['group'] : [];
  $params['title'] = isset($params['title']) ? $params['title'] : "Browse Anime";
  $params['aliases'] = isset($params['aliases']) ? $params['aliases'] : [];
  $params['wilsons'] = isset($params['wilsons']) ? $params['wilsons'] : [];
  $params['anime'] = isset($params['anime']) ? $params['anime'] : [];
  $params['numPages'] = isset($params['numPages']) ? $params['numPages'] : 1;

  $firstAnime = Anime::Get($this->app);

  $paginationArray = [];
  if (isset($_REQUEST['search'])) {
    $paginationArray['search'] = $_REQUEST['search'];
  }
  $paginationArray['page'] = '';
?>
<div class='row'>
  <h1><?php echo escape_output($params['title']); ?></h1>
  <?php echo $firstAnime->view('searchForm', ['form' => ['class' => 'form-inline pull-right']]); ?>
</div>
<?php echo $params['numPages'] > 1 ? paginate($firstAnime->url("index", Null, $paginationArray), intval($this->app->page), $params['numPages']) : ""; ?>

<div class='row'>
  <?php echo $this->view('grid', ['group' => $params['group'], 'wilsons' => $params['wilsons'], 'aliases' => $params['aliases']]); ?>
</div>

<?php echo $params['numPages'] > 1 ? paginate($firstAnime->url("index", Null, $paginationArray), intval($this->app->page), $params['numPages']) : ""; ?>
<?php echo $firstAnime->allow($this->app->user, 'new') ? $firstAnime->link("new", "Add an anime") : ""; ?>