<?php
require_once dirname(__FILE__). '/snoopy.class.php';

class grabimage_helper
{
    /**
     * refine url
     * @param $url
     * @return string
     */
    public function reconstruct_url($url) {
        $url_parts = parse_url($url);
        $constructed_url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];

        return $constructed_url;
    }

    /**
     * encode url for unicode
     * @param $url
     * @return mixed
     */
    public function reencode_url($url) {
        $temp = basename($url);
        $temp2 = urlencode($temp);

        return str_replace($temp, $temp2, $url);
    }

    /**
     * make filename clean
     * @param $file
     * @return string
     */
    public function clean_filename($file) {
        $path = pathinfo($file);
        if (isset($path['extension'])) {
            $new_filename = preg_replace('/.' . $path['extension'] . '$/', '', $file);
            $file = substr(sanitize_title($new_filename), 0, 128) . '.' . $path['extension'];
        }

        return $file;
    }

    /**
     * get original image url (remove size)
     * @param $file
     * @return string
     */
    public function real_filename($file) {
        // remove size string
        if (preg_match("/^(https?:\/\/.*)\-[0-9]+x[0-9]+\.(jpg|jpeg|png)$/", $file, $m)) {
            $url = $m[1].'.'.$m[2];

            // check file exist or not
            $tmp = $this->download_url($this->reencode_url($url));
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
    public function extract_image($str, $check_ignore = true) {
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
    public function get_attachment_id( $url ) {
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
     * Downloads a url to a local temporary file using the WordPress HTTP Class.
     * Please note, That the calling function must unlink() the file.
     *
     * @since 2.5.0
     *
     * @param string $url the URL of the file to download
     * @param int $timeout The timeout for the request to download the file default 300 seconds
     * @return mixed WP_Error on failure, string Filename on success.
     */
    public function download_url( $url, $timeout = 300 ) {
        //WARNING: The file is not automatically deleted, The script must unlink() the file.
        if (! $url)
            return new WP_Error('http_no_url', __('Invalid URL Provided.'));

        $tmpfname = wp_tempnam($url);
        if (! $tmpfname)
            return new WP_Error('http_no_file', __('Could not create Temporary file.'));

        // download file by snoopy
        $snoopy = new Snoopy;

        // need an proxy?:
        //$snoopy->proxy_host = "my.proxy.host";
        //$snoopy->proxy_port = "8080";

        // set browser and referer:
        $snoopy->agent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)";
        $snoopy->referer = "http://www.google.com/";

        // set some cookies:
        $snoopy->cookies["SessionID"] = '238472834723489';
        $snoopy->cookies["favoriteColor"] = "blue";

        // set an raw-header:
        $snoopy->rawheaders["Pragma"] = "no-cache";

        // set some internal variables:
        $snoopy->maxredirs = 2;
        $snoopy->offsiteok = false;
        $snoopy->expandlinks = false;

        // fetch url
        $snoopy->fetch($url);

        $headers = array();
        while(list($key, $val) = each($snoopy->headers)){
            $headers[$key] = $val;
        }

        if (strpos($snoopy->response_code, '200') === false) {
            return new WP_Error('http_404', trim($snoopy->error));
        } else {
            file_put_contents($tmpfname, $snoopy->results);
        }

        return $tmpfname;
    }
}
