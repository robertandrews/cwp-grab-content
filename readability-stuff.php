<?php

require __DIR__ . '/vendor/autoload.php';
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;
use fivefilters\Readability\Readability;




function grab_article()
{
/**
* Grab article from URL
* @return void
*/

// Check the nonce
if (!isset($_POST['grab_article_nonce']) || !wp_verify_nonce($_POST['grab_article_nonce'], 'grab_article')) {
print 'Sorry, your nonce did not verify.';
exit;
} else {
// process form data
}

// Form was posted
if (isset($_POST['submit'])) {

$article_url = $_POST['article_url'];
$response = wp_remote_get($article_url);
if (is_array($response) && !is_wp_error($response)) {
$html = wp_remote_retrieve_body($response);
}

$configuration = new Configuration([
'fixRelativeURLs' => true,
'ArticleByLine' => true,
]);

$readability = new Readability($configuration);

try {
$readability->parse($html);
// echo $readability;
print_r($readability->getAuthor());
} catch (ParseException $e) {
echo sprintf('Error processing text: %s', $e->getMessage());
}

}
}
add_action('admin_post_grab_article_action', 'grab_article');
