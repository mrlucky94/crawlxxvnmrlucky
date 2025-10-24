<?php
/*
Plugin Name: CrawlxxvnMrLucky
Plugin URI: https://t.me/lorenkidkubi
Description: Plugin tự động crawl và import phim từ xxvnapi hoặc vsphim về WordPress post thường. Hỗ trợ phân loại, tags, gán chuyên mục, lưu link video embed iframe.
Version: 1.7
Author: MrLucky
Author URI: https://github.com/mrlucky94
*/

if (!defined('ABSPATH')) exit;

// --- Enqueue assets
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'toplevel_page_crawlxxvnmrlucky') {
        wp_enqueue_style('crawlxxvnmrlucky-admin', plugin_dir_url(__FILE__) . 'admin.css');
        wp_enqueue_script('crawlxxvnmrlucky-admin', plugin_dir_url(__FILE__) . 'admin.js', ['jquery'], '1.7', true);
        wp_localize_script('crawlxxvnmrlucky-admin', 'CrawlxxvnAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('crawlxxvn_nonce')
        ]);
    }
});

// --- Admin menu
add_action('admin_menu', function () {
    add_menu_page(
        'CrawlxxvnMrLucky',
        'CrawlxxvnMrLucky',
        'manage_options',
        'crawlxxvnmrlucky',
        'crawlxxvnmrlucky_admin_page',
        'dashicons-download',
        6
    );
});

// --- Admin page UI
function crawlxxvnmrlucky_admin_page() { ?>
    <div class="wrap crawlxxvn-wrap">
        <div class="crawlxxvn-header">
            <div class="crawlxxvn-logo">
                <svg width="48" height="48" viewBox="0 0 64 64" fill="none"><rect width="64" height="64" rx="12" fill="#353aff"/><path d="M18 32c7-7 21-7 28 0" stroke="#fff" stroke-width="4" stroke-linecap="round"/><circle cx="32" cy="32" r="6" fill="#fff"/><text x="32" y="58" font-size="10" fill="#fff" text-anchor="middle" font-family="Arial">MrLucky</text></svg>
            </div>
            <div class="crawlxxvn-info">
                <h1>CrawlxxvnMrLucky</h1>
                <div class="crawlxxvn-author">
                    <b>Tác giả:</b> MrLucky<br>
                    <b>Plugin WordPress auto crawl phim từ xxvnapi.com hoặc vsphim.com sử dụng cho tất cả các website wordpress.</b><br>
                    <b> Yêu cầu Theme</b> phải thêm trường field tên "_post_play" áp trong Bài viết post. để có ô nhập link nhúng player trong bài.
                </div>
            </div>
        </div>
        <div class="crawlxxvn-guide">
            <h2>Hướng dẫn sử dụng</h2>
            <ol>
                <li><b>Bấm nút "Check API"</b> để kiểm tra kết nối & lấy tổng số video và tổng số trang từng chuyên mục.</li>
                <li><b>Chọn nguồn crawl:</b> Phim mới cập nhật hoặc theo chuyên mục gốc (từ API).</li>
                <li><b>Chọn số trang cần crawl</b> hoặc chọn "Tất cả".</li>
                <li><b>Bấm "Bắt đầu Crawl Collect"</b> để tải phim về website.</li>
                <li>Plugin tự động:
                    <ul>
                        <li>Chống trùng lặp video đã import, Import image API về thư viện site</li>
                        <li>Tạo chuyên mục mới (nếu chưa có) và gán đúng</li>
                        <li>Lưu link player vào trường <b>"_post_play"</b> (theme không có trường nhập link embed iframe sẽ không có player)<br/> Sử dụng Plugin ACF để tạo trường field "_post_play" có ô nhập link nhúng player trong bài.</li>
                    </ul>
                </li>
            </ol>
            <p>— <i><strong><a href="https://t.me/lorenkidkubi" target="_blank" rel="noopener">Bấm để báo cáo lỗi</a> </strong>hoặc <strong><a href="https://mmo4me.com/threads/share-code-server-upload-hls-m3u8-tu-lam-server-rieng-tren-website.496209/post-8363994">tải miễn phí code Server HLS free để lưu trữ video riêng biệt</a></strong></i></p>
        </div>
        <div class="crawlxxvn-actions">
            <button id="crawlxxvn-check-api" class="button button-primary">Check API</button>
            <div id="crawlxxvn-check-result" style="margin-top:16px"></div>
            <div id="crawlxxvn-crawl-form" style="display:none"></div>
            <div id="crawlxxvn-progress"></div>
        </div>
    </div>
<?php }

function crawlxxvn_remote_get($url) {
    $response = wp_remote_get($url, ['timeout' => 30]);
    if (is_wp_error($response)) return false;
    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
}

function crawlxxvn_remote_get_with_retry($url, $max_retry = 6, $sleep = 2) {
    for ($i=0; $i<$max_retry; $i++) {
        $json = crawlxxvn_remote_get($url);
        if ($json && (
            (isset($json['items']) && is_array($json['items']) && count($json['items'])) ||
            (isset($json['pagination']) && isset($json['pagination']['totalPages']))
        )) {
            return $json;
        }
        sleep($sleep);
    }
    return false;
}

// --- AJAX: Check API chuyên mục vsphim.com + bổ sung chuyên mục theo country
add_action('wp_ajax_crawlxxvn_check_api', function() {
    check_ajax_referer('crawlxxvn_nonce', 'nonce');
    $source_api = $_POST['source_api'] ?? 'xxvnapi';

    if ($source_api=='vsphim') {
        // Danh sách CHUYÊN MỤC (category)
        $vsphim_categories = [
            ['name'=>"Trung Quốc",    'slug'=>"phim-sex-trung-quoc"],
            ['name'=>"JAV",           'slug'=>"jav"],
            ['name'=>"XVideos",       'slug'=>"xvideos"],
            ['name'=>"VLXX",          'slug'=>"vlxx"],
            ['name'=>"XNXX",          'slug'=>"xnxx"],
            ['name'=>"Việt Sub",      'slug'=>"phim-sex-viet-sub"],
            ['name'=>"Không Che",     'slug'=>"phim-sex-khong-che"],
            ['name'=>"Âu Mỹ (category)", 'slug'=>"phim-sex-chau-au"],
            ['name'=>"Thủ Dâm",       'slug'=>"thu-dam"],
            ['name'=>"Vụng Trộm",     'slug'=>"phim-sex-vung-trom"],
            ['name'=>"Loạn luân",     'slug'=>"phim-sex-loan-luan"],
            ['name'=>"3D",            'slug'=>"3d"],
            ['name'=>"Loli",          'slug'=>"loli"]
        ];

        // Danh sách QUỐC GIA (country) — theo yêu cầu
        $vsphim_countries = [
            ['name'=>"Việt Nam", 'slug'=>"viet-nam"],   // sẽ thành slug "country:viet-nam" trong dropdown
            ['name'=>"Nhật Bản", 'slug'=>"nhat-ban"],
            ['name'=>"Âu Mỹ",    'slug'=>"chau-au"],
        ];

        $categories = [];
        $total_all  = 0;

        foreach($vsphim_categories as $cat) {
            $api = 'https://nguon.vsphim.com/api/danh-sach?category='.$cat['slug'].'&page=1';
            $json = crawlxxvn_remote_get_with_retry($api, 5, 2);
            $total_cat = isset($json['pagination']['totalItems']) ? intval($json['pagination']['totalItems']) : 0;
            $last_page = isset($json['pagination']['totalPages']) ? intval($json['pagination']['totalPages']) : 1;
            $categories[] = [
                'name' => $cat['name'],
                'slug' => $cat['slug'], // giữ nguyên slug gốc cho category
                'total' => $total_cat,
                'last_page' => $last_page
            ];
            $total_all += $total_cat;
        }

        foreach($vsphim_countries as $c) {
            $api = 'https://nguon.vsphim.com/api/danh-sach?country='.$c['slug'].'&page=1';
            $json = crawlxxvn_remote_get_with_retry($api, 5, 2);
            $total_ct = isset($json['pagination']['totalItems']) ? intval($json['pagination']['totalItems']) : 0;
            $last_page = isset($json['pagination']['totalPages']) ? intval($json['pagination']['totalPages']) : 1;
            $categories[] = [
                'name' => $c['name'].' (country)',  // ghi chú để phân biệt trong dropdown
                'slug' => 'country:'.$c['slug'],    // QUAN TRỌNG: thêm prefix để JS không cần đổi
                'total'=> $total_ct,
                'last_page' => $last_page
            ];
            $total_all += $total_ct;
        }

        $api_new  = 'https://nguon.vsphim.com/api/danh-sach/phim-moi-cap-nhat?page=1';
        $json_new = crawlxxvn_remote_get_with_retry($api_new, 5, 2);
        $total_new = isset($json_new['pagination']['totalItems']) ? intval($json_new['pagination']['totalItems']) : 0;

        wp_send_json([
            'success'=>true,
            'msg'=>'Lấy dữ liệu thành công từ vsphim.com',
            'total'=>$total_new, // tổng phim mới cập nhật
            'categories'=>$categories
        ]);
    } else {
        $api_url = 'https://www.xxvnapi.com/api/phim-moi-cap-nhat';
        $res = wp_remote_get($api_url, ['timeout'=>15]);
        $body = wp_remote_retrieve_body($res);
        $data = json_decode($body, true);

        $total = !empty($data['total']) ? $data['total'] : (is_array($data['movies']) ? count($data['movies']) : 0);

        $xxvn_categories = [
            ['name'=>"Việt Nam Clip", 'slug'=>"viet-nam-clip"],
            ['name'=>"Mỹ - Châu Âu", 'slug'=>"chau-au"],
            ['name'=>"Trung Quốc", 'slug'=>"trung-quoc"],
            ['name'=>"Hàn Quốc", 'slug'=>"han-quoc-18-"],
            ['name'=>"AV Không che", 'slug'=>"khong-che"],
            ['name'=>"Jav HD", 'slug'=>"jav-hd"],
            ['name'=>"Hentai", 'slug'=>"hentai"],
            ['name'=>"Phim SexHD", 'slug'=>"sexhd"],
            ['name'=>"Phim sex Vietsub", 'slug'=>"vietsub"],
            ['name'=>"XVIDEOS", 'slug'=>"xvideos"],
            ['name'=>"Nhật Bản", 'slug'=>"nhat-ban"],
            ['name'=>"Học sinh", 'slug'=>"hoc-sinh"],
            ['name'=>"Vụng trộm", 'slug'=>"vung-trom"],
            ['name'=>"Tập thể", 'slug'=>"tap-the"],
            ['name'=>"Loạn luân", 'slug'=>"loan-luan"],
            ['name'=>"PornHub", 'slug'=>"pornhub"],
            ['name'=>"Hiếp dâm", 'slug'=>"hiep-dam"],
        ];

        $categories = [];
        foreach ($xxvn_categories as $cat) {
            $api_cat = 'https://www.xxvnapi.com/api/chuyen-muc/'.$cat['slug'].'?page=1';
            $res_cat = wp_remote_get($api_cat, ['timeout'=>15]);
            $body_cat = wp_remote_retrieve_body($res_cat);
            $data_cat = json_decode($body_cat, true);

            $last_page    = !empty($data_cat['page']['last_page']) ? intval($data_cat['page']['last_page']) : 1;
            $total_cat    = $last_page * 50;

            $categories[] = [
                'name'      => $cat['name'],
                'slug'      => $cat['slug'],
                'total'     => $total_cat,
                'last_page' => $last_page
            ];
        }
        wp_send_json([
            'success'=>true,
            'msg'=>'Kết nối API thành công!',
            'total'=>$total,
            'categories'=>$categories
        ]);
    }
});

add_action('wp_ajax_crawlxxvn_get_movies', function() {
    check_ajax_referer('crawlxxvn_nonce', 'nonce');
    $source_api = $_POST['source_api'] ?? 'xxvnapi';
    $source    = sanitize_text_field($_POST['source']);
    $slug      = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
    $from_page = max(1, intval($_POST['from_page']));
    $to_page   = max($from_page, intval($_POST['to_page']));
    $movies    = [];

    if ($source_api=='vsphim') {
        // Cho phép các category cũ + 3 country mới (tiền tố country:)
        $allow_slugs = [
            "phim-sex-trung-quoc", "jav", "xvideos", "phim-sex-viet-sub",
            "thu-dam", "phim-sex-vung-trom", "phim-sex-loan-luan", "3d", "loli",
            "vlxx", "xnxx", "phim-sex-khong-che", "phim-sex-chau-au",
            "country:viet-nam", "country:nhat-ban", "country:chau-au"
        ];

        for ($page=$from_page; $page<=$to_page; $page++) {
            if ($source==='category' && $slug) {
                if (!in_array($slug, $allow_slugs, true)) continue;

                // Nếu là country:slug thì gọi endpoint country, ngược lại category
                if (strpos($slug, 'country:') === 0) {
                    $country_slug = substr($slug, 8); // bỏ "country:"
                    $api = 'https://nguon.vsphim.com/api/danh-sach?country='.urlencode($country_slug).'&page='.$page;
                } else {
                    $api = 'https://nguon.vsphim.com/api/danh-sach?category='.urlencode($slug).'&page='.$page;
                }
            } else {
                $api = 'https://nguon.vsphim.com/api/danh-sach/phim-moi-cap-nhat?page='.$page;
            }

            $json = crawlxxvn_remote_get_with_retry($api, 6, 2);
            if (!$json || empty($json['items'])) continue;
            foreach($json['items'] as $item) {
                $movies[] = [
                    'name'   => $item['name'],
                    'slug'   => $item['slug'],
                    'poster' => $item['poster_url'] ?? '',
                    'year'   => $item['year'] ?? '',
                ];
            }
        }
        wp_send_json(['success'=>true, 'movies'=>$movies]);

    } else {
        // XXVNAPI
        for ($page=$from_page; $page<=$to_page; $page++) {
            if ($source==='new') {
                $api_url = "https://www.xxvnapi.com/api/phim-moi-cap-nhat?page={$page}";
            } else {
                $api_url = "https://www.xxvnapi.com/api/chuyen-muc/{$slug}?page={$page}";
            }
            $res = wp_remote_get($api_url, ['timeout'=>20]);
            if (is_wp_error($res)) continue;
            $body = wp_remote_retrieve_body($res);
            $data = json_decode($body, true);
            if (!$data || empty($data['movies'])) continue;
            foreach ($data['movies'] as $m) {
                $movies[] = $m;
            }
        }
        wp_send_json(['success'=>true, 'movies'=>$movies]);
    }
});

add_action('wp_ajax_crawlxxvn_import_one', function() {
    check_ajax_referer('crawlxxvn_nonce', 'nonce');
    $source_api = $_POST['source_api'] ?? 'xxvnapi';
    $movie = json_decode(stripslashes($_POST['movie']), true);

    if ($source_api=='vsphim') {
        $slug = $movie['slug'];
        $exist = get_posts(['name'=>$slug, 'post_type'=>'post', 'post_status'=>'any']);
        if ($exist) wp_send_json(['duplicated'=>1]);
        $detail = crawlxxvn_remote_get('https://nguon.vsphim.com/api/phim/'.$slug);
        if (!$detail || empty($detail['movie'])) {
            wp_send_json(['error'=>1, 'msg'=>'Không lấy được chi tiết phim']);
        }
        $m = $detail['movie'];
        $poster_url = !empty($m['poster_url']) ? $m['poster_url'] : '';
        $link_embed = '';
        if (!empty($detail['episodes'][0]['server_data'][0]['link_embed'])) {
            $link_embed = $detail['episodes'][0]['server_data'][0]['link_embed'];
        }
        // --- PHẦN QUAN TRỌNG: Gán đúng chuyên mục country
        $cat_ids = [];
        if (!empty($m['category'])) foreach($m['category'] as $cat){
            $term = get_term_by('slug', $cat['slug'], 'category');
            if (!$term) {
                $new_term = wp_insert_term($cat['name'], 'category', ['slug'=>$cat['slug']]);
                if (!is_wp_error($new_term) && !empty($new_term['term_id'])) {
                    $cat_ids[] = $new_term['term_id'];
                }
            } else {
                $cat_ids[] = $term->term_id;
            }
        }
        // Nếu là country thì gán bắt buộc đúng slug country
        if (!empty($_POST['category_slug']) && strpos($_POST['category_slug'], 'country:') === 0) {
            $country_slug = str_replace('country:', '', $_POST['category_slug']);
            $country_term = get_term_by('slug', $country_slug, 'category');
            if (!$country_term) {
                $new_term = wp_insert_term(ucwords(str_replace('-', ' ', $country_slug)), 'category', ['slug'=>$country_slug]);
                if (!is_wp_error($new_term) && !empty($new_term['term_id'])) {
                    $cat_ids[] = $new_term['term_id'];
                }
            } else {
                if (!in_array($country_term->term_id, $cat_ids)) {
                    $cat_ids[] = $country_term->term_id;
                }
            }
        }
        $cat_ids = array_unique($cat_ids);
        // --- END PHẦN QUAN TRỌNG

        $postarr = [
            'post_title'    => $m['name'],
            'post_content'  => $m['content'] ?? '',
            'post_status'   => 'publish',
            'post_type'     => 'post',
            'post_category' => $cat_ids,
            'post_name'     => $slug,
        ];
        $pid = wp_insert_post($postarr);
        if (is_wp_error($pid)) wp_send_json(['error'=>1, 'msg'=>'Không tạo được post']);
        if (!empty($poster_url)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            $thumb_id = media_sideload_image($poster_url, $pid, $m['name'], 'id');
            if (!is_wp_error($thumb_id)) set_post_thumbnail($pid, $thumb_id);
        }
        if (!empty($link_embed)) {
            update_post_meta($pid, '_post_play', $link_embed);
        }
        wp_send_json(['success'=>1, 'thumb'=>$poster_url, 'embed'=>$link_embed]);
    } else {
        // XXVNAPI gốc (không thay đổi)
        if (empty($movie['id'])) wp_send_json(['error'=>1]);
        if (crawlxxvn_check_exist($movie['id'])) wp_send_json(['duplicated'=>1]);
        $post_id = crawlxxvn_import_movie($movie, !empty($_POST['category_slug']) ? sanitize_text_field($_POST['category_slug']) : '');
        if ($post_id) wp_send_json(['success'=>1]);
        wp_send_json(['error'=>1]);
    }
});
function crawlxxvn_check_exist($movie_id) {
    $args = [
        'post_type'  => 'post',
        'meta_key'   => 'xxvnapi_id',
        'meta_value' => $movie_id,
        'post_status'=> 'any',
        'fields'     => 'ids',
        'numberposts'=> 1
    ];
    $exists = get_posts($args);
    return !empty($exists);
}

function crawlxxvn_import_movie($movie, $category_slug='') {
    $post_id = wp_insert_post([
        'post_title'   => $movie['name'],
        'post_content' => $movie['content'],
        'post_type'    => 'post',
        'post_status'  => 'publish'
    ]);
    if (!$post_id) return false;

    update_post_meta($post_id, 'xxvnapi_id', $movie['id']);
    update_post_meta($post_id, 'xxvnapi_slug', $movie['slug']);

    if (!empty($movie['thumb_url'])) crawlxxvn_set_thumbnail($post_id, $movie['thumb_url']);

    if (function_exists('update_field')) {
        $video_play = '';
        if (!empty($movie['episodes'][0]['server_data'][0]['link'])) $video_play = $movie['episodes'][0]['server_data'][0]['link'];
        if ($video_play) update_field('_post_play', $video_play, $post_id);
    }

    if (!empty($category_slug)) {
        if (!empty($movie['categories'])) {
            foreach ($movie['categories'] as $cat) {
                if ($cat['slug'] === $category_slug) {
                    crawlxxvn_set_category($post_id, $cat);
                    break;
                }
            }
        }
    } else {
        if (!empty($movie['categories'])) {
            foreach ($movie['categories'] as $cat) crawlxxvn_set_category($post_id, $cat);
        }
    }
    crawlxxvn_set_tags($post_id, $movie);
    return $post_id;
}

function crawlxxvn_set_thumbnail($post_id, $image_url) {
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $media = media_sideload_image($image_url, $post_id, null, 'id');
    if (!is_wp_error($media)) set_post_thumbnail($post_id, $media);
}

function crawlxxvn_set_category($post_id, $cat_data) {
    $term = term_exists($cat_data['slug'], 'category');
    if (!$term) $term = wp_insert_term($cat_data['name'], 'category', ['slug'=>$cat_data['slug']]);
    $cat_id = is_array($term) ? $term['term_id'] : $term;
    wp_set_post_terms($post_id, [$cat_id], 'category', true);
}

function crawlxxvn_set_tags($post_id, $movie) {
    $tags = [];
    if (!empty($movie['categories'])) foreach ($movie['categories'] as $cat) $tags[] = $cat['name'];
    if (!empty($movie['country']['name'])) $tags[] = $movie['country']['name'];
    if ($tags) wp_set_post_terms($post_id, $tags, 'post_tag', true);
}

add_action('admin_notices', function() {
    // Chỉ hiện thông báo cho admin
    if (!current_user_can('manage_options')) return;

    $latest = get_transient('crawlxxvn_latest_release');
    if ($latest === false) {
        $response = wp_remote_get('https://api.github.com/repos/mrlucky94/crawlxxvnmrlucky/releases/latest');
        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            set_transient('crawlxxvn_latest_release', $data, 12 * HOUR_IN_SECONDS);
        }
    } else {
        $data = $latest;
    }

    if (!empty($data['tag_name'])) {
        $current_version = '1.7'; // 
        $latest_version = ltrim($data['tag_name'], 'v'); // Nếu tag kiểu "v1.8" thì lấy "1.8"
        if (version_compare($latest_version, $current_version, '>')) {
            echo '<div class="notice notice-warning"><strong>Plugin CrawlxxvnMrLucky:</strong> Đã có phiên bản mới <b>'.$latest_version.'</b> trên <a href="https://github.com/mrlucky94/crawlxxvnmrlucky/releases" target="_blank">Github</a>. <br>Bạn nên tải về để cập nhật!</div>';
        }
    }
});
