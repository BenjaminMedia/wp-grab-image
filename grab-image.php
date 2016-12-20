<?php
/**
 * @package grab-image
 * Plugin Name: Grab Image
 * Version: 3.3
 * Description: Grab images of img tags are re-uploads them to be located on the site.
 * Author: Niteco
 * Author URI: http://niteco.se/
 * Plugin URI: PLUGIN SITE HERE
 * Text Domain: grab-image
 * Domain Path: /languages
 */

defined('ALLOW_UNFILTERED_UPLOADS') or define('ALLOW_UNFILTERED_UPLOADS', true);

// no limit time
ini_set('max_execution_time', 3000);
error_reporting(E_ERROR);

// start up the engine
add_action('admin_menu'             , 'grab_image_page');
add_action('wp_ajax_grab_image'     , 'ajax_grab_image_post');
add_action('wp_ajax_feature_image'  , 'ajax_feature_image_post');
add_action('wp_ajax_download_image' , 'ajax_download_image');
add_action('wp_ajax_attach_image'   , 'ajax_attach_image_post');
add_action('wp_ajax_search_image'   , 'ajax_search_image_post');
add_action('wp_ajax_regex_image'    , 'ajax_regex_image');
add_action('wp_ajax_recover_image'  , 'ajax_recover_image_post');

// require helper
require_once dirname(__FILE__) . '/libs/helper.php';

// trigger save_post
function grab_image_save_post($post_id)
{
    // if this is just a revision, don't do anything
    if (wp_is_post_revision($post_id)) {
        return;
    }

    // if not post, don't do anything
    if (get_post_type($post_id) != 'post') {
        return;
    }

    // check it's not an auto save routine
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // perform permission checks! for example:
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // fix loop issue
    remove_action('save_post', 'grab_image_save_post');

    $_REQUEST['id'] = $post_id;
    ajax_grab_image_post(true);

    // rehook save_post
    add_action('save_post', 'grab_image_save_post');
}

add_action('save_post', 'grab_image_save_post');

/**
 * define new menu page parameters
 */
function grab_image_page()
{
    add_menu_page('Grab Image', 'Grab Image', 'activate_plugins', 'grab-image', 'grab_image_run', '');
}

/**
 * plugin page
 */
function grab_image_run()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    } else {
        require_once dirname(__FILE__) . '/views/page.php';
    }
}

/**
 * @package download-image
 * ajax function to download image
 */
function ajax_download_image()
{
    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    $full_url = wp_get_attachment_image_url($id, 'full');

    if (!$full_url) {
        echo 'Error, file does not exists';
    } else {
        $helper = new grabimage_helper();
        echo "<a href='$full_url' target='_blank'>$full_url</a>";

        $sizes = array(
            'full',
            'large',
            'medium',
            'thumbnail'
        );
        // download attachment
        foreach ($sizes as $size) {
            echo '<br/>' . ucfirst($size) . ' size,';
            $url = wp_get_attachment_image_url($id, $size);
            $helper->media_download($url);
        }
    }

    wp_die();
}

/**
 * @package download-image
 * ajax function to download image
 */
function ajax_regex_image()
{
    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    $content = $post->post_content;
    $pattern = '/"http:\/\/wp-uploads.interactives.dk\/(.*?)\/uploads\/([0-9]*)\/([0-9]*)\/[0-9]*\/(.[^"]*)"/';
    preg_match_all($pattern, $content, $matches);

    $search = $replace = array();
    foreach ($matches[0] as $i => $match) {
        if (!in_array($match, $search)) {
            $search[] = $match;
            $link = home_url() . "/wp-content/uploads/{$matches[2][$i]}/{$matches[3][$i]}/{$matches[4][$i]}";
            $replace[] = '"' . $link . '"';

            // print to browser
            echo "<a href='{$link}' target='_blank'>{$link}</a>";
            echo "<br/>";
        }
    }

    if (count($replace)) {
        $content = str_replace($search, $replace, $content);
        if (!empty($content)) {
            // fix loop issue
            remove_action('save_post', 'grab_image_save_post');

            wp_update_post([
                'ID' => $post->ID,
                'post_content' => $content,
            ]);

            // rehook save_post
            add_action('save_post', 'grab_image_save_post');
        }
        echo count($replace) . ' urls were replaced';
    } else {
        echo '0 url was replaced';
    }

    wp_die();
}

/**
 * @package grab-image
 * ajax function to grab image
 */
function ajax_grab_image_post($trigger = false)
{
    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    // call helper class
    $helper = new grabimage_helper();

    // extract image tag from post_content
    $array = $helper->extract_image($post->post_content);

    $search = $replace = [];
    $count = 0;
    if (is_array($array) && count($array) > 0) {
        foreach ($array as $tag => $url) {
            // refine url
            $url = $helper->reconstruct_url($url);

            // real url
            $url = $helper->real_filename($url);

            // check if attachment id exist
            $attachment_id = $helper->get_attachment_id($url);

            if (empty($attachment_id)) {
                // init file
                $file = array();
                $file['name'] = $helper->clean_basename($helper->basename($url));

                // get uploads path
                $time = current_time('mysql');
                if (substr($post->post_date, 0, 4) > 0) {
                    $time = $post->post_date;
                }
                $uploads = wp_upload_dir($time);

                // check if image exist on hard disk
                if (!file_exists($uploads['path'] . '/' . $file['name'])) {
                    $exist = false;

                    // sideload image
                    $attachment_id = $helper->media_download_sideload($url, $post->ID);
                } else {
                    $exist = true;

                    // insert attachment
                    $attachment_id = $helper->media_update_sideload($url, $post->ID);
                }

                // ignore if it has any error
                if (is_wp_error($attachment_id)) {
                    continue;
                }
            }

            // ignore if empty attachment_id
            if (empty($attachment_id)) {
                continue;
            }

            // search a|img tag
            $search[] = $tag;

            // replace with
            $image = wp_get_attachment_image_src($attachment_id, 'full');
            $id = $attachment_id;
            $src = $image[0];
            $replace[] = "<a href=\"{$src}\" rel=\"attachment wp-att-{$id}\">"
                . "<img class=\"alignnone size-full wp-image-{$id}\" src=\"{$src}\" alt=\"{$post->post_title}\" />"
                . "</a>";

            // return message
            $exist = ($exist ? 'did exist' : 'didn\'t exist');
            echo "<a href=\"" . get_edit_post_link($attachment_id) . "\">{$url}</a> <b>{$exist}</b> already.<br/>";
        }

        // update post data search | replace
        if (count($search) > 0 && count($replace) > 0) {
            $post_content = str_replace($search, $replace, $post->post_content);
            $my_post = [
                'ID' => $post->ID,
                'post_content' => $post_content,
            ];
            wp_update_post($my_post);
        }

        $count = count($search);
    }

    // set post thumbnail if not exist
    $helper->set_post_thumbnail($post->ID);

    if (!$trigger) {
        echo 'Found ' . $count . ' new image';
        wp_die();
    }
}

/**
 * @package grab-image
 * ajax function to attach image
 */
function ajax_attach_image_post()
{
    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    // call helper class
    $helper = new grabimage_helper();

    // extract image tag from post content
    $array = $helper->extract_image($post->post_content, false);

    $search = $replace = [];
    $count = 0;
    if (is_array($array) && count($array) > 0) {
        foreach ($array as $tag => $url) {
            // get attachment ID from url
            $attachment_id = $helper->get_attachment_id($url);
            if (empty($attachment_id)) {
                continue;
            }

            // get post attachment
            $attachments = get_attached_media('image', $post->ID);

            // ignore if image was attachment
            if (isset($attachments[$attachment_id])) {
                continue;
            }

            wp_update_post([
                'ID' => $attachment_id,
                'post_parent' => $post->ID,
            ]);

            // search a|img tag
            $search[] = $tag;

            // replace with
            $image = wp_get_attachment_image_src($attachment_id, 'full');
            $id = $attachment_id;
            $src = $image[0];
            $replace[] = "<a href=\"{$src}\" rel=\"attachment wp-att-{$id}\">"
                . "<img class=\"alignnone size-full wp-image-{$id}\" src=\"{$src}\" alt=\"{$post->post_title}\" />"
                . "</a>";

            echo "success ; {$url} ; {$attachment_id} <br/>";
        }

        // update post data
        if (count($search) > 0 && count($replace) > 0) {
            $post_content = str_replace($search, $replace, $post->post_content);
            $my_post = [
                'ID' => $post->ID,
                'post_content' => $post_content,
            ];
            wp_update_post($my_post);
        }

        $count = count($search);
    }

    echo 'Attached ' . $count . ' image';
    wp_die();
}

/**
 * @package grab-image
 * ajax function to search / replace image
 */
function ajax_search_image_post()
{
    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    $search_str = (string)$_REQUEST['search'];
    $replace_str = (string)$_REQUEST['replace'];

    // call helper class
    $helper = new grabimage_helper();

    // extract image tag from post_content
    $array = $helper->extract_image($post->post_content, false);

    $search = $replace = [];
    $count = 0;
    if (is_array($array) && count($array) > 0) {
        foreach ($array as $tag => $url) {
            if (strpos($url, $search_str) === false) {
                continue;
            }

            $search[] = $tag;
            $replace[] = str_replace($search_str, $replace_str, $tag);

            echo "success ; {$url} <br/>";
        }

        // update post data
        if (count($search) > 0 && count($replace) > 0) {
            $post_content = str_replace($search, $replace, $post->post_content);
            $my_post = [
                'ID' => $post->ID,
                'post_content' => $post_content,
            ];
            wp_update_post($my_post);
        }

        $count = count($search);
    }

    echo 'Searched / Replaced ' . $count . ' image';
    wp_die();
}

/**
 * @package grab-image
 * ajax function to recover images by restoring last revisions
 */
function ajax_recover_image_post()
{
    $post_id = intval($_REQUEST['id']);
    $post = get_post($post_id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    if (strpos($post->post_content, 'class="alignnone size-full wp-image-"') === false) {
        echo 'Post isn\'t needed to recover';
        return;
    }

    // get post revision
    $found = false;
    $revisions = wp_get_post_revisions($post);
    foreach ($revisions as $revision) {
        if ($revision->ID != $post->ID
            && !empty($revision->post_content)
            && strpos($revision->post_content, '<img') !== false
            && strpos($revision->post_content, 'class="alignnone size-full wp-image-"') === false
            && strpos($revision->post_content, 'wp-uploads.interactives.dk') === false) {
            // remove dirty backlinks
            if (strpos($revision->post_content, 'position: absolute') !== false) {
                $post_content = preg_replace('/<div style=\'position: absolute;.*?>.*?<\/div>/', '', $revision->post_content);
            } else {
                $post_content = $revision->post_content;
            }
            wp_update_post([
                'ID' => $post->ID,
                'post_content' => $post_content,
            ]);
            $found = true;
            break;
        }
    }

    if ($found) {
        echo 'Post was recovered';
    } else {
        echo 'Post wasn\'t recovered';
    }

    exit();
}

/**
 * @package grab-image
 * ajax function to update featured image
 */
function ajax_feature_image_post()
{
    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    // call helper class
    $helper = new grabimage_helper();

    // set post thumbnail if not exist
    $has_thumb = $helper->set_post_thumbnail($post->ID);

    if (!$has_thumb) {
        echo 'Error, featured image wasn\'t found';
    } else {
        echo 'Success, featured image was found';
    }

    wp_die();
}