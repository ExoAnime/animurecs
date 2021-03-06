<?php
class TagType extends BaseObject {
  public static $TABLE = "tag_types";
  public static $PLURAL = "tagTypes";
  public static $FIELDS = [
    'id' => [
      'type' => 'int',
      'db' => 'id'
    ],
    'name' => [
      'type' => 'str',
      'db' => 'name'
    ],
    'description' => [
      'type' => 'str',
      'db' => 'description'
    ],
    'createdAt' => [
      'type' => 'date',
      'db' => 'created_at'
    ],
    'updatedAt' => [
      'type' => 'date',
      'db' => 'updated_at'
    ],
    'createdUserId' => [
      'type' => 'int',
      'db' => 'created_user_id'
    ]
  ];
  public static $JOINS = [
    'tags' => [
      'obj' => 'Tag',
      'table' => 'tags',
      'own_col'  => 'id',
      'join_col'  => 'tag_type_id',
      'type' => 'many'
    ],
    'createdUser' => [
      'obj' => 'User',
      'table' => 'users',
      'own_col'  => 'created_user_id',
      'join_col' => 'id',
      'type' => 'one'
    ]
  ];

  public function __construct(Application $app, $id=Null) {
    parent::__construct($app, $id);
    if ($id === 0) {
      $this->name = $this->description = "";
      $this->tags = [];
      $this->createdUser = Null;
    }
  }
  public function pluralName() {
    return $this->name."s";
  }
  public function allow(User $authingUser, $action, array $params=Null) {
    // takes a user object and an action and returns a bool.
    switch($action) {
      // case 'approve':
      case 'new':
      case 'edit':
      case 'delete':
        if ($authingUser->isAdmin()) {
          return True;
        }
        return False;
        break;
      case 'show':
      case 'index':
        return True;
        break;
      default:
        return False;
        break;
    }
  }
  public function validate(array $tag_type) {
    $validationErrors = [];
    try {
      parent::validate($tag_type);
    } catch (ValidationException $e) {
      $validationErrors = array_merge($validationErrors, $e->messages);
    }
    if (!isset($tag_type['name']) || mb_strlen($tag_type['name']) < 1) {
      $validationErrors[] = "Tag type must have a non-blank title";
    }
    if (isset($tag_type['description']) && (mb_strlen($tag_type['description']) < 1 || mb_strlen($tag_type['description']) > 600)) {
      $validationErrors[] = "Tag type must have a description between 1 and 600 characters";
    }
    if (!isset($tag_type['created_user_id']) || !is_integral($tag_type['created_user_id']) || intval($tag_type['created_user_id']) <= 0) {
      $validationErrors[] = "Created user ID must be valid";
    } else {
      try {
        $approvedUser = new User($this->app, intval($tag_type['created_user_id']));
        $approvedUser->getInfo();
      } catch (Exception $e) {
        $validationErrors[] = "Created user ID must exist";
      }
    }
    if ($validationErrors) {
      throw new ValidationException($this->app, $tag_type, $validationErrors);
    } else {
      return True;
    }
  }
  public function create_or_update(array $tag_type, array $whereConditions=Null) {
    // creates or updates a tag type based on the parameters passed in $tag_type and this object's attributes.
    // returns False if failure, or the ID of the tag type if success.
    // make sure tag type name adheres to standards.
    $tag_type['name'] = str_replace("_", " ", strtolower($tag_type['name']));
    return parent::create_or_update($tag_type);
  }
  public function isApproved() {
    // Returns a bool reflecting whether or not the current anime is approved.
    // doesn't do anything for now. maybe use later.
    /* 
    if ($this->approvedOn === '' or !$this->approvedOn) {
      return False;
    }
    return True;
    */
  }
  public function getApprovedUser() {
    // retrieves an id,name array corresponding to the user who approved this anime.
    // return $this->app->dbConn->firstRow("SELECT `users`.`id`, `users`.`name` FROM `anime` LEFT OUTER JOIN `users` ON `users`.`id` = `anime`.`approved_user_id` WHERE `anime`.`id` = ".intval($this->id));
  }

  public function render() {
    if ($this->app->action == 'new' || $this->app->action == 'edit') {
      if (isset($_POST['tag_types']) && is_array($_POST['tag_types'])) {
        $updateTagType = $this->create_or_update($_POST['tag_types']);
        if ($updateTagType) {
          $this->app->delayedMessage("Successfully updated.", "success");
          $this->app->redirect($this->url("show"));
        } else {
           $this->app->delayedMessage("An error occurred while creating or updating this tag type.", "error");
          $this->app->redirect($this->id === 0 ? $this->url("new") : $this->url("edit"));
        }
      }
    }
    switch($this->app->action) {
      case 'new':
        $title = "Create a Tag Type";
        $output = $this->view('new');
        break;
      case 'edit':
        if ($this->id == 0) {
          $this->app->display_error(404);
        }
        $title = "Editing ".escape_output($this->name);
        $output = $this->view('edit');
        break;
      case 'show':
        if ($this->id == 0) {
          $this->app->display_error(404);
        }
        $title = escape_output($this->name);
        $output = $this->view('show');
        break;
      case 'delete':
        if ($this->id == 0) {
          $this->app->display_error(404);
        }
        if (!$this->app->checkCSRF()) {
          $this->app->display(403);
        }
        $deleteTagType = $this->delete();
        if ($deleteTagType) {
          $this->app->delayedMessage('Successfully deleted '.$this->name.'.', "success");
          $this->app->redirect();
        } else {
          $this->app->delayedMessage('An error occurred while deleting '.$this->name.'.', 'error');
          $this->app->redirect();
        }
        break;
      default:
      case 'index':
        $title = "Tag Types";
        $perPage = 25;

        if ($this->app->user->isAdmin()) {
          $pages = ceil(TagType::Count($this->app)/$perPage);
          $tagTypes = $this->app->dbConn->table(TagType::$TABLE)->fields('id')->order('name')->offset((intval($this->app->page)-1)*$perPage)->limit($perPage)->query();
        } else {
          $pages = ceil(TagType::Count($this->app, ['approved_on != ""'])/$perPage);
          $tagTypes = $this->app->dbConn->table(TagType::$TABLE)->fields('id')->where(['approved_on != ""'])->order('name ASC')->offset((intval($this->app->page)-1)*$perPage)->limit($perPage)->query();
        }

        $output = $this->view('index',[
          'perPage' => $perPage,
          'pages' => $pages,
          'tagTypes' => $tagTypes
        ]);
        break;
    }
    return $this->app->render($output, ['subtitle' => $title]);
  }
}
?>