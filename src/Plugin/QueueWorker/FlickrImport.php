<?php
namespace Drupal\flickr_json\Plugin\QueueWorker;

/**
 * Create node object from the imported XML content
 *
 * @QueueWorker(
 *   id = "flickr_json",
 *   title = @Translation("Import Content Flickr JSON"),
 * )
 */
class FlickrImport extends FlickrJsonImport {}