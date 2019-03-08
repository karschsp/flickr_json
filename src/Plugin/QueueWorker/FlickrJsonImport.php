<?php

namespace Drupal\flickr_json\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Drupal\taxonomy\Entity\Term;

abstract class FlickrJsonImport extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  public function __construct(array $configuration, $plugin_id, $plugin_definition)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  public function processItem($item) {
    // TODO: Implement processItem() method.
    // Get the content array
    $content = $item->data;
    // Create node from the array
    $this->createContent($content);
  }

  private function checkNodeByTitle($id) {
    /* $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['title' => $id]);
    foreach ( $nodes as $node ) {
      return TRUE;
    }
    return FALSE; */
    $query = \Drupal::service('entity.query');
    $node_ids = $query->get('node')
      ->condition('type', 'photo')
      ->condition('status', 1)  // Once we have our conditions specified we use the execute() method to run the query
      ->condition('field_id', $id, '=')
      ->execute();

    if (count($node_ids) > 0) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  private function checkTermByTitle($title) {

  }

  private function createAlbumTerm($title) {
    $term = Term::create([
      'vid' => 'album',
      'name' => $title,
    ])->save();

  }

  protected function createContent($json_path) {
    $config = \Drupal::config('flickr_json.config');
    $jpg_location = $config->get('flickr_jpgs_location');
    $json = file_get_contents($json_path);
    // kint($json);
    // die();
    $arr = \GuzzleHttp\json_decode($json);
    if (!$this->checkNodeByTitle($arr->id)) {
      foreach ($arr->albums as $album) {
        // echo 'ABLUM: ' . $album->title;
        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('vid', "album");
        $query->condition('name', $album->title);
        $tids = $query->execute();
        if (!$tids) {
          $this->createAlbumTerm($album->title);
        }
      }
      // Create file object from remote URL.
      if (file_exists('public://' . $jpg_location . '/' . basename($arr->original))) {
        $data = file_get_contents('public://' . $jpg_location . '/' . basename($arr->original));
      }
      if ($data) {
        $file = file_save_data($data, 'public://' . basename($arr->original), FILE_EXISTS_REPLACE);
        $title = $arr->name;
        $new_title = preg_replace('/\s+/', '', $title);
        if ($new_title == '') {
          $title = basename($arr->original);
        }

        $media = Media::create([
          'bundle' => 'photography',
          'title' => $title,
          'field_image' => [
            'target_id' => $file->id(),
            'alt' => $title,
            'title' => $title,
          ],
        ]);
        $media->save();
        $node = Node::create([
          'type' => 'photo',
          'title' => $title,
          'created' => strtotime($arr->date_taken),
          'changed' => strtotime($arr->date_imported),
          'field_id' => $arr->id,
          'field_filename' => basename($arr->original),
          'field_album' => $tids,
          /* 'field_image' => [
            'target_id' => $file->id(),
            'alt' => $title,
            'title' => $title
          ], */
          'field_media_photograph' => [
            'target_id' => $media->id(),
          ],
        ]);
        // echo basename($arr->original);
        $node->save();
        return $node->id();
      }
    }



  }


}