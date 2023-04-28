<?php

function wp_api_exists($article_url)
{
    /**
     * Check if the URL has a WP JSON API endpoint
     * @param string $article_url
     * @return bool
     */
    $domain = parse_url($article_url, PHP_URL_SCHEME) . '://' . parse_url($article_url, PHP_URL_HOST);
    $api_base_url = $domain . '/wp-json';
    $response = wp_remote_get($api_base_url);
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code === 200) {
        return true;
    }
    return false;
}

function wp_get_post_id_from_url($article_url)
{
    /**
     * Get the WordPress post ID from the URL
     * @param string $article_url
     * @return int
     */
    // use wp_remote_get to retrieve the page source code
    $response = wp_remote_get($article_url);
    $body = wp_remote_retrieve_body($response);

    // use a regular expression to find the post ID in the source code
    $pattern = '/\/wp-json\/wp\/v2\/posts\/(\d+)/';
    preg_match($pattern, $body, $matches);
    $post_id = $matches[1];

    return $post_id;
}



function wp_get_json_post($article_url, $wp_post_id)
{
    /**
     * Get the post data from the WP JSON API
     * @param string $article_url
     * @return object
     */

    // Generate /posts endpoint URL, eg. https://techcrunch.com/wp-json/wp/v2/posts/2525617?_embed=wp:term
    $domain = parse_url($article_url, PHP_URL_SCHEME) . '://' . parse_url($article_url, PHP_URL_HOST);
    $api_base_url = $domain . '/wp-json';
    $api_endpoint = '/wp/v2/posts';
    $api_params = '_embed=wp:term,wp:featuredmedia,author';
    $api_url = $api_base_url . $api_endpoint . '/' . $wp_post_id . '?' . $api_params;

    // use wp_remote_get to retrieve the post data
    $response = wp_remote_get($api_url);
    /*
    echo '<pre>';
    print_r($response);
    echo '</pre>';
     */

    if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {

        $post_json = json_decode(wp_remote_retrieve_body($response));

        return $post_json;

    } else {
        // Handle error.
    }

}
