<?php
/**
 * refine url
 * @param $url
 * @return string
 */
function reconstruct_url($url) {
    $url_parts = parse_url($url);
    $constructed_url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];

    return $constructed_url;
}

/**
 * encode url for unicode
 * @param $url
 * @return mixed
 */
function reencode_url($url) {
    $temp = basename($url);
    $temp2 = urlencode($temp);

    return str_replace($temp, $temp2, $url);
}

/**
 * make filename clean
 * @param $file
 * @return string
 */
function clean_filename($file) {
    $path = pathinfo($file);
    if (isset($path['extension'])) {
        $new_filename = preg_replace('/.' . $path['extension'] . '$/', '', $file);
        $file = sanitize_title($new_filename) . '.' . $path['extension'];
    }

    return $file;
}

/**
 * get original image url (remove size)
 * @param $file
 * @return string
 */
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
        preg_match('/src="([^"]*)"/i', $tag , $result);
        // can not regex src
        if (!isset($result[1]) || empty($result[1])) {
            continue;
        }
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