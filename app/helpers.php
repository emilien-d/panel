<?php

function layout($file, $data = array()) {
  return new Layout($file, $data);
}

function view($file, $data = array()) {
  return new View($file, $data);
}

function blueprint($page) {
  return $page->blueprint();
}

function fileResponse($file, $child = false) {

  $meta = $file->meta(app::$language);

  $result = array(
    'filename'  => $file->filename(),
    'name'      => $file->name(),
    'extension' => $file->extension(),
    'size'      => $file->niceSize(),
    'type'      => $file->type() ? $file->type() : 'unknown',
    'url'       => $file->url()
  );

  if(!$child) {

    $result['prev'] = $file->prev() ? fileResponse($file->prev(), true) : false;
    $result['next'] = $file->next() ? fileResponse($file->next(), true) : false;
    $result['meta'] = array_map(function($field) {
      return (string)$field;
    }, $meta->data());

    if(empty($result['meta'])) $result['meta'] = null;

    try {
      $result['thumb'] = thumb($file, array('width' => 300, 'height' => 200, 'crop' => true))->url();
    } catch(Exception $e) {
      $result['thumb'] = $result['url'];
    }

  }

  return $result;

}

function pageResponse($page, $child = false) {

  $content = $page->content(app::$language);
  $title   = (string)$content->title();

  $result = array(
    'title'    => !empty($title) ? $title : $page->uid(),
    'url'      => $page->url(app::$language),
    'uri'      => $page->uri(),
    'uid'      => $page->uid(),
    'slug'     => $page->slug(),
    'home'     => $page->isHomePage(),
    'error'    => $page->isErrorPage(),
    'visible'  => $page->isVisible(),
    'template' => $page->template()
  );


  // if there's a blueprint for the intended page template, use that!
  if(c::get('root.blueprints') . DS . $page->intendedTemplate() . '.php') {
    $result['template'] = $page->intendedTemplate();
  }

  if(!$page->isSite()) {

    if(!$child) {
      $result['parent']  = pageResponse($page->parent(), true);
      $result['parents'] = $page->parents()->toArray(function($item) {
        return pageResponse($item, true);
      });
    }

    if($parentBlueprint = $page->parent()->blueprint()) {
      switch($parentBlueprint->num()->mode()) {
        case 'zero':
          $result['num'] = '';
          break;
        case 'date':
          $result['num'] = date('Y/m/d', strtotime($page->num()));
          break;
        default:
          $result['num'] = intval($page->num());
          break;
      }
    }

  }

  if(!$child) {

    $children = $page->children();

    if($blueprint = $page->blueprint()) {

      if($pages = $blueprint->pages()) {
        if($pages->sort() == 'flip') {
          $children = $children->flip();
        }
      }

      $result['settings'] = array(
        'pages' => $blueprint->pages(),
        'files' => $blueprint->files()
      );

    } else {
      $result['settings'] = array(
        'pages' => array(
          'limit' => 20
        ),
        'files' => true
      );
    }

    $result['children'] = array_values($children->toArray(function($item) {
      return pageResponse($item, true);
    }));

    $result['content'] = array_map(function($field) {
      return (string)$field;
    }, $content->data());

    if(empty($result['content'])) $result['content'] = array();

    $result['files'] = array_values($page->files()->toArray(function($file) {
      return fileResponse($file);
    }));

    $folder   = new Folder($page->root());
    $writable = true;

    if($folder->isWritable()) {
      foreach($folder->files() as $f) {
        if(!$f->isWritable()) {
          $writable = false;
          break;
        }
      }
    } else {
      $writable = false;
    }

    $result['writable'] = array(
      'status'  => $writable,
      'message' => 'The page is not writable'
    );

  }

  $result['deletable'] = array(
    'status'  => true,
    'message' => 'This page can be deleted'
  );

  $result['changeableURL'] = array(
    'status'  => true,
    'message' => 'The URL for this page can be changed'
  );

  // deletable status
  if($page->isSite()) {
    $result['deletable'] = array(
      'status'  => false,
      'message' => 'The site cannot be deleted'
    );
    $result['changeableURL'] = array(
      'status'  => false,
      'message' => 'The URL for the site cannot be changed'
    );
  }

  if($page->isErrorPage()) {
    $result['deletable'] = array(
      'status'  => false,
      'message' => 'The error page cannot be deleted'
    );
    $result['changeableURL'] = array(
      'status'  => false,
      'message' => 'The URL for the error page cannot be changed'
    );
  }

  if($page->isHomePage()) {
    $result['deletable'] = array(
      'status'  => false,
      'message' => 'The home page cannot be deleted'
    );
    $result['changeableURL'] = array(
      'status'  => false,
      'message' => 'The URL for the home page cannot be changed'
    );
  }

  if($page->hasChildren()) {
    $result['deletable'] = array(
      'status'  => false,
      'message' => 'This page has subpages. Please delete them first'
    );
  }

  return $result;

}