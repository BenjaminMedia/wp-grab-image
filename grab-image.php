<?php
/**
 * @package grab-image
 * Plugin Name: grab-image
 * Version: 1.1
 * Description: Grabs images of img tags are re-uploads them to be located on the site.
 * Author: Niteco
 * Author URI: http://niteco.se/
 * Plugin URI: PLUGIN SITE HERE
 * Text Domain: grab-image
 * Domain Path: /languages
 */

define('ALLOW_UNFILTERED_UPLOADS', true);

// no limit time
ini_set('max_execution_time', 300);
error_reporting(E_ERROR);

// start up the engine
add_action('admin_menu'                 , 'grab_image_page'                 );
add_action('wp_ajax_grab_image'         , 'ajax_grab_image_post'            );
add_action('wp_ajax_attach_image'       , 'ajax_attach_image_post'          );
add_action('wp_ajax_search_image'       , 'ajax_search_image_post'          );
add_action('wp_ajax_restore_image'      , 'ajax_restore_feature_image'      );

// require helper
require_once dirname(__FILE__). '/helper.php';

/**
 * define new menu page parameters
 */
function grab_image_page() {
    add_menu_page( 'Grab images', 'Grab images', 'activate_plugins', 'grab-image', 'grab_image_run', '');
}

/**
 * plugin page
 */
function grab_image_run() {
    if (!current_user_can('manage_options'))  {
        wp_die( __('You do not have sufficient permissions to access this page.') );
    } else { ?>

    <!-- Output for Plugin Options Page -->
    <div class="wrap">
        <?php
            if (isset($_GET['action']) && !empty($_GET['action'])) {
                echo '<div class="updated below-h2" id="message">';
                switch ($_GET['action']) {
                    case 'grab':
                        echo '<h2>Grab images</h2> <p>Images are being grabbed !</p>';
                        break;

                    case 'attach':
                        echo '<h2>Attach images</h2> <p>Images are being attached !</p>';
                        break;

                    case 'search':
                        echo '<h2>Search / Replace images</h2> <p>Images are being searched and replaced !</p>';
                        break;

                    case 'restore':
                        echo '<h2>Restore featured images</h2> <p>Restore old featured images of frutimian.no !</p>';
                        break;
                }
                echo '</div>';
            }
        ?>
        <p>
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a href="?page=grab-image&amp;action=grab" class="nav-link <?php echo (@$_GET['action'] == 'grab' ? 'active' : ''); ?>">Grab images</a>
                </li>
                <li class="nav-item">
                    <a href="?page=grab-image&amp;action=attach" class="nav-link <?php echo (@$_GET['action'] == 'attach' ? 'active' : ''); ?>">Attach images</a>
                </li>
                <li class="nav-item">
                    <a href="?page=grab-image&amp;action=search" class="nav-link <?php echo (@$_GET['action'] == 'search' ? 'active' : ''); ?>">Search / Replace images</a>
                </li>
                <li class="nav-item">
                    <a href="?page=grab-image&amp;action=restore" class="nav-link <?php echo (@$_GET['action'] == 'restore' ? 'active' : ''); ?>">Restore featured images</a>
                </li>
                <li class="nav-item">
                    <button id="box-status" class="btn btn-warning" style="display: none;"><span class="fa fa-refresh fa-refresh-animate"></span> Loading...</button>
                </li>
            </ul>
        </p>
    </div>
    <!-- End Output for Plugin Options Page -->

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha/css/bootstrap.min.css">
        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.0.0-beta1/jquery.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha/js/bootstrap.min.js"></script>
        <style type="text/css">
            .nav-link {
                cursor: pointer !important;
            }
            .fa-refresh-animate {
                -animation: spin .7s infinite linear;
                -webkit-animation: spin2 .7s infinite linear;
            }
            @-webkit-keyframes spin2 {
                from { -webkit-transform: rotate(0deg);}
                to { -webkit-transform: rotate(360deg);}
            }
            @keyframes spin {
                from { transform: scale(1) rotate(0deg);}
                to { transform: scale(1) rotate(360deg);}
            }
        </style>

    <?php
        if (isset($_GET['action']) && !empty($_GET['action'])) {
            $action = trim($_GET['action']);
            switch ($action) {
                case 'grab':
                case 'attach':
                case 'search':
                case 'restore':
                    $posts = get_posts([
                        'posts_per_page' => 100000,
                        'post_status' => 'any',
                        'orderby' => 'ID',
                        'order'   => 'ASC',
                    ]);

                    if ($action == 'restore') {
                        // list of old post_id with thumbnail
                        $helper = new grabimage_helper();
                        $thumbnail = $helper->get_old_thumbnail();
                    }
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function () {
                            var post = jQuery(".post");
                            var index = -1;

                            /**
                             * click stop button
                             */
                            jQuery('#button-stop').click(function () {
                                index = post.length;
                            });

                            /**
                             * click start button
                             */
                            jQuery('#button-start').click(function () {
                                function doNext() {
                                    if (++index >= post.length) {
                                        jQuery('#box-status').hide();
                                        return;
                                    } else {
                                        jQuery('#box-status').show();
                                    }

                                    var current = post.eq(index);
                                    var id = current.attr('rel');
                                    var data = {
                                        'action': '<?php echo $action . '_image'; ?>',
                                        'id': id,
                                        <?php if ($action == 'search') { ?>
                                        'search': search,
                                        'replace': replace,
                                        <?php } ?>
                                    };

                                    console.log(id);
                                    jQuery('#result-' + id).html('Loading ...');
                                    jQuery.ajax({
                                        url: ajaxurl,
                                        cache: false,
                                        data: data,
                                        method: 'POST',
                                        success: function(msg) {
                                            console.log(msg);
                                            jQuery('#result-' + id).html(msg);
                                            doNext();
                                        },
                                        error: function (msg) {
                                            console.log(msg);
                                            jQuery('#result-' + id).html('Error ... Restart in 10s');
                                            --index;
                                            setTimeout(doNext, 10000);
                                        }
                                    });
                                }

                                <?php if ($action == 'search') { ?>
                                    var search = jQuery('#input-search').val();
                                    var replace = jQuery('#input-replace').val();
                                    if (search == '' || replace == '') {
                                        alert('You must enter search and replace input !');
                                        return false;
                                    } else {
                                        if (confirm("Are you sure you want to search '" + search + "' and replace to '" + replace + "'") != true) {
                                            return false;
                                        }
                                    }
                                <?php } ?>

                                doNext();
                            });
                        });
                    </script>
                    <table class="table table-striped">
                        <tr>
                            <th colspan="5">
                                <button class="btn btn-primary" id="button-start">Start</button>
                                <button class="btn btn-danger" id="button-stop">Stop</button>
                            </th>
                        </tr>
                        <?php if ($action == 'search') { ?>
                        <tr>
                            <th colspan="4">
                                <label for="input-search">Search</label> <input class="input" type="text" id="input-search" />
                                <label for="input-replace">Replace</label> <input class="" type="text" id="input-replace" />
                            </th>
                        </tr>
                        <?php } ?>
                        <tr>
                            <th>#</th>
                            <th>Post</th>
                            <?php if ($action == 'restore') { ?>
                                <th>Featured image</th>
                                <th>Status</th>
                            <?php } ?>
                            <th>Result</th>
                        </tr>

                        <?php
                        foreach ($posts as $i => $post) {
                            $original_url = '';
                            if ($action == 'restore') {
                                if (!isset($thumbnail[$post->ID]) || empty($thumbnail[$post->ID]['attach_url'])) {
                                    continue;
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo ($i + 1); ?></td>
                                <td class="post" rel="<?php echo $post->ID; ?>"><a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank"><?php echo $post->ID; ?></a></td>
                                <?php
                                if ($action == 'restore') {
                                    echo '<td>';
                                    if (isset($thumbnail[$post->ID])) {
                                        $original_url = $thumbnail[$post->ID]['attach_url'];
                                        if (!empty($original_url)) {
                                            echo 'Original : ';
                                            echo "<a href='{$original_url}' target='_blank'>{$original_url}</a>";
                                        }
                                    }

                                    $current_url = get_the_post_thumbnail_url($post->ID, 'full');
                                    if (!empty($current_url)) {
                                        if (!empty($original_url)) {
                                            echo '<br/>';
                                        }
                                        echo 'Current : ';
                                        echo "<a href='{$current_url}' target='_blank'>{$current_url}</a>";
                                    }
                                    echo '</td>';
                                    echo '<td>';
                                    if (empty($current_url)) {
                                        echo '<span class="label label-default">No featured image</span>';
                                    } else {
                                        if ($helper->compare_basename($original_url, $current_url)) {
                                            echo '<span class="label label-success">Same featured image</span>';
                                        } else {
                                            echo '<span class="label label-danger">Different featured image</span>';
                                        }
                                    }
                                    echo '</td>';
                                }
                                ?>
                                <td id="result-<?php echo $post->ID; ?>"></td>
                            </tr>
                        <?php } ?>

                    </table>
                        <?php
                    break;
            }
        }
    }
}

/**
 * @package grab-image
 * ajax function to grab image
 */
function ajax_grab_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);

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

            // init file
            $file = array();
            $file['name'] = $helper->clean_filename($helper->basename($url));

            // get uploads path
            $time = current_time('mysql');
            if (substr($post->post_date, 0, 4) > 0) {
                $time = $post->post_date;
            }
            $uploads = wp_upload_dir($time);

            // check if image exist on hard disk
            if (!file_exists($uploads['path']. '/'. $file['name'])) {
                $exist = false;

                // sideload image
                $attachment_id = $helper->media_handle_sideload($url, $post->ID);
            } else {
                $exist = true;

                // insert attachment
                $attachment_id = $helper->media_insert_sideload($url, $post->ID);
            }

            // ignore if it has any error
            if (is_wp_error($attachment_id)) {
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
            echo "<a href=\"".get_edit_post_link($attachment_id)."\">{$url}</a> was successfully uploaded. It <b>{$exist}</b> already.<br/>";
        }

        // update post data search | replace
        if (count($search) > 0 && count($replace) > 0) {
            $post_content = str_replace($search, $replace, $post->post_content);
            $my_post = [
                'ID'           => $post->ID,
                'post_content' => $post_content,
            ];
            wp_update_post($my_post);
        }

        $count = count($search);
    }

    // set post thumbnail if not exist
    $helper->set_post_thumbnail($post->ID);

    echo 'Found '. $count. ' new image';
    wp_die();
}

/**
 * @package grab-image
 * ajax function to attach image
 */
function ajax_attach_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);

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
                'ID'           => $post->ID,
                'post_content' => $post_content,
            ];
            wp_update_post($my_post);
        }

        $count = count($search);
    }

    echo 'Attached '. $count. ' image';
    wp_die();
}

/**
 * @package grab-image
 * ajax function to search / replace image
 */
function ajax_search_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    $search_str = (string) $_REQUEST['search'];
    $replace_str = (string) $_REQUEST['replace'];

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
                'ID'           => $post->ID,
                'post_content' => $post_content,
            ];
            wp_update_post($my_post);
        }

        $count = count($search);
    }

    echo 'Searched / Replaced '. $count. ' image';
    wp_die();
}

/**
 * @package grab-image
 * ajax function to restore old featured images of frutimian.no
 */
function ajax_restore_feature_image() {
    $original_url = '';

    $id = intval($_REQUEST['id']);
    $post = get_post($id);
    if (empty($post)) {
        echo 'Wrong post ID';
        wp_die();
    }

    // current featured image
    $current_url = get_the_post_thumbnail_url($post->ID, 'full');

    // call helper class
    $helper = new grabimage_helper();

    // get original thumbnail array
    $thumbnail = $helper->get_old_thumbnail();

    if (isset($thumbnail[$id])) {
        $thumb = $thumbnail[$id];

        // original featured image url
        $original_url = $thumb['attach_url'];
        if (!empty($thumb)) {
            $attachment_id = $helper->get_attachment_id($original_url);
            if (empty($attachment_id)) {
                $attachment_id = $helper->media_handle_sideload($original_url, $id);
            }
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($post, $attachment_id);
            }
        }
    }

    // check post thumbnail exist or not
    $helper->set_post_thumbnail($post->ID);

    // new featured image url
    $new_url = get_the_post_thumbnail_url($id, 'full');

    if (empty($current_url)) {
        if (empty($new_url)) {
            echo '<span class="label label-danger">Error</span> no featured image was added';
        } else {
            echo '<span class="label label-success">Success</span> featured image was added';
        }
    } else {
        if ($helper->compare_basename($original_url, $current_url)) {
            echo 'Nothing to do';
        } else {
            echo '<span class="label label-success">Success</span> featured image was restored';
        }
    }

    wp_die();
}