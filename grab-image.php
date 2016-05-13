<?php
/* Plugin Name: grab-image
 * Version: 0.1-alpha
 * Description: PLUGIN DESCRIPTION HERE
 * Author: YOUR NAME HERE
 * Author URI: YOUR SITE HERE
 * Plugin URI: PLUGIN SITE HERE
 * Text Domain: grab-image
 * Domain Path: /languages
 * @package grab-image
 */

define('ALLOW_UNFILTERED_UPLOADS', true);

// no limit time
ini_set('max_execution_time', 0);

// Start up the engine
add_action('admin_menu', 'grab_image_page');
add_action('wp_ajax_grab_image', 'grab_image_post');
add_action('wp_ajax_attach_image', 'attach_image_post');

function reconstruct_url($url) {
    $url_parts = parse_url($url);
    $constructed_url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];

    return $constructed_url;
}

function reencode_url($url) {
    $temp = basename($url);
    $temp2 = urlencode($temp);

    return str_replace($temp, $temp2, $url);
}

function clean_filename($file) {
    $path = pathinfo($file);
    $new_filename = preg_replace('/.' . $path['extension'] . '$/', '', $file);
    $file = sanitize_title($new_filename) . '.' . $path['extension'];

    return $file;
}

function real_filename($file) {
    // remove size string
    if (preg_match("/^(https?:\/\/.*)\-[0-9]+x[0-9]+\.(jpg|jpeg|png)$/", $file, $m)) {
        $url = $m[1].'.'.$m[2];

        // check file exist or not
        $tmp = download_url(reencode_url($url));
        if (!is_wp_error($tmp)) {
            $file = $url;
        }
        @unlink($tmp);
    }

    return $file;
}

/**
 * extract all a|img tag from post_content
 * @param $str
 * @return array
 */
function extract_image($str, $check_ignore = true) {
    $a_pattern = "/<a[^>]*>(<img [^><]*\/?>)<\/a>/";
    $img_pattern = "/<img[^>]+>/i";

    preg_match_all($a_pattern, $str, $a_tags);
    $str = preg_replace($a_pattern, '', $str);
    preg_match_all($img_pattern, $str, $img_tags);

    $tags = array_merge($a_tags[0], $img_tags[0]);
    $srcs = array();
    foreach ($tags as $tag) {
        // get the source string
        preg_match('%<img.*?src=["\'](.*?)["\'].*?/>%i', $tag , $result);
        $url = $result[1];

        if ($check_ignore) {
            // ignore s3 image
            if (strpos($url, 'wp-uploads.interactives.dk') !== false) {
                continue;
            }

            // ignore niteco image
            if (strpos($url, $_SERVER['SERVER_NAME']) !== false) {
                continue;
            }

            // ignore relative image
            if (strpos($url, 'http') === false) {
                continue;
            }
        }

        $srcs[$tag] = $url;
    }

    return $srcs;
}

/**
 * Get an attachment ID given a URL.
 * @param string $url
 * @return int Attachment ID on success, 0 on failure
 */
function get_attachment_id( $url ) {
    $attachment_id = 0;
    $dir = wp_upload_dir();
    if ( false !== strpos( $url, $dir['baseurl'] . '/' ) ) { // Is URL in uploads directory?
        $file = basename( $url );
        $query_args = array(
            'post_type'   => 'attachment',
            'post_status' => 'inherit',
            'fields'      => 'ids',
            'meta_query'  => array(
                array(
                    'value'   => $file,
                    'compare' => 'LIKE',
                    'key'     => '_wp_attachment_metadata',
                ),
            )
        );
        $query = new WP_Query( $query_args );
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post_id ) {
                $meta = wp_get_attachment_metadata( $post_id );
                $original_file       = basename( $meta['file'] );
                $cropped_image_files = wp_list_pluck( $meta['sizes'], 'file' );
                if ( $original_file === $file || in_array( $file, $cropped_image_files ) ) {
                    $attachment_id = $post_id;
                    break;
                }
            }
        }
    }
    return $attachment_id;
}

/**
 * Define new menu page parameters
 */
function grab_image_page() {
    add_menu_page( 'Grab image', 'Grab image', 'activate_plugins', 'grab-image', 'grab_image_run', '');
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
                    echo '<h2 id="">Grab image</h2> <p>Images are being grabbed !</p>';
                    break;

                case 'attach':
                    echo '<h2 id="">Attach image</h2> <p>Images are being attached !</p>';
                    break;
            }
            echo '</div>';
        } ?>
        <a href="?page=grab-image&amp;action=grab" class="button">Grab images</a>
        <a href="?page=grab-image&amp;action=attach" class="button">Attach images</a>
    </div>
    <!-- End Output for Plugin Options Page -->

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha/css/bootstrap.min.css">
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.0.0-beta1/jquery.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha/js/bootstrap.min.js"></script>

    <?php
        if (isset($_GET['action']) && !empty($_GET['action'])) {
            switch ($_GET['action']) {
                case 'grab':
                case 'attach':
                    $posts = get_posts([
                        'posts_per_page' => 100000,
                        'post_status' => 'any',
                        'orderby' => 'ID',
                        'order'   => 'ASC',
                    ]);
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function () {
                            var post = jQuery(".post");
                            var index = -1;

                            /**
                             * click stop button
                             */
                            jQuery('#stop-grab').click(function () {
                                index = post.length;
                            });

                            /**
                             * click start button
                             */
                            jQuery('#start-grab').click(function () {
                                function doNext() {
                                    if (++index >= post.length) {
                                        jQuery('#status-grab').html('Finish ...');
                                        return;
                                    } else {
                                        jQuery('#status-grab').html('Loading ...');
                                    }

                                    var current = post.eq(index);
                                    var id = current.attr('rel');
                                    var data = {
                                        'action': '<?php echo ($_GET['action'] == 'grab' ? 'grab_image' : 'attach_image'); ?>',
                                        'id': id,
                                    };

                                    console.log(id);
                                    jQuery('#image-' + id).html('Loading ...');
                                    jQuery.ajax({
                                        url: ajaxurl,
                                        cache: false,
                                        data: data,
                                        success: function(msg) {
                                            jQuery('#image-' + id).html(msg);
                                            doNext();
                                        },
                                        error: function (msg) {
                                            jQuery('#image-' + id).html('Error ...');
                                        }
                                    });
                                }

                                doNext();
                            });
                        });
                    </script>
                    <table class="table table-striped">
                        <tr>
                            <th colspan="3">
                                <button class="btn btn-primary" id="start-grab">Start</button>
                                <button class="btn btn-danger" id="stop-grab">Stop</button>
                                <span id="status-grab"></span>
                            </th>
                        </tr>
                        <tr>
                            <th>#</th>
                            <th>Post</th>
                            <th>Image</th>
                        </tr>
                    <?php
                    foreach ($posts as $i => $post) {
                        ?>
                        <tr>
                            <td><?php echo ($i + 1); ?></td>
                            <td class="post" rel="<?php echo $post->ID; ?>"><a href="<?php echo get_permalink($post->ID); ?>" target="_blank"><?php echo $post->ID; ?></a></td>
                            <td id="image-<?php echo $post->ID; ?>"></td>
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
 * ajax function to grab image
 */
function grab_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);

    $array = extract_image($post->post_content);
    $search = $replace = [];
    $count = 0;
    if (is_array($array) && count($array) > 0) {
        $thumb = 0;
        foreach ($array as $tag => $url) {
            // refine url
            $url = reconstruct_url($url);

            // real url
            $url = real_filename($url);

            // init file
            $file = array();
            $file['name'] = clean_filename(basename($url));

            // check file exist on hard disk or not
            $time = current_time('mysql');
            if (substr($post->post_date, 0, 4) > 0) {
                $time = $post->post_date;
            }
            $uploads = wp_upload_dir($time);
            $filename = $file['name'];

            // check if image exist
            if (!file_exists($uploads['path']. '/'. $filename)) {
                $exist = false;

                // build up array like PHP file upload
                $file['file'] = $uploads['path']. '/'. $file['name'];
                $filetype = wp_check_filetype($file['file']);
                $file['type'] = $filetype['type'];
                $file['tmp_name'] = download_url(reencode_url($url));
                $file['error'] = 0;

                // check ignore file
                $ignore = false;

                // ignore 404 file
                if (is_wp_error($file['tmp_name'])) {
                    @unlink($file['tmp_name']);
                    $ignore = true;
                    // return new WP_Error('grabfromurl', 'Could not download image from remote source');
                }

                // ignore empty file
                if (!$ignore) {
                    $file['size'] = filesize($file['tmp_name']);
                    if ($file['size'] <= 0) {
                        $ignore = true;
                    }
                }

                // continue if ignore
                if ($ignore) {
                    echo "error ; {$url} <br/>";
                    continue;
                }

                // sideload image
                $attachmentId = media_handle_sideload($file, $post->ID);
            } else {
                $exist = true;

                // insert to db
                $file['file'] = $uploads['path']. '/'. $file['name'];
                $file['url'] = $uploads['url'] . "/$filename";
                $filetype = wp_check_filetype($file['file']);
                $file['type'] = $filetype['type'];
                $file['error'] = 0;

                $type = $file['type'];
                $file = $file['file'];
                $title = preg_replace('/\.[^.]+$/', '', basename($file));
                $content = '';

                // Use image exif/iptc data for title and caption defaults if possible.
                if ( $image_meta = @wp_read_image_metadata($file) ) {
                    if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) )
                        $title = $image_meta['title'];
                    if ( trim( $image_meta['caption'] ) )
                        $content = $image_meta['caption'];
                }

                // Construct the attachment array.
                $attachment = array(
                    'post_mime_type' => $type,
                    'guid' => $url,
                    'post_parent' => $post->ID,
                    'post_title' => $title,
                    'post_content' => $content,
                );

                // This should never be set as it would then overwrite an existing attachment.
                unset( $attachment['ID'] );

                // Save the attachment metadata
                $attachmentId = wp_insert_attachment($attachment, $file, $post->ID);
            }

            // create the thumbnails
            $attach_data = wp_generate_attachment_metadata( $attachmentId,  get_attached_file($attachmentId));

            // update metadata
            wp_update_attachment_metadata( $attachmentId,  $attach_data);

            // search a|img tag
            $search[] = $tag;

            // replace with
            $image = wp_get_attachment_image_src($attachmentId, 'full');
            $id = $attachmentId;
            $src = $image[0];
            $width = $image[1];
            $height = $image[2];
            $replace[] = "<a href=\"{$src}\" rel=\"attachment wp-att-{$id}\">"
                . "<img class=\"alignnone size-full wp-image-{$id}\" src=\"{$src}\" alt=\"{$post->post_title}\" width=\"{$width}\" height=\"{$height}\" />"
                . "</a>";

            // set post thumbnail
            if ($thumb == 0) {
                set_post_thumbnail($post, $attachmentId);
                $thumb = 1;
            }

            $exist = ($exist ? '' : 'none-exist');
            echo "success ; {$url} ; {$attachmentId} ; {$exist} <br/>";
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

    echo 'Found '. $count. ' new image';
    wp_die();
}

/**
 * ajax function to attach image
 */
function attach_image_post() {
    $id = intval($_REQUEST['id']);
    $post = get_post($id);

    $array = extract_image($post->post_content, false);
    $search = $replace = [];
    $count = 0;
    if (is_array($array) && count($array) > 0) {
        foreach ($array as $tag => $url) {
            $attachmentId = get_attachment_id($url);
            if (empty($attachmentId)) {
                continue;
            }

            // get post attachment
            $attachments = get_attached_media('image', $post->ID);

            // ignore if image was attachment
            if (isset($attachments[$attachmentId])) {
                continue;
            }

            wp_update_post([
                'ID' => $attachmentId,
                'post_parent' => $post->ID,
            ]);

            // search a|img tag
            $search[] = $tag;

            // replace with
            $image = wp_get_attachment_image_src($attachmentId, 'full');
            $id = $attachmentId;
            $src = $image[0];
            $width = $image[1];
            $height = $image[2];
            $replace[] = "<a href=\"{$src}\" rel=\"attachment wp-att-{$id}\">"
                . "<img class=\"alignnone size-full wp-image-{$id}\" src=\"{$src}\" alt=\"{$post->post_title}\" width=\"{$width}\" height=\"{$height}\" />"
                . "</a>";

            echo "success ; {$url} ; {$attachmentId} <br/>";
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

    echo 'Attach '. $count. ' image';
    wp_die();
}