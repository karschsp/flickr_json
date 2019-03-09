<?php

namespace Drupal\flickr_json\Controller;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;
use \Drupal\node\Entity\Node;
use \Drupal\media\Entity\Media;
use \Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\Core\Queue\QueueFactory;

/**
 * You can use this constant to set how many queued items
 * you want to be processed in one batch operation
 */
define("IMPORT_BATCH_SIZE", 5);

class FlickrJsonController extends ControllerBase {

  /**
   * We add QueueFactory and QueueWorkerManager services with the Dependency Injection solution
   */

  /**
   * @var QueueFactory
   */
  protected $queueFactory;

  /**
   * @var QueueWorkerManager
   */
  protected $queueManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(QueueFactory $queue_factory, QueueWorkerManager $queue_manager) {
    $this->queue_factory = $queue_factory;
    $this->queue_manager = $queue_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $queue_factory = $container->get('queue');
    $queue_manager = $container->get('plugin.manager.queue_worker');

    return new static($queue_factory, $queue_manager);
  }

  public function importAlbums() {
    $output = '';
    $config = \Drupal::config('flickr_json.config');
    $json_location = $config->get('flickr_json_location');
    $album_json = file_get_contents('public://' . $json_location . '/albums.json');

    $albums_json = \GuzzleHttp\json_decode($album_json);
    foreach ($albums_json as $albums) {
      foreach ($albums as $album) {

        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('vid', "album");
        $query->condition('name', $album->title);
        $tids = $query->execute();
        if (!$tids) {
          $term = $this->createAlbumTerm($album->title, $album->description);
          $tids[] = $term->tid;
          $output .= '<p>creating ' . $album->title . ' with description . ' . $album->description . '</p>';
        } else {
          $output .= '<p><em>album ' . $album->title . ' already exists with termID ' . implode(',', $tids) . '</em></p>';
        }

        foreach ($album->photos as $photo) {
          $query = \Drupal::entityQuery('node')
            ->condition('field_id', $photo)
            ->condition('type', 'photo');

          $nids = $query->execute();
          foreach ($nids as $nid) {
            if ($nid) {
              $node = Node::load($nid);
              $node->field_album = $tids;
              $node->save();

              $output .= 'updating NODE ' . (string)$nid;
            }
          }
        }
      }
    }
    return [
      '#markup' => $this->t($output)
    ];

  }

  /**
   * Process all queue items with batch
   */
  public function processAllQueueItemsWithBatch() {

    // Create batch which collects all the specified queue items and process them one after another
    $batch = array(
      'title' => $this->t("Process all Flickr JSON Import queues with batch"),
      'operations' => array(),
      'finished' => 'Drupal\flickr_json\Controller\FlickrJsonController::batchFinished',
    );

    // Get the queue implementation for flickr_json queue
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get('flickr_json');

    // Count number of the items in this queue, and create enough batch operations
    for($i = 0; $i < ceil($queue->numberOfItems() / IMPORT_BATCH_SIZE); $i++) {
      // Create batch operations
      $batch['operations'][] = array('Drupal\flickr_json\Controller\FlickrJsonController::batchProcess', array());
    }

    // Adds the batch sets
    batch_set($batch);
    return batch_process('<front>');
  }

  public function importJsonFiles() {
    $output = '';
    $nids = [];
    $config = \Drupal::config('flickr_json.config');

    $json_location = $config->get('flickr_json_location');
    $jpg_location = $config->get('flickr_jpgs_location');
    $folder = 'public://' . $json_location;
    $it = new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS);
    $count = 0;
    /* foreach ($contents as $content) {
      // Get the queue implementation for import_content_from_xml queue
      $queue = $this->queue_factory->get('import_content_from_xml');

      // Create new queue item
      $item = new \stdClass();
      $item->data = $content;
      $queue->createItem($item);
    }  */
    foreach($it as $path) {
      if (strstr($path, '.json') && strstr($path, 'photo_')) {
        $queue = $this->queue_factory->get('flickr_json');

        // Create new queue item
        $item = new \stdClass();

        $item->data = 'public://' . $json_location . '/' . basename($path);
        $output .= $item->data . '<br />';
        // kint($item);
        $queue->createItem($item);
        // $nids[] = $this->createNodeFromJson($path);
        $count++;
      }
    }

    return array(
      '#type' => 'markup',
      '#markup' => $output . $this->t('@count queue items are created.', array('@count' => $count)),
    );
    /* foreach ($nids as $nid) {
      $output .= 'Create node with nid ' . $nid . '<br />';
    }
    return [
      '#markup' => $output,
    ]; */

  }

  /* private function createNodeFromJson($json_path) {
    $config = \Drupal::config('flickr_json.config');
    $jpg_location = $config->get('flickr_jpgs_location');
    $json = file_get_contents($json_path);
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

          'field_media_photograph' => [
            'target_id' => $media->id(),
          ],
        ]);
        // echo basename($arr->original);
        $node->save();
        return $node->id();
      }
    }
  } */

  /* private function checkNodeByTitle($id) {

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

  } */

  private function createAlbumTerm($title, $description) {
    $term = Term::create([
      'vid' => 'album',
      'name' => $title,
      'description' => $description,
    ])->save();
    return $term;

  }

  /**
   * Common batch processing callback for all operations.
   */
  public static function batchProcess(&$context) {

    // We can't use here the Dependency Injection solution
    // so we load the necessary services in the other way
    $queue_factory = \Drupal::service('queue');
    $queue_manager = \Drupal::service('plugin.manager.queue_worker');

    // Get the queue implementation for import_content_from_xml queue
    $queue = $queue_factory->get('flickr_json');
    // Get the queue worker
    // $queue_worker = $queue_manager->createInstance('flickr_json', $queue_factory, $queue_manager);
    $queue_worker = $queue_manager->createInstance('flickr_json');

    // Get the number of items
    $number_of_queue = ($queue->numberOfItems() < IMPORT_BATCH_SIZE) ? $queue->numberOfItems() : IMPORT_BATCH_SIZE;
    // kint($number_of_queue);die();
    // Repeat $number_of_queue times
    for ($i = 0; $i < $number_of_queue; $i++) {
      // Get a queued item
      if ($item = $queue->claimItem()) {
        try {
          // Process it

          $queue_worker->processItem($item->data);
          // If everything was correct, delete the processed item from the queue
          $queue->deleteItem($item);
        }
        catch (SuspendQueueException $e) {
          // If there was an Exception thrown because of an error
          // Releases the item that the worker could not process.
          // Another worker can come and process it
          $queue->releaseItem($item);
          break;
        }
      }
    }
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      drupal_set_message(t("The contents are successfully imported from the JSON source."));
    }
    else {
      $error_operation = reset($operations);
      drupal_set_message(t('An error occurred while processing @operation with arguments : @args', array('@operation' => $error_operation[0], '@args' => print_r($error_operation[0], TRUE))));
    }
  }
}