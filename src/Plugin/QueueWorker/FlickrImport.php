<?php
namespace Drupal\flickr_json\Plugin\QueueWorker;

/**
 * Create node object from the imported XML content
 *
 * @QueueWorker(
 *   id = "flickr_json",
 *   title = @Translation("Import Content Flickr JSON"),
 *   cron = {"time" = 60}
 * )
 */
class FlickrImport extends FlickrJsonImport {}